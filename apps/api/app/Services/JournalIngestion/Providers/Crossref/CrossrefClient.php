<?php

namespace App\Services\JournalIngestion\Providers\Crossref;

use App\Services\JournalIngestion\IngestJournalRecords;
use App\Support\CatalogNormalizer;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Throwable;

/** Serial singleton lookups only. No list, work, member, redirect or metadata URL fetching. */
class CrossrefClient
{
    public const BASE = 'https://api.crossref.org';

    public function __construct(private CrossrefResponse $schema) {}

    public function lookup(array $identifiers, int $limit): array
    {
        if ($limit < 1 || $limit > IngestJournalRecords::MAX_RECORDS || ! array_is_list($identifiers)
            || $identifiers === [] || count($identifiers) > $limit) {
            throw new DomainException('crossref_invalid_request');
        }
        // Validate the entire bounded input before the first request; never silently truncate.
        $issns = [];
        foreach ($identifiers as $identifier) {
            if (! is_string($identifier) || strlen($identifier) > 32) {
                throw new DomainException('crossref_invalid_request');
            }
            $issns[] = CatalogNormalizer::issn($identifier);
        }
        $issns = array_values(array_unique($issns));
        $settings = config('journal_ingestion.crossref');
        if (($settings['base_url'] ?? null) !== self::BASE) {
            throw new DomainException('crossref_invalid_configuration');
        }
        foreach (['timeout' => [1, 60], 'connect_timeout' => [1, 30]] as $key => [$min, $max]) {
            $value = filter_var($settings[$key], FILTER_VALIDATE_INT);
            if ($value === false || $value < $min || $value > $max) {
                throw new DomainException('crossref_invalid_configuration');
            }
            $settings[$key] = $value;
        }
        $mailto = $settings['mailto'];
        if (! is_string($mailto) || strlen($mailto) > 254 || ($mailto !== ''
            && (! filter_var($mailto, FILTER_VALIDATE_EMAIL) || preg_match('/[^\x21-\x7e]/', $mailto)))) {
            throw new DomainException('crossref_invalid_configuration');
        }
        $records = [];
        $bytes = 0;
        $missing = [];
        $delay = 1000;
        foreach ($issns as $index => $issn) {
            if ($index > 0) {
                Sleep::usleep($delay * 1000);
            }
            [$body, $delay] = $this->request(self::BASE.'/journals/'.$issn, $settings);
            if ($body === null) {
                $missing[] = $issn;

                continue;
            }
            $bytes += strlen($body);
            if ($bytes > 1048576) {
                throw new DomainException('crossref_batch_too_large');
            }
            $record = $this->schema->journal($body);
            // Provider payload retained unchanged apart from explicit local request identity.
            $record['_lookup_issn'] = $issn;
            $records[] = $record;
        }

        return ['records' => $records, 'not_found' => $missing, 'lookups' => count($issns)];
    }

    private function request(string $url, array $settings): array
    {
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                $response = Http::acceptJson()
                    ->withUserAgent('Fikarohana/0.1 (controlled Crossref identity enrichment'.($settings['mailto'] === '' ? '' : '; mailto:'.$settings['mailto']).')')
                    ->timeout($settings['timeout'])->connectTimeout($settings['connect_timeout'])->withoutRedirecting()
                    ->withOptions([
                        'on_headers' => function ($response): void {
                            if ((int) $response->getHeaderLine('Content-Length') > 1048576) {
                                throw new DomainException('crossref_response_too_large');
                            }
                        },
                        'progress' => function ($total, $downloaded): void {
                            if ($downloaded > 1048576) {
                                throw new DomainException('crossref_response_too_large');
                            }
                        },
                    ])->get($url, $settings['mailto'] === '' ? [] : ['mailto' => $settings['mailto']]);
            } catch (ConnectionException) {
                if ($attempt === 3) {
                    throw new DomainException('crossref_connection_failed');
                }
                Sleep::usleep(1000000);

                continue;
            } catch (Throwable) {
                throw new DomainException('crossref_transport_failed');
            }
            $delay = 1000;
            $rate = $response->header('x-rate-limit-limit');
            $interval = $response->header('x-rate-limit-interval');
            if (ctype_digit((string) $rate) && (int) $rate > 0 && preg_match('/^([0-9]+)s$/D', (string) $interval, $match)) {
                $delay = max(1000, (int) ceil((int) $match[1] / (int) $rate * 1000));
                if ($delay > 5000) {
                    throw new DomainException('crossref_retry_deferred');
                }
            }
            if ($response->status() === 429 || in_array($response->status(), [500, 502, 503, 504], true)) {
                if ($attempt === 3) {
                    throw new DomainException($response->status() === 429 ? 'crossref_rate_limited' : 'crossref_unavailable');
                }
                $after = $response->header('Retry-After');
                if ($after !== '') {
                    $seconds = ctype_digit((string) $after) ? (int) $after : strtotime((string) $after) - time();
                    if ($seconds < 0 || $seconds > 5) {
                        throw new DomainException('crossref_retry_deferred');
                    }
                    $delay = max($delay, $seconds * 1000);
                }
                Sleep::usleep($delay * 1000);

                continue;
            }
            if ($response->status() === 404) {
                return [null, $delay];
            }
            if ($response->status() !== 200) {
                throw new DomainException('crossref_http_failed');
            }
            if (! preg_match('~^application/(?:json|vnd\.crossref-api-message\+json)(?:\s*;|$)~i', $response->header('Content-Type'))) {
                throw new DomainException('crossref_not_json');
            }
            $body = $response->body();
            if (strlen($body) > 1048576) {
                throw new DomainException('crossref_response_too_large');
            }

            return [$body, $delay];
        }
        throw new DomainException('crossref_unavailable');
    }
}

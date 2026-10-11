<?php

namespace App\Services\JournalIngestion\Providers\Doaj;

use App\Services\JournalIngestion\IngestJournalRecords;
use DomainException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Throwable;

/** Fixed official origin, GET journal search only; never follows metadata/next URLs. */
class DoajClient
{
    public function __construct(private DoajResponse $schema) {}

    public function search(string $query, int $limit): array
    {
        if ($limit < 1 || $limit > IngestJournalRecords::MAX_RECORDS || trim($query) === '' || strlen($query) > 512
            || preg_match('/[\x00-\x1f\x7f]/', $query) || ! mb_check_encoding($query, 'UTF-8')) {
            throw new DomainException('doaj_invalid_request');
        }
        $settings = config('journal_ingestion.doaj');
        $base = rtrim((string) $settings['base_url'], '/');
        if (! in_array($base, ['https://doaj.org/api/v4', 'https://doaj.org/api'], true)) {
            throw new DomainException('doaj_invalid_configuration');
        }
        foreach (['timeout' => [1, 60], 'connect_timeout' => [1, 30], 'attempts' => [1, 3], 'delay_ms' => [500, 5000],
            'page_size' => [1, 100], 'max_response_bytes' => [1024, 1048576]] as $key => [$min, $max]) {
            $value = filter_var($settings[$key], FILTER_VALIDATE_INT);
            if ($value === false || $value < $min || $value > $max) {
                throw new DomainException('doaj_invalid_configuration');
            }
            $settings[$key] = $value;
        }
        if (! is_string($settings['user_agent']) || strlen($settings['user_agent']) > 200
            || ! preg_match('/^[\x20-\x7e]+$/D', $settings['user_agent'])) {
            throw new DomainException('doaj_invalid_configuration');
        }
        $pageSize = min($limit, $settings['page_size']);
        // Fixed pageSize preserves offsets; choose a divisor to avoid fetching past --limit.
        while ($limit % $pageSize !== 0) {
            $pageSize--;
        }
        $records = [];
        for ($page = 1; count($records) < $limit; $page++) {
            if ($page > 1) {
                Sleep::usleep($settings['delay_ms'] * 1000);
            }
            $body = $this->request($base.'/search/journals/'.rawurlencode($query), $page, $pageSize, $settings);
            $data = $this->schema->page($body, $page, $pageSize);
            $records = array_merge($records, array_slice($data['results'], 0, $limit - count($records)));
            if ($page * $pageSize >= $data['total']) {
                break;
            }
        }

        return $records;
    }

    private function request(string $url, int $page, int $pageSize, array $settings): string
    {
        for ($attempt = 1; $attempt <= $settings['attempts']; $attempt++) {
            try {
                $response = Http::acceptJson()->withUserAgent($settings['user_agent'])
                    ->timeout($settings['timeout'])->connectTimeout($settings['connect_timeout'])->withoutRedirecting()
                    ->withOptions([
                        'on_headers' => function ($response) use ($settings): void {
                            if ((int) $response->getHeaderLine('Content-Length') > $settings['max_response_bytes']) {
                                throw new DomainException('doaj_response_too_large');
                            }
                        },
                        'progress' => function ($total, $downloaded) use ($settings): void {
                            if ($downloaded > $settings['max_response_bytes']) {
                                throw new DomainException('doaj_response_too_large');
                            }
                        },
                    ])->get($url, ['page' => $page, 'pageSize' => $pageSize, 'sort' => 'id:asc']);
            } catch (ConnectionException) {
                if ($attempt === $settings['attempts']) {
                    throw new DomainException('doaj_connection_failed');
                }
                Sleep::usleep($settings['delay_ms'] * 1000);

                continue;
            } catch (Throwable) {
                throw new DomainException('doaj_transport_failed');
            }
            if ($response->status() === 429 || in_array($response->status(), [500, 502, 503, 504], true)) {
                if ($attempt === $settings['attempts']) {
                    throw new DomainException($response->status() === 429 ? 'doaj_rate_limited' : 'doaj_unavailable');
                }
                $retryAfter = $response->header('Retry-After');
                $delay = $settings['delay_ms'];
                if ($retryAfter !== '') {
                    $seconds = ctype_digit((string) $retryAfter) ? (int) $retryAfter : strtotime((string) $retryAfter) - time();
                    if ($seconds > 5 || $seconds < 0) {
                        throw new DomainException('doaj_retry_deferred');
                    }
                    $delay = max($delay, $seconds * 1000);
                }
                Sleep::usleep($delay * 1000);

                continue;
            }
            if ($response->status() !== 200) {
                throw new DomainException('doaj_http_failed');
            }
            if (! str_contains(strtolower($response->header('Content-Type')), 'application/json')) {
                throw new DomainException('doaj_not_json');
            }
            $body = $response->body();
            if (strlen($body) > $settings['max_response_bytes']) {
                throw new DomainException('doaj_response_too_large');
            }

            return $body;
        }
        throw new DomainException('doaj_unavailable');
    }
}

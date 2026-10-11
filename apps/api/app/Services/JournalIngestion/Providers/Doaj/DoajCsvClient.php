<?php

namespace App\Services\JournalIngestion\Providers\Doaj;

use DomainException;
use Illuminate\Support\Facades\Http;
use Throwable;

/** Stable official source; one explicitly validated DOAJ-owned download redirect. */
class DoajCsvClient
{
    public const SOURCE = 'https://doaj.org/csv';

    public function __construct(private DoajCsvParser $parser) {}

    public function records(int $limit): array
    {
        if ($limit < 1 || $limit > 100) {
            throw new DomainException('doaj_csv_invalid_limit');
        }
        $settings = config('journal_ingestion.doaj_csv');
        foreach (['timeout' => [1, 120], 'connect_timeout' => [1, 30], 'max_download_bytes' => [1024, 33554432]] as $key => [$min, $max]) {
            $value = filter_var($settings[$key] ?? null, FILTER_VALIDATE_INT);
            if ($value === false || $value < $min || $value > $max) {
                throw new DomainException('doaj_csv_invalid_configuration');
            }
            $settings[$key] = $value;
        }
        $stream = tmpfile();
        if ($stream === false) {
            throw new DomainException('doaj_csv_storage_failed');
        }
        try {
            $url = self::SOURCE;
            for ($hop = 0; $hop < 2; $hop++) {
                $response = Http::withHeaders(['Accept' => 'text/csv', 'Accept-Encoding' => 'identity'])
                    ->withUserAgent('Fikarohana/0.1 (controlled journal ingestion)')
                    ->timeout($settings['timeout'])->connectTimeout($settings['connect_timeout'])->withoutRedirecting()
                    ->withOptions(['stream' => true, 'read_timeout' => $settings['timeout'],
                        'on_headers' => function ($response) use ($settings): void {
                            if ((int) $response->getHeaderLine('Content-Length') > $settings['max_download_bytes']) {
                                throw new DomainException('doaj_csv_download_too_large');
                            }
                        },
                    ])->get($url);
                if ($hop === 0 && in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                    $url = $response->header('Location');
                    $parts = parse_url($url);
                    if (strlen($url) > 4096 || ! is_array($parts) || ($parts['scheme'] ?? '') !== 'https'
                        || ($parts['host'] ?? '') !== 'doaj-live-journal-csv.s3.amazonaws.com'
                        || isset($parts['user'], $parts['pass']) || isset($parts['user']) || isset($parts['port']) || isset($parts['fragment'])
                        || ! preg_match('~^/doaj_journalcsv_[0-9]{8}_[0-9]{4}_utf8\.csv$~D', $parts['path'] ?? '')) {
                        throw new DomainException('doaj_csv_unsafe_redirect');
                    }
                    $response->toPsrResponse()->getBody()->close();

                    continue;
                }
                if ($response->status() !== 200) {
                    throw new DomainException('doaj_csv_http_failed');
                }
                $mime = strtolower(explode(';', $response->header('Content-Type'))[0]);
                if (! in_array($mime, ['text/csv', 'application/csv', 'text/plain', 'application/octet-stream', 'binary/octet-stream'], true)) {
                    throw new DomainException('doaj_csv_invalid_format');
                }
                if ((int) $response->header('Content-Length') > $settings['max_download_bytes']
                    || ! in_array(strtolower($response->header('Content-Encoding')), ['', 'identity'], true)) {
                    throw new DomainException('doaj_csv_download_too_large');
                }
                $body = $response->toPsrResponse()->getBody();
                $bytes = 0;
                $deadline = microtime(true) + $settings['timeout'];
                try {
                    while (! $body->eof()) {
                        $chunk = $body->read(8192);
                        $bytes += strlen($chunk);
                        if ($bytes > $settings['max_download_bytes']) {
                            throw new DomainException('doaj_csv_download_too_large');
                        }
                        if (microtime(true) > $deadline || ($chunk === '' && ! $body->eof())) {
                            throw new DomainException('doaj_csv_download_timeout');
                        }
                        if (fwrite($stream, $chunk) !== strlen($chunk)) {
                            throw new DomainException('doaj_csv_storage_failed');
                        }
                    }
                } finally {
                    $body->close();
                }
                rewind($stream);

                return $this->parser->parse($stream, $limit);
            }
            throw new DomainException('doaj_csv_redirect_failed');
        } catch (DomainException $exception) {
            throw $exception;
        } catch (Throwable) {
            // No response body, signed Location or network exception crosses this boundary.
            throw new DomainException('doaj_csv_transport_failed');
        } finally {
            fclose($stream);
        }
    }
}

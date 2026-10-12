<?php

namespace App\Services\OfficialSources;

use App\Models\JournalSource;
use App\Models\SourceRecord;
use App\Services\JournalIngestion\PostgresText;
use DomainException;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Throwable;

/** Only a persisted, explicitly approved page can be requested. No URL fetch entry point. */
class OfficialSourceClient
{
    public const MAX_BYTES = 262144;

    public const VERSION = 'official-page-raw-v1';

    public function __construct(private OfficialUrl $urls, private PublicDns $dns) {}

    public function fetch(JournalSource $source, ?SourceRecord $previous = null): array
    {
        $source = $source->fresh();
        if (! $source || ! $source->official_journal_id || $source->approval_status !== 'approved') {
            throw new DomainException('official_not_approved');
        }
        if ($previous && $previous->journal_source_id !== $source->id) {
            throw new DomainException('official_snapshot_mismatch');
        }
        if (! extension_loaded('curl')) {
            throw new DomainException('official_curl_required');
        }
        $requested = $this->urls->normalize($source->official_url);
        if ($this->urls->origin($requested) !== $source->official_origin) {
            throw new DomainException('official_origin_mismatch');
        }
        $url = $requested;
        $visited = [];
        for ($hop = 0; $hop <= 2; $hop++) {
            if (isset($visited[$url])) {
                throw new DomainException('official_redirect_loop');
            }
            $visited[$url] = true;
            // Reject cross-origin redirects, even public ones. Register/approve the new page separately.
            if ($this->urls->origin($url) !== $source->official_origin) {
                throw new DomainException('official_redirect_disallowed');
            }
            Sleep::sleep(30);
            $source = $source->fresh();
            if (! $source || ! $source->official_journal_id || $source->approval_status !== 'approved') {
                throw new DomainException('official_not_approved');
            }
            if ($this->urls->normalize($source->official_url) !== $requested || $this->urls->origin($url) !== $source->official_origin) {
                throw new DomainException('official_origin_mismatch');
            }
            $ips = $this->dns->validate($this->dns->resolve(parse_url($url, PHP_URL_HOST)));
            $ip = $ips[0];
            $address = str_contains($ip, ':') ? '['.$ip.']' : $ip;
            $headers = ['Accept' => 'text/html, text/plain', 'Accept-Encoding' => 'identity'];
            if ($previous && $source->last_final_url === $url) {
                foreach (['last_etag' => 'If-None-Match', 'last_modified' => 'If-Modified-Since'] as $field => $header) {
                    if (($validator = $this->validator($source->{$field})) !== null) {
                        $headers[$header] = $validator;
                    }
                }
            }
            $tooLarge = false;
            $rejected = null;
            $sink = fopen('php://temp/maxmemory:262144', 'w+');
            try {
                $response = Http::withHeaders($headers)
                    ->withUserAgent('Fikarohana/0.1 (operator-approved official journal evidence; no crawler)')
                    ->timeout(15)->connectTimeout(5)->withoutRedirecting()
                    ->setHandler(new CurlHandler)
                    ->withOptions([
                        'verify' => true, 'proxy' => '', 'auth' => null, 'protocols' => ['https'],
                        'cookies' => false, 'decode_content' => false, 'sink' => $sink,
                        'curl' => [CURLOPT_RESOLVE => [parse_url($url, PHP_URL_HOST).':443:'.$address],
                            CURLOPT_FRESH_CONNECT => true, CURLOPT_FORBID_REUSE => true],
                        'on_headers' => function ($response) use (&$tooLarge, &$rejected): void {
                            if ((float) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                                $tooLarge = true;
                                throw new DomainException('official_response_too_large');
                            }
                            // Reject error/redirect bodies before download, and compressed bodies entirely.
                            if (! in_array($response->getStatusCode(), [200, 304, 301, 302, 303, 307, 308], true)) {
                                $rejected = $this->statusCode($response->getStatusCode());
                                throw new DomainException($rejected);
                            }
                            $encoding = strtolower(trim($response->getHeaderLine('Content-Encoding')));
                            if ($encoding !== '' && $encoding !== 'identity') {
                                $rejected = 'official_unsupported_encoding';
                                throw new DomainException($rejected);
                            }
                            if ($response->getStatusCode() === 200 && ! preg_match('~^text/(html|plain)(?:\s*;|$)~i', $response->getHeaderLine('Content-Type'))) {
                                $rejected = 'official_unsupported_mime';
                                throw new DomainException($rejected);
                            }
                        },
                        'progress' => function ($total, $downloaded) use (&$tooLarge): void {
                            if ($downloaded > self::MAX_BYTES) {
                                $tooLarge = true;
                                throw new DomainException('official_response_too_large');
                            }
                        },
                    ])->get($url);
                $body = $response->body();
            } catch (Throwable) {
                throw new DomainException($tooLarge ? 'official_response_too_large' : ($rejected ?? 'official_transport_failed'));
            } finally {
                fclose($sink);
            }
            if (in_array($response->status(), [301, 302, 303, 307, 308], true)) {
                if ($hop === 2) {
                    throw new DomainException('official_too_many_redirects');
                }
                $location = $response->header('Location');
                if (! is_string($location) || $location === '' || strlen($location) > 2048) {
                    throw new DomainException('official_invalid_redirect');
                }
                try {
                    $url = $this->urls->normalize((string) UriResolver::resolve(new Uri($url), new Uri($location)));
                } catch (Throwable) {
                    throw new DomainException('official_redirect_disallowed');
                }

                continue;
            }
            if ($response->status() === 304) {
                if (! $previous || (! isset($headers['If-None-Match']) && ! isset($headers['If-Modified-Since']))) {
                    throw new DomainException('official_unexpected_304');
                }

                return ['outcome' => 'not_modified', 'final_url' => $url, 'http_status' => 304];
            }
            if ($response->status() !== 200) {
                throw new DomainException($this->statusCode($response->status()));
            }
            if (strlen($body) > self::MAX_BYTES || (float) $response->header('Content-Length') > self::MAX_BYTES) {
                throw new DomainException('official_response_too_large');
            }
            $encoding = strtolower(trim($response->header('Content-Encoding')));
            if ($encoding !== '' && $encoding !== 'identity') {
                throw new DomainException('official_unsupported_encoding');
            }
            $type = strtolower(trim(explode(';', $response->header('Content-Type'))[0]));
            if (! in_array($type, ['text/html', 'text/plain'], true)) {
                throw new DomainException('official_unsupported_mime');
            }
            if (preg_match('/charset\s*=\s*["\']?([^;"\'\s]+)/i', $response->header('Content-Type'), $charset)
                && ! in_array(strtolower($charset[1]), ['utf-8', 'us-ascii'], true)) {
                throw new DomainException('official_unsupported_charset');
            }
            PostgresText::validate($body);
            if ($body === '') {
                throw new DomainException('official_empty_content');
            }
            if ($type === 'text/html') {
                // No entity expansion, network, resource loads or rendering. Tolerate recoverable HTML syntax.
                $old = libxml_use_internal_errors(true);
                try {
                    $dom = new \DOMDocument;
                    if (! preg_match('/<[a-z][^>]*>/i', $body) || ! $dom->loadHTML($body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                        throw new DomainException('official_malformed_html');
                    }
                } finally {
                    libxml_clear_errors();
                    libxml_use_internal_errors($old);
                }
            }
            $metadata = ['requested_url' => $requested, 'final_url' => $url, 'http_status' => 200,
                'content_type' => $type, 'bytes' => strlen($body), 'parser_version' => self::VERSION,
                'etag' => $this->validator($response->header('ETag')),
                'last_modified' => $this->validator($response->header('Last-Modified'))];

            return ['outcome' => 'captured', 'body' => $body, 'metadata' => $metadata,
                'content_hash' => hash('sha256', $body),
                'fingerprint' => hash('sha256', $requested."\n".$type."\n".$body)];
        }
        throw new DomainException('official_too_many_redirects');
    }

    private function validator(?string $value): ?string
    {
        return is_string($value) && $value !== '' && strlen($value) <= 512
            && ! preg_match('/[^\x20-\x7e]/', $value) ? $value : null;
    }

    private function statusCode(int $status): string
    {
        return match ($status) {
            403 => 'official_forbidden', 404 => 'official_not_found', 429 => 'official_rate_limited',
            500, 502, 503, 504 => 'official_unavailable', default => 'official_http_failed',
        };
    }
}

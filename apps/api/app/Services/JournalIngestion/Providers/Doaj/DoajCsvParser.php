<?php

namespace App\Services\JournalIngestion\Providers\Doaj;

use App\Services\JournalIngestion\IngestJournalRecords;
use App\Services\JournalIngestion\PostgresText;
use DomainException;

class DoajCsvParser
{
    /** Seekable bounded download resource; only the selected prefix is parsed. */
    public function parse($stream, int $limit): array
    {
        if ($limit < 1 || $limit > IngestJournalRecords::MAX_RECORDS) {
            throw new DomainException('doaj_csv_invalid_limit');
        }
        $headers = fgetcsv($stream, 0, ',', '"', '');
        if (! is_array($headers)) {
            throw new DomainException('doaj_csv_invalid_header');
        }
        $headerEnd = ftell($stream);
        rewind($stream);
        $headerRaw = fread($stream, $headerEnd);
        fseek($stream, $headerEnd);
        if ($headerEnd > 60000 || ! $this->validQuotes(preg_replace('/^\xEF\xBB\xBF/', '', $headerRaw))) {
            throw new DomainException('doaj_csv_invalid_header');
        }
        $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0] ?? '');
        PostgresText::validate($headers);
        if (count($headers) > 100 || count(array_unique($headers)) !== count($headers)
            || ! in_array('Journal title', $headers, true)
            || (! in_array('Journal ISSN (print version)', $headers, true) && ! in_array('Journal EISSN (online version)', $headers, true))) {
            throw new DomainException('doaj_csv_invalid_header');
        }
        $records = [];
        while (count($records) < $limit && ! feof($stream)) {
            $start = ftell($stream);
            $values = fgetcsv($stream, 0, ',', '"', '');
            if ($values === false) {
                break;
            }
            $end = ftell($stream);
            fseek($stream, $start);
            $raw = fread($stream, $end - $start);
            fseek($stream, $end);
            $fingerprint = hash('sha256', $raw);
            // Unsafe bytes never reach JSONB or the run summary, but retain their hash identity.
            $error = null;
            try {
                PostgresText::validate($values);
            } catch (DomainException) {
                $error = 'postgres_incompatible_text';
            }
            if ($error !== null || strlen($raw) > 60000 || count($values) !== count($headers) || ! $this->validQuotes($raw)) {
                $records[] = ['id' => 'csv.'.$fingerprint, '_csv_error' => $error ?? 'doaj_csv_invalid_row', '_csv_fingerprint' => $fingerprint];

                continue;
            }
            $row = array_combine($headers, $values);
            $url = $row['URL in DOAJ'] ?? '';
            $id = preg_match('~^https://doaj\.org/toc/([0-9a-f]{32})$~D', $url, $match) ? $match[1]
                : 'csv.'.hash('sha256', ($row['Journal ISSN (print version)'] ?? '').'|'.($row['Journal EISSN (online version)'] ?? ''));
            $records[] = ['id' => $id, '_csv_row' => $row, '_csv_fingerprint' => $fingerprint];
        }

        return $records;
    }

    /** Strict RFC-style quoting; fgetcsv alone accepts unterminated/stray quotes. */
    private function validQuotes(string $raw): bool
    {
        $state = 'start';
        for ($i = 0, $length = strlen($raw); $i < $length; $i++) {
            $char = $raw[$i];
            if ($state === 'quoted') {
                if ($char === '"') {
                    if (($raw[$i + 1] ?? '') === '"') {
                        $i++;
                    } else {
                        $state = 'closed';
                    }
                }
            } elseif ($char === ',' || $char === "\r" || $char === "\n") {
                $state = 'start';
            } elseif ($char === '"' && $state === 'start') {
                $state = 'quoted';
            } elseif ($char === '"' || $state === 'closed') {
                return false;
            } else {
                $state = 'plain';
            }
        }

        return $state !== 'quoted';
    }
}

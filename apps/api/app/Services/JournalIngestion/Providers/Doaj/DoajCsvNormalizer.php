<?php

namespace App\Services\JournalIngestion\Providers\Doaj;

use DomainException;

/** Reverses the official DOAJ question export; never guesses a code or fee currency. */
class DoajCsvNormalizer
{
    public function normalize(array $row): array
    {
        $vocabulary = json_decode(file_get_contents(__DIR__.'/csv-vocabulary.json'), true, 16, JSON_THROW_ON_ERROR);
        $b = ['title' => $row['Journal title'] ?? null];
        foreach (['Journal ISSN (print version)' => 'pissn', 'Journal EISSN (online version)' => 'eissn'] as $column => $key) {
            if (($row[$column] ?? '') !== '') {
                $b[$key] = $row[$column];
            }
        }
        if (($row['Publisher'] ?? '') !== '') {
            $b['publisher']['name'] = $row['Publisher'];
            if (($row['Country of publisher'] ?? '') !== '') {
                $b['publisher']['country'] = $vocabulary['countries'][$row['Country of publisher']] ?? throw new DomainException('doaj_csv_unknown_country');
            }
        }
        if (($row['Journal URL'] ?? '') !== '') {
            $b['ref']['journal'] = $row['Journal URL'];
        }
        if (($row['Languages in which the journal accepts manuscripts'] ?? '') !== '') {
            $b['language'] = array_map(fn ($name) => $vocabulary['languages'][trim($name)] ?? throw new DomainException('doaj_csv_unknown_language'), explode(',', $row['Languages in which the journal accepts manuscripts']));
        }
        // CSV LCC Codes includes ancestors. Subjects holds selected full paths, not positional terms.
        // Resolve each path leaf against the official DOAJ LCC vocabulary and observed code set.
        if (($row['Subjects'] ?? '') !== '' && ($row['LCC Codes'] ?? '') !== '') {
            $codes = explode('|', $row['LCC Codes']);
            foreach (explode(' | ', $row['Subjects']) as $path) {
                $separator = strrpos($path, ': ');
                $term = trim($separator === false ? $path : substr($path, $separator + 2));
                $matches = array_filter($codes, fn ($code) => ($vocabulary['subjects'][$code] ?? null) === $term);
                if (count($matches) !== 1) {
                    throw new DomainException('doaj_csv_ambiguous_subject');
                }
                $b['subject'][] = ['scheme' => 'LCC', 'code' => array_values($matches)[0], 'term' => $term];
            }
        }
        foreach (['APC' => ['apc', 'has_apc'], 'Journal waiver policy (for developing country authors etc)' => ['waiver', 'has_waiver']] as $column => [$object, $flag]) {
            if (($row[$column] ?? '') !== '') {
                $b[$object][$flag] = match ($row[$column]) {
                    'Yes' => true, 'No' => false, default => throw new DomainException('doaj_csv_invalid_flag'),
                };
            }
        }
        if (($row['APC amount'] ?? '') !== '') {
            foreach (explode('; ', $row['APC amount']) as $maximum) {
                if (! preg_match('/^(0|[1-9][0-9]{0,9}) ([A-Z]{3})$/D', $maximum, $match)) {
                    throw new DomainException('doaj_csv_invalid_apc');
                }
                $b['apc']['max'][] = ['price' => (int) $match[1], 'currency' => $match[2]];
            }
        }

        return $b;
    }
}

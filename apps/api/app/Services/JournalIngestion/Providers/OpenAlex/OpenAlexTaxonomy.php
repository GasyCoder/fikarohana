<?php

namespace App\Services\JournalIngestion\Providers\OpenAlex;

use DomainException;

/** Explicit source topics only; ancestor edges come from the reported topic path. */
class OpenAlexTaxonomy
{
    public function normalize(mixed $topics): array
    {
        if ($topics === null) {
            return [];
        }
        if (! is_array($topics) || ! array_is_list($topics) || count($topics) > 30) {
            throw new DomainException('openalex_invalid_taxonomy');
        }
        $nodes = [];
        foreach ($topics as $index => $topic) {
            if (! is_array($topic)) {
                throw new DomainException('openalex_invalid_taxonomy');
            }
            $parent = null;
            foreach (['domain', 'field', 'subfield', 'topic'] as $level) {
                $entry = $level === 'topic' ? $topic : ($topic[$level] ?? null);
                if ($entry === null) {
                    $parent = null;

                    continue;
                }
                $pattern = match ($level) {
                    'topic' => '~^https://openalex\.org/T[1-9][0-9]{0,19}$~D',
                    'domain' => '~^https://openalex\.org/domains/[1-4]$~D',
                    'field' => '~^https://openalex\.org/fields/(?:1[1-9]|2[0-9]|3[0-6])$~D',
                    'subfield' => '~^https://openalex\.org/subfields/[1-9][0-9]{3}$~D',
                };
                if (! is_array($entry) || ! is_string($entry['id'] ?? null) || ! preg_match($pattern, $entry['id'])
                    || ! is_string($entry['display_name'] ?? null) || trim($entry['display_name']) === ''
                    || mb_strlen($entry['display_name']) > 255 || (isset($entry['level']) && $entry['level'] !== $level)) {
                    throw new DomainException('openalex_invalid_taxonomy');
                }
                $node = ['provider' => 'openalex', 'external_id' => $entry['id'], 'level' => $level,
                    'name' => trim($entry['display_name']), 'parent_external_id' => $parent];
                if (isset($nodes[$entry['id']])) {
                    if (array_diff_assoc($node, array_diff_key($nodes[$entry['id']], ['locator' => true])) !== []) {
                        throw new DomainException('openalex_taxonomy_conflict');
                    }
                } else {
                    $node['locator'] = 'topics.'.$index.($level === 'topic' ? '' : '.'.$level).'.display_name';
                    $nodes[$entry['id']] = $node;
                }
                $parent = $entry['id'];
            }
        }

        return array_values($nodes);
    }
}

<?php

namespace App\Services\JournalDiscovery;

use Illuminate\Database\Eloquent\Builder;
use InvalidArgumentException;

class JournalRelevanceScorer
{
    public function settings(): array
    {
        $c = config('journal_recommendation');
        foreach (['taxonomy', 'lexical'] as $key) {
            if (! is_numeric($c['weights'][$key] ?? null) || $c['weights'][$key] <= 0) {
                throw new InvalidArgumentException('Invalid recommendation weights.');
            }
        }
        if ($c['taxonomy_decay'] <= 0 || $c['taxonomy_decay'] >= 1 || $c['taxonomy_max_distance'] < 1 || $c['taxonomy_max_distance'] > 3
            || $c['max_tokens'] < 1 || $c['max_tokens'] > 24 || $c['candidate_limit'] < 10 || $c['candidate_limit'] > 200) {
            throw new InvalidArgumentException('Invalid recommendation bounds.');
        }

        return $c;
    }

    public function tokens(array $input): array
    {
        $text = mb_strtolower(implode(' ', [$input['title'], $input['abstract'] ?? '', ...($input['keywords'] ?? [])]));
        $tokens = preg_split('/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY);
        $tokens = array_values(array_unique(array_filter($tokens, fn ($t) => mb_strlen($t) >= 2)));

        return array_slice($tokens, 0, $this->settings()['max_tokens']);
    }

    public function expressions(Builder $base, array $input, array $tokens): array
    {
        $c = $this->settings();
        $parts = [];
        $bindings = [];
        foreach ($tokens as $token) {
            $q = clone $base;
            $q->whereHas('usableSelections.assertion', function ($q) use ($token) {
                $pattern = '%'.app(JournalDiscoveryQuery::class)->escape($token).'%';
                $q->whereNull('article_type_id')->whereNull('language_id')->where('availability', 'known')
                    ->where(fn ($q) => $q->where(fn ($q) => $q->where('field', 'title')->whereRaw('lower(text_value) LIKE ?', [$pattern]))
                        ->orWhere(fn ($q) => $q->where('field', 'subject')->whereHas('subjectLink.subject', fn ($q) => $q->whereRaw('lower(name) LIKE ?', [$pattern]))));
            })->select('journal_revisions.id');
            $parts[] = 'CASE WHEN journal_revisions.id IN ('.$q->toSql().') THEN 1.0 ELSE 0.0 END';
            array_push($bindings, ...$q->getBindings());
        }
        $lexical = $parts ? '('.implode(' + ', $parts).') / '.count($tokens) : '0.0';
        $taxonomy = '0.0';
        if ($input['subject_ids'] ?? []) {
            $cases = [];
            for ($distance = 0; $distance <= $c['taxonomy_max_distance']; $distance++) {
                $q = clone $base;
                $related = $this->relatedSql($input['subject_ids'], $distance);
                app(JournalDiscoveryQuery::class)->fact($q, 'subject', fn ($q) => $q->whereHas('subjectLink', fn ($q) => $q->whereRaw('subject_id IN ('.$related.')', [...$input['subject_ids'], ...$input['subject_ids']])));
                $q->select('journal_revisions.id');
                $cases[] = 'WHEN journal_revisions.id IN ('.$q->toSql().') THEN '.pow($c['taxonomy_decay'], $distance);
                array_push($bindings, ...$q->getBindings());
            }
            $taxonomy = 'CASE '.implode(' ', $cases).' ELSE 0.0 END';
        }

        return [$lexical, $taxonomy, $bindings];
    }

    public function relatedSql(array $ids, int $distance): string
    {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));

        return "WITH RECURSIVE ancestors AS (
            SELECT id,parent_id,0 AS distance FROM subjects WHERE id IN ($placeholders)
            UNION ALL SELECT s.id,s.parent_id,a.distance+1 FROM subjects s JOIN ancestors a ON s.id=a.parent_id WHERE a.distance < $distance
        ), descendants AS (
            SELECT id,parent_id,0 AS distance FROM subjects WHERE id IN ($placeholders)
            UNION ALL SELECT s.id,s.parent_id,d.distance+1 FROM subjects s JOIN descendants d ON s.parent_id=d.id WHERE d.distance < $distance
        ) SELECT id FROM ancestors UNION SELECT id FROM descendants";
    }
}

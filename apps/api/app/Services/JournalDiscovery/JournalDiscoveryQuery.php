<?php

namespace App\Services\JournalDiscovery;

use App\Models\CatalogRevision;
use App\Models\JournalRevision;
use Illuminate\Database\Eloquent\Builder;

class JournalDiscoveryQuery
{
    public function catalog(): ?CatalogRevision
    {
        return CatalogRevision::whereNotNull('published_at')->orderByDesc('version')->first();
    }

    public function query(?CatalogRevision $catalog, array $filters = []): Builder
    {
        $query = JournalRevision::query()->whereNotNull('published_at')
            ->whereHas('journal', fn ($q) => $q->where('active', true))
            ->whereIn('id', function ($q) use ($catalog) {
                $q->select('journal_revision_id')->from('catalog_revision_journals')
                    ->where('catalog_revision_id', $catalog?->id);
            });
        $this->fact($query, 'title', fn ($q) => $q->where('availability', 'known'));
        foreach (['language' => ['languageLink.language', 'languageLink'], 'article_type' => ['articleTypeLink.articleType', 'articleTypeLink']] as $key => [$vocabulary, $link]) {
            if (isset($filters[$key])) {
                $query->whereDoesntHave('usableSelections.assertion', function ($q) use ($key, $vocabulary, $link, $filters) {
                    $q->where('field', $key);
                    $this->possibleContext($q, $filters);
                    $q->whereHas($vocabulary, fn ($q) => $q->where('code', $filters[$key]))->whereHas($link, fn ($q) => $q->where('supported', false));
                });
                $this->fact($query, $key, fn ($q) => $q->whereHas($vocabulary, fn ($q) => $q->where('code', $filters[$key]))
                    ->whereHas($link, fn ($q) => $q->where('supported', true)), $filters);
            }
        }
        foreach ($filters['indexation_services'] ?? (isset($filters['indexation']) ? [$filters['indexation']] : []) as $code) {
            $query->whereDoesntHave('usableSelections.assertion', function ($q) use ($filters, $code) {
                $q->where('field', 'indexation');
                $this->possibleContext($q, $filters);
                $q->whereHas('indexation', fn ($q) => $q->where('supported', false)->whereHas('indexationService', fn ($q) => $q->where('code', $code)));
            });
            $this->fact($query, 'indexation', fn ($q) => $q->whereHas('indexation', fn ($q) => $q->where('supported', true)
                ->whereHas('indexationService', fn ($q) => $q->where('code', $code))), $filters);
        }
        if (isset($filters['subject'])) {
            $this->fact($query, 'subject', fn ($q) => $q->whereHas('subjectLink', fn ($q) => $q->where('subject_id', $filters['subject'])));
        }
        if (isset($filters['publisher'])) {
            $this->fact($query, 'publisher', fn ($q) => $q->where('publisher_id', $filters['publisher']));
        }
        if (($filters['no_apc'] ?? $filters['no_apc_only'] ?? false) === true) {
            $this->fact($query, 'fee', fn ($q) => $q->whereHas('fee', fn ($q) => $q->where('fee_type', 'apc'))
                ->where('availability', 'not_applicable'), $filters);
            $query->whereDoesntHave('usableSelections.assertion', function ($q) use ($filters) {
                $q->where('field', 'fee');
                $this->possibleContext($q, $filters);
                $q->where('availability', 'known')->whereHas('fee', fn ($q) => $q->where('fee_type', 'apc'));
            });
        } elseif (array_key_exists('no_apc', $filters) && $filters['no_apc'] === false) {
            $this->fact($query, 'fee', fn ($q) => $q->where('availability', 'known')->whereHas('fee', fn ($q) => $q->where('fee_type', 'apc')->where('amount', '>', 0)), $filters);
        }
        if (isset($filters['q'])) {
            $term = '%'.$this->escape(mb_strtolower($filters['q'])).'%';
            $query->whereHas('usableSelections.assertion', fn ($q) => $q->where(function ($q) use ($term) {
                $q->where(fn ($q) => $q->where('field', 'title')->whereRaw('lower(text_value) LIKE ?', [$term]))
                    ->orWhere(fn ($q) => $q->where('field', 'publisher')->whereHas('publisher', fn ($q) => $q->whereRaw('lower(name) LIKE ?', [$term])))
                    ->orWhere(fn ($q) => $q->where('field', 'subject')->whereHas('subjectLink.subject', fn ($q) => $q->whereRaw('lower(name) LIKE ?', [$term])))
                    ->orWhere(fn ($q) => $q->where('field', 'identifier')->whereHas('identifier', fn ($q) => $q->whereRaw('lower(normalized_value) LIKE ?', [$term])));
            })->whereNull('article_type_id')->whereNull('language_id')->where('availability', 'known'));
        }

        return $query;
    }

    public function fact(Builder $query, string $field, callable $constraint, array $context = []): void
    {
        $query->whereHas('usableSelections.assertion', function ($q) use ($field, $constraint, $context) {
            $q->where('field', $field);
            $this->context($q, $context);
            $constraint($q);
        });
    }

    public function context(Builder $q, array $context): void
    {
        foreach (['article_type' => ['article_type_id', 'articleType'], 'language' => ['language_id', 'language']] as $key => [$column, $relation]) {
            $q->where(function ($q) use ($context, $key, $column, $relation) {
                $q->whereNull($column);
                if (isset($context[$key])) {
                    $q->orWhereHas($relation, fn ($q) => $q->where('code', $context[$key]));
                }
            });
        }
    }

    private function possibleContext(Builder $q, array $context): void
    {
        // An unspecified context cannot disprove an explicit negative scoped claim.
        foreach (['article_type' => ['article_type_id', 'articleType'], 'language' => ['language_id', 'language']] as $key => [$column, $relation]) {
            if (isset($context[$key])) {
                $q->where(fn ($q) => $q->whereNull($column)->orWhereHas($relation, fn ($q) => $q->where('code', $context[$key])));
            }
        }
    }

    public function selections(JournalRevision $revision, array $context): array
    {
        return $revision->usableSelections->filter(function ($selection) use ($context) {
            $a = $selection->assertion;

            return ($a->article_type_id === null || ($context['article_type'] ?? null) === $a->articleType?->code)
                && ($a->language_id === null || ($context['language'] ?? null) === $a->language?->code);
        })->values()->all();
    }

    public function eager(Builder $query): Builder
    {
        return $query->with(['usableSelections.review', 'usableSelections.assertion.publisher',
            'usableSelections.assertion.identifier', 'usableSelections.assertion.subjectLink.subject',
            'usableSelections.assertion.languageLink.language', 'usableSelections.assertion.articleTypeLink.articleType',
            'usableSelections.assertion.indexation.indexationService', 'usableSelections.assertion.fee',
            'usableSelections.assertion.articleType', 'usableSelections.assertion.language',
            'usableSelections.assertion.sourceRecord:id,journal_source_id,source_url,retrieved_at']);
    }

    public function escape(string $text): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $text);
    }
}

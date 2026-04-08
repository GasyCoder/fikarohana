<?php

namespace App\Pipelines\ResourceFilters;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class SearchFilter
{
    public function __construct(private readonly array $filters) {}

    public function handle(Builder $query, Closure $next): Builder
    {
        $search = trim((string) ($this->filters['search'] ?? ''));
        $terms = $this->searchTerms($search);

        if ($terms === []) {
            return $next($query);
        }

        foreach ($terms as $term) {
            $asciiTerm = Str::ascii($term);

            $query->where(function (Builder $builder) use ($term, $asciiTerm): void {
                $this->applyTerm($builder, $term);

                if ($asciiTerm !== '' && $asciiTerm !== $term) {
                    $builder->orWhere(function (Builder $nested) use ($asciiTerm): void {
                        $this->applyTerm($nested, $asciiTerm);
                    });
                }
            });
        }

        return $next($query);
    }

    private function applyTerm(Builder $query, string $term): void
    {
        $query
            ->where('title', 'like', "%{$term}%")
            ->orWhere('author', 'like', "%{$term}%")
            ->orWhere('abstract', 'like', "%{$term}%")
            ->orWhere('domain', 'like', "%{$term}%")
            ->orWhere('institution', 'like', "%{$term}%")
            ->orWhere('source_name', 'like', "%{$term}%")
            ->orWhere('tags', 'like', "%{$term}%");
    }

    /**
     * @return array<int, string>
     */
    private function searchTerms(string $search): array
    {
        return collect(preg_split('/[\s,;:!?()\[\]\'"\/\\\\.-]+/u', Str::lower($search)) ?: [])
            ->map(fn (string $term) => trim($term))
            ->filter(fn (string $term) => mb_strlen($term) >= 3)
            ->reject(fn (string $term) => in_array($term, ['des', 'les', 'une', 'dans', 'avec', 'pour', 'sur', 'par', 'aux', 'and', 'the'], true))
            ->unique()
            ->values()
            ->all();
    }
}

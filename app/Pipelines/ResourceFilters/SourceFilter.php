<?php

namespace App\Pipelines\ResourceFilters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class SourceFilter
{
    public function __construct(private readonly array $filters) {}

    public function handle(Builder $query, Closure $next): Builder
    {
        $sources = collect($this->filters['sources'] ?? [])->filter()->values()->all();

        if ($sources !== []) {
            $query->whereIn('source_name', $sources);
        }

        return $next($query);
    }
}

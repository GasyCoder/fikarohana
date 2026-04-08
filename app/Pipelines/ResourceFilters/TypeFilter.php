<?php

namespace App\Pipelines\ResourceFilters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class TypeFilter
{
    public function __construct(private readonly array $filters) {}

    public function handle(Builder $query, Closure $next): Builder
    {
        $types = collect($this->filters['types'] ?? [])->filter()->values()->all();

        if ($types !== []) {
            $query->whereIn('type', $types);
        }

        return $next($query);
    }
}

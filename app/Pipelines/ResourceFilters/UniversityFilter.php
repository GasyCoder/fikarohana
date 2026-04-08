<?php

namespace App\Pipelines\ResourceFilters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class UniversityFilter
{
    public function __construct(private readonly array $filters) {}

    public function handle(Builder $query, Closure $next): Builder
    {
        $universities = collect($this->filters['universities'] ?? [])->filter()->values()->all();

        if ($universities !== []) {
            $query->whereIn('institution', $universities);
        }

        return $next($query);
    }
}

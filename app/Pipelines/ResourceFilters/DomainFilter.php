<?php

namespace App\Pipelines\ResourceFilters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class DomainFilter
{
    public function __construct(private readonly array $filters) {}

    public function handle(Builder $query, Closure $next): Builder
    {
        $domains = collect($this->filters['domains'] ?? [])->filter()->values()->all();

        if ($domains !== []) {
            $query->whereIn('domain', $domains);
        }

        return $next($query);
    }
}

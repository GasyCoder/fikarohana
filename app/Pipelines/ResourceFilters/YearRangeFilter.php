<?php

namespace App\Pipelines\ResourceFilters;

use Closure;
use Illuminate\Database\Eloquent\Builder;

class YearRangeFilter
{
    public function __construct(private readonly array $filters) {}

    public function handle(Builder $query, Closure $next): Builder
    {
        $fromYear = data_get($this->filters, 'years.from');
        $toYear = data_get($this->filters, 'years.to');

        if (is_numeric($fromYear)) {
            $query->where('year', '>=', (int) $fromYear);
        }

        if (is_numeric($toYear)) {
            $query->where('year', '<=', (int) $toYear);
        }

        return $next($query);
    }
}

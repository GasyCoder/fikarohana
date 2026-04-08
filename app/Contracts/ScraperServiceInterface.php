<?php

namespace App\Contracts;

interface ScraperServiceInterface
{
    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetch(string $query): array;
}

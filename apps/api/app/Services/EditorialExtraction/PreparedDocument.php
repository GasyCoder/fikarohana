<?php

namespace App\Services\EditorialExtraction;

final readonly class PreparedDocument
{
    public function __construct(public string $baseUrl, public array $units) {}
}

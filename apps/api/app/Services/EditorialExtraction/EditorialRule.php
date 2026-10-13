<?php

namespace App\Services\EditorialExtraction;

interface EditorialRule
{
    public function id(): string;

    public function field(): string;

    public function extract(EvidenceUnit $unit, PreparedDocument $document): array;
}

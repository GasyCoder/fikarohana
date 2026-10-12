<?php

namespace App\Services\JournalIngestion\Providers\Crossref;

use App\Services\JournalIngestion\ExistingJournalCandidates;

class CrossrefCandidates
{
    public function identifiers(string $issn): array
    {
        return app(ExistingJournalCandidates::class)->identifiers($issn);
    }
}

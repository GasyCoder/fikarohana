<?php

namespace App\Services\JournalIngestion;

use App\Models\JournalSource;

/** Transport-free mapping boundary. Future providers acquire approved data separately. */
interface ProviderAdapter
{
    public function key(): string;

    public function mappingVersion(): string;

    public function accepts(JournalSource $source): bool;

    /** Reject unsafe/unsupported input before retaining it in durable staging. */
    public function validatePayload(array $payload): void;

    /** Return the bounded canonical proposal understood by ProposalValidator. */
    public function normalize(array $payload): array;

    /** Translate canonical proposal paths into locators in the retained provider record. */
    public function sourceLocator(string $field): string;
}

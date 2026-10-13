<?php

namespace App\Services\EditorialExtraction;

final readonly class EvidenceUnit
{
    // Evidence text and local label (heading) are separate from contextual entries.
    // Heading text is explicitly mapped to article scope by RuleContext; qualifiers
    // and table caption/header entries retain their own kinds and source locators.
    public function __construct(public string $text, public string $locator, public string $heading = '', public ?string $href = null,
        public array $contexts = [], public bool $ambiguousContext = false) {}
}

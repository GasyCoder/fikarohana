<?php

namespace App\Services\JournalCatalog;

use App\Enums\JournalField;
use App\Enums\JournalSourceType;

class SourcePrecedence
{
    /** Preference for review, never an automatic conflict resolver or trust score. */
    public static function preferredTypes(JournalField $field): array
    {
        return match ($field) {
            JournalField::Indexation => [JournalSourceType::IndexRegistry],
            JournalField::Fee, JournalField::Language, JournalField::ArticleType,
            JournalField::Requirement, JournalField::Template, JournalField::SubmissionUrl,
            JournalField::Scope => [JournalSourceType::OfficialJournal, JournalSourceType::OfficialPublisher],
            default => [JournalSourceType::OfficialJournal, JournalSourceType::OfficialPublisher,
                JournalSourceType::Doaj, JournalSourceType::Crossref, JournalSourceType::Openalex],
        };
    }
}

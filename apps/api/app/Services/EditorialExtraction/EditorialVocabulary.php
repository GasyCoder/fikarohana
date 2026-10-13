<?php

namespace App\Services\EditorialExtraction;

final class EditorialVocabulary
{
    public const LANGUAGES = ['english' => 'en', 'french' => 'fr', 'german' => 'de', 'spanish' => 'es', 'portuguese' => 'pt'];

    public const ARTICLES = ['original research' => 'research_article', 'original article' => 'research_article', 'original articles' => 'research_article',
        'research article' => 'research_article', 'review article' => 'review_article', 'short communication' => 'short_communication',
        'case report' => 'case_report', 'editorial' => 'editorial', 'review articles' => 'review_article'];

    public static function label(string $text): string
    {
        return strtolower(trim($text, " .\t\n\r"));
    }
}

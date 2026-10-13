<?php

namespace App\Services\EditorialExtraction;

final class RuleContext
{
    private const GENERAL = ['author guidelines', 'author instructions', 'instructions for authors', 'guidelines', 'requirements',
        'fees', 'publication fees', 'publication fee', 'apc', 'article processing charge', 'submission fee', 'page charge', 'color charge',
        'accepted manuscript languages', 'accepted publication languages', 'article types', 'accepted article types',
        'word limits', 'word counts', 'maximum word counts', 'maximum word count', 'article type',
        'abstract', 'manuscript', 'references', 'reference style', 'citation style', 'templates', 'manuscript template',
        'article template', 'submission', 'online submission'];

    public static function allows(EvidenceUnit $unit, bool $wordScope = false): bool
    {
        if ($unit->ambiguousContext) {
            return false;
        }
        $articles = [];
        foreach ($unit->contexts as $context) {
            $label = self::label($context['text']);
            // Qualifier prose is retained as a separate context, never reinterpreted as a field label.
            if ($context['kind'] === 'qualifier') {
                return false;
            }
            $article = EditorialVocabulary::ARTICLES[$label] ?? null;
            if ($article !== null) {
                if (! $wordScope) {
                    return false;
                } // Other rule mappings do not safely represent article conditions.
                $articles[$article] = true;
            } elseif (! in_array($label, self::GENERAL, true)) {
                return false; // Unknown, examples, conditional, minimum/exact or compound scope: abstain.
            }
        }

        return count($articles) <= 1;
    }

    public static function article(EvidenceUnit $unit): ?string
    {
        foreach ($unit->contexts as $context) {
            $article = EditorialVocabulary::ARTICLES[self::label($context['text'])] ?? null;
            if ($article !== null) {
                return $article;
            }
        }

        return null;
    }

    public static function hasMaximumCaption(EvidenceUnit $unit): bool
    {
        foreach ($unit->contexts as $context) {
            if (in_array($context['kind'], ['caption', 'table_header'], true)
                && in_array(self::label($context['text']), ['maximum word counts', 'maximum word count'], true)) {
                return true;
            }
        }

        return false;
    }

    public static function labeledText(EvidenceUnit $unit, array $labels): string
    {
        return in_array(self::label($unit->heading), $labels, true) ? $unit->heading.': '.$unit->text : $unit->text;
    }

    private static function label(string $text): string
    {
        return strtolower(trim($text, " .:\t\n\r"));
    }
}

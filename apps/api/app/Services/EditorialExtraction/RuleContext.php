<?php

namespace App\Services\EditorialExtraction;

final class RuleContext
{
    private const GENERAL = ['author guidelines', 'author instructions', 'instructions for authors', 'guidelines', 'requirements', 'submission guidelines', 'style and format', 'language', 'parts of a submission', 'cost and sustainability model',
        'fees', 'publication fees', 'publication fee', 'apc', 'article processing charge', 'submission fee', 'page charge', 'color charge',
        'accepted manuscript languages', 'accepted publication languages', 'article types', 'accepted article types',
        'word limits', 'word counts', 'maximum word counts', 'maximum word count', 'article type',
        'abstract', 'manuscript', 'references', 'reference style', 'citation style', 'templates', 'manuscript template',
        'article template', 'submission', 'online submission'];

    public static function allows(EvidenceUnit $unit, bool $wordScope = false, bool $selfContained = false): bool
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
                // Unknown is not affirmative scope evidence. Only an independently
                // complete grammar can tolerate a neutral heading; never a fragment.
                if (! $selfContained || $context['kind'] !== 'heading' || self::unsafe($label)) {
                    return false;
                }
            }
        }

        return count($articles) <= 1;
    }

    public static function unsafe(string $text): bool
    {
        return (bool) preg_match('/\b(?:examples?|illustrations?|hypothetical|sample|optional|recommended|recommendations?|conditional|conditions?|exceptions?|only|invited|eligible|waiv\w*|discount\w*|promot\w*|temporary|obsolete|previously|disabled|minimum|exact|range|special|supplement\w*|subject areas?|domains?|research articles?|review articles?|systematic reviews?|letters?|correspondence|case reports?|short communications?)\b|^(?:for|if|when|unless|except|during)\b|\b(?:exemples?|hypoth[ée]tique|facultatif|condition\w*|uniquement|invit[ée]\w*)\b/iu', $text);
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
        $label = strtolower(trim($text, " .:\t\n\r"));

        return match ($label) {
            'the abstract should', 'abstract should' => 'abstract',
            'the manuscript should', 'manuscript should' => 'manuscript',
            default => $label,
        };
    }
}

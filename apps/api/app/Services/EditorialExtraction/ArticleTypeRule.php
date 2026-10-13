<?php

namespace App\Services\EditorialExtraction;

class ArticleTypeRule implements EditorialRule
{
    public function id(): string
    {
        return 'explicit_article_types';
    }

    public function field(): string
    {
        return 'article_type';
    }

    public function extract(EvidenceUnit $unit, PreparedDocument $document): array
    {
        if ($unit->href !== null || ! RuleContext::allows($unit)) {
            return [];
        }
        $text = RuleContext::labeledText($unit, ['article types', 'accepted article types']);
        if (! preg_match('/^(?:Accepted article types|Article types):\s*([A-Za-z ,;]+)\.?$/iD', $text, $m)) {
            return [];
        }
        $proposals = [];
        foreach (preg_split('/\s*(?:,|;|\band\b)\s*/i', $m[1]) as $label) {
            $code = EditorialVocabulary::ARTICLES[EditorialVocabulary::label($label)] ?? null;
            if ($code !== null) {
                $proposals[$code] = ['code' => $code, 'label' => trim($label), 'supported' => true];
            }
        }

        return array_values($proposals);
    }
}

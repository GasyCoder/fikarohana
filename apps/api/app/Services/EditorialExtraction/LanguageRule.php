<?php

namespace App\Services\EditorialExtraction;

class LanguageRule implements EditorialRule
{
    public function id(): string
    {
        return 'explicit_languages';
    }

    public function field(): string
    {
        return 'language';
    }

    public function extract(EvidenceUnit $unit, PreparedDocument $document): array
    {
        if ($unit->href !== null || ! RuleContext::allows($unit)) {
            return [];
        }
        $text = RuleContext::labeledText($unit, ['accepted manuscript languages', 'accepted publication languages']);
        if (! preg_match('/^(?:Accepted (?:manuscript|publication) languages:\s*|Manuscripts must be submitted in\s+|Articles are accepted in\s+)([A-Za-z ,;]+)\.?$/iD', $text, $m)) {
            return [];
        }
        $proposals = [];
        foreach (preg_split('/\s*(?:,|;|\band\b)\s*/i', $m[1]) as $label) {
            $code = EditorialVocabulary::LANGUAGES[EditorialVocabulary::label($label)] ?? null;
            if ($code === null) {
                return [];
            }
            $proposals[$code] = ['code' => $code, 'label' => ucfirst(strtolower(trim($label))), 'supported' => true];
        }

        return array_values($proposals);
    }
}

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
        $selfContained = (bool) preg_match('/^(?:Accepted (?:manuscript|publication) languages:|Manuscripts must be submitted in\b|Articles are accepted in\b|Langues\s*:)/i', $unit->text);
        if ($unit->href !== null || ! RuleContext::allows($unit, false, $selfContained)) {
            return [];
        }
        // A closed French eligibility construction explicitly exempts English from
        // the stated non-English translation dependency. Keep the entire unit as
        // evidence; never emit the conditioned languages as plain memberships.
        if (preg_match('/^Langues\s*:\s*[\p{L}0-9 .&-]{1,100} accepte des articles en ([\p{L} ,]+)\s*;\s*si l[’\x27]article n[’\x27]est pas en anglais, une traduction anglaise du titre et du résumé doit être fournie\.(.*)$/iuD', $unit->text, $french)) {
            if (preg_match('/\b(?:seulement|certains|uniquement|sauf|invit[ée]\w*|optionnel|recommand\w*)\b/iu', $unit->text)) {
                return [];
            }
            $languages = preg_split('/\s*(?:,|\bet\b)\s*/u', mb_strtolower(trim($french[1])));
            if (! in_array('anglais', $languages, true) || array_diff($languages, ['anglais', 'français', 'allemand', 'espagnol', 'portugais']) !== []) {
                return [];
            }
            // Trailing formatting notes must have an unrelated technical subject.
            // Unknown prose or a further eligibility condition fails closed.
            $tail = trim($french[2]);
            foreach ($tail === '' ? [] : preg_split('/(?<=\.)\s+/u', $tail) as $note) {
                if (! preg_match('/^Le (?:paquet [A-Za-z0-9_-]+|mélange de langues)\b/u', $note)
                    || preg_match('/\b(?:si|sauf|uniquement|seulement|anglais|français|allemand|accept\w*|refus\w*|interdit\w*|obligatoire|soumission|traduction|article[s]? doivent)\b/iu', $note)) {
                    return [];
                }
            }

            return [['code' => 'en', 'label' => 'English', 'supported' => true]];
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

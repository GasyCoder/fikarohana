<?php

namespace App\Services\EditorialExtraction;

class ReferenceStyleRule implements EditorialRule
{
    public function id(): string
    {
        return 'explicit_reference_style';
    }

    public function field(): string
    {
        return 'requirement';
    }

    public function extract(EvidenceUnit $unit, PreparedDocument $document): array
    {
        if ($unit->href !== null || ! RuleContext::allows($unit)
            || preg_match('/\b(?:only|some|invited|eligible|during|promotional|except|unless)\b/i', $unit->text)) {
            return [];
        }
        $text = RuleContext::labeledText($unit, ['reference style', 'citation style']);
        if (! preg_match('/^(?:Reference|Citation) style:\s*(Vancouver|APA(?: 7th edition)?|AMA|Chicago)\.?$/iD', $text, $m)
            && ! preg_match('/^[A-Za-z][A-Za-z -]{0,80} uses [“\"]?(Vancouver|APA(?: 7th edition)?|AMA|Chicago)[”\"]? style(?:, as outlined in the ICMJE sample references)?\.?$/iuD', $unit->text, $m)) {
            return [];
        }
        $style = match (strtolower($m[1])) {
            'apa' => 'APA', 'apa 7th edition' => 'APA 7th edition', 'ama' => 'AMA', default => ucfirst(strtolower($m[1]))
        };

        return [['requirement_type' => 'reference_style', 'operator' => 'text', 'value_text' => $style, 'scope' => 'references']];
    }
}

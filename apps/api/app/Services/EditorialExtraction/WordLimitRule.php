<?php

namespace App\Services\EditorialExtraction;

class WordLimitRule implements EditorialRule
{
    public function id(): string
    {
        return 'explicit_word_limit';
    }

    public function field(): string
    {
        return 'requirement';
    }

    public function extract(EvidenceUnit $unit, PreparedDocument $document): array
    {
        if ($unit->href !== null || ! RuleContext::allows($unit, true)) {
            return [];
        }
        $scope = null;
        $number = null;
        $label = '(Abstract|Manuscript|Original Articles?|Original Research|Review Articles?|Short Communication|Case Report|Editorial)';
        $count = '([0-9]{1,3}(?:,[0-9]{3})|[0-9]{1,6})';
        $candidates = array_unique([$unit->text, RuleContext::labeledText($unit, ['abstract', 'manuscript', ...array_keys(EditorialVocabulary::ARTICLES)])]);
        foreach ($candidates as $text) {
            if (preg_match('/^'.$label.':\s*(?:maximum|max|up to|no more than)\s+'.$count.' words\.?$/iD', $text, $m)
                || preg_match('/^'.$label.'\s+(?:should not exceed|must not exceed|must contain no more than)\s+'.$count.' words\.?$/iD', $text, $m)
                || (RuleContext::hasMaximumCaption($unit) && preg_match('/^'.$label.':\s*'.$count.' words\.?$/iD', $text, $m))) {
                $scope = strtolower($m[1]);
                $number = $m[2];
                break;
            }
        }
        if ($scope === null) {
            return [];
        }
        $section = $scope === 'abstract' ? 'abstract' : 'manuscript';
        $declaredArticle = in_array($scope, ['abstract', 'manuscript'], true) ? null : EditorialVocabulary::ARTICLES[$scope];
        $inheritedArticle = RuleContext::article($unit);
        if ($declaredArticle !== null && $inheritedArticle !== null && $declaredArticle !== $inheritedArticle) {
            return [];
        }
        foreach ($unit->contexts as $context) {
            $contextLabel = strtolower(trim($context['text'], " .:\t\n\r"));
            if (in_array($contextLabel, ['abstract', 'manuscript'], true) && $contextLabel !== $section) {
                return [];
            }
        }

        return [['requirement_type' => $section === 'abstract' ? 'abstract_words' : 'word_count',
            'operator' => 'max', 'value_number' => (int) str_replace(',', '', $number), 'scope' => $section,
            'article_code' => $declaredArticle ?? $inheritedArticle, 'notes' => 'Words; inclusion of references/tables unspecified.']];
    }
}

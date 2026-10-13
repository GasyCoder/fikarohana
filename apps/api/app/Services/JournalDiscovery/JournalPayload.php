<?php

namespace App\Services\JournalDiscovery;

use App\Models\JournalRevision;

class JournalPayload
{
    public function make(JournalRevision $revision, array $context = [], bool $detail = false): array
    {
        $out = ['id' => $revision->journal_id, 'revision_id' => $revision->id, 'title' => null, 'publisher' => null,
            'identifiers' => [], 'subjects' => [], 'languages' => [], 'article_types' => [], 'indexations' => [],
            'apc' => ['status' => 'unknown', 'amount' => null, 'currency' => null], 'fees' => []];
        $provenance = [];
        $apcValues = [];
        foreach (app(JournalDiscoveryQuery::class)->selections($revision, $context) as $selection) {
            $a = $selection->assertion;
            $field = $a->field->value;
            if ($field === 'title') {
                $out['title'] = $a->text_value;
            }
            if ($field === 'publisher' && $a->publisher) {
                $out['publisher'] = ['id' => $a->publisher->id, 'name' => $a->publisher->name];
            }
            if ($field === 'identifier' && $a->identifier) {
                $out['identifiers'][] = ['type' => $a->identifier->type, 'value' => $a->identifier->normalized_value];
            }
            if ($field === 'subject' && $a->subjectLink) {
                $s = $a->subjectLink->subject;
                $out['subjects'][] = ['id' => $s->id, 'name' => $s->name, 'parent_id' => $s->parent_id, 'taxonomy_level' => $s->taxonomy_level];
            }
            foreach (['language' => ['languageLink', 'language', 'languages'], 'article_type' => ['articleTypeLink', 'articleType', 'article_types'], 'indexation' => ['indexation', 'indexationService', 'indexations']] as $f => [$link, $vocab, $target]) {
                if ($field === $f && $a->$link) {
                    $value = $a->$link->$vocab;
                    $out[$target][] = ['code' => $value->code, 'name' => $value->name, 'supported' => $a->$link->supported];
                }
            }
            if ($field === 'fee' && $a->fee) {
                $fee = $a->fee;
                $item = ['fee_type' => $fee->fee_type->value, 'availability' => $a->availability->value,
                    'amount' => $fee->amount, 'currency' => $fee->currency, 'basis' => $fee->basis,
                    'applicability' => $fee->applicability, 'article_type' => $a->articleType?->code, 'language' => $a->language?->code];
                $out['fees'][] = $item;
                if ($fee->fee_type->value === 'apc') {
                    $apcValues[] = ['status' => $a->availability->value === 'not_applicable' ? 'no_apc' : ($a->availability->value === 'known' ? 'known' : 'unknown'), 'amount' => $fee->amount, 'currency' => $fee->currency];
                }
            }
            if ($detail && in_array($field, ['title', 'publisher', 'identifier', 'subject', 'language', 'article_type', 'indexation', 'fee'], true)) {
                $provenance[] = ['assertion_id' => $a->id, 'field' => $field, 'source_url' => $a->sourceRecord->source_url,
                    'retrieved_at' => $a->sourceRecord->retrieved_at?->toIso8601String(),
                    'verified_at' => $selection->review->verified_at?->toIso8601String()];
            }
        }
        if (count($apcValues) === 1) {
            $out['apc'] = $apcValues[0];
        }
        if (count($apcValues) > 1) {
            $out['apc'] = ['status' => 'context_dependent', 'amount' => null, 'currency' => null];
        }
        usort($provenance, fn ($a, $b) => strcmp($a['assertion_id'], $b['assertion_id']));
        foreach (['identifiers', 'subjects', 'languages', 'article_types', 'indexations', 'fees'] as $key) {
            usort($out[$key], fn ($a, $b) => strcmp(json_encode($a), json_encode($b)));
        }
        if ($detail) {
            $out['provenance'] = $provenance;
        }

        return $out;
    }
}

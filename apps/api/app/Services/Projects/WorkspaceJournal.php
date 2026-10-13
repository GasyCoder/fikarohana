<?php

namespace App\Services\Projects;

use App\Models\ResearchProject;
use App\Services\JournalDiscovery\JournalDiscoveryQuery;
use App\Services\JournalDiscovery\JournalPayload;

class WorkspaceJournal
{
    public function make(ResearchProject $project): ?array
    {
        if (! $project->selected_journal_id) {
            return null;
        }
        $query = app(JournalDiscoveryQuery::class);
        $revision = $query->eager($query->query($query->catalog()))->where('journal_id', $project->selected_journal_id)->first();
        if (! $revision) {
            return null;
        }
        $revision->load(['usableSelections.assertion.requirement', 'usableSelections.assertion.template']);
        $context = array_filter(['language' => $project->language, 'article_type' => $project->article_type], fn ($v) => $v !== null);
        $out = app(JournalPayload::class)->make($revision, $context, true);
        $out['provenance'] = array_map(fn ($p) => array_diff_key($p, ['assertion_id' => true]), $out['provenance']);
        $out['requirements'] = [];
        $out['templates'] = [];
        foreach ($query->selections($revision, $context) as $selection) {
            $a = $selection->assertion;
            $scope = ['article_type' => $a->articleType?->code, 'language' => $a->language?->code];
            if ($a->requirement) {
                $r = $a->requirement;
                $out['requirements'][] = ['type' => $r->requirement_type->value, 'availability' => $a->availability->value, 'operator' => $r->operator, 'value_number' => $r->value_number, 'value_text' => $r->value_text, 'value_items' => $r->value_items, 'notes' => $r->notes] + $scope;
            }
            if ($a->template) {
                $t = $a->template;
                $out['templates'][] = ['format' => $t->format->value, 'label' => $t->label, 'official_url' => $this->safeUrl($t->official_url)] + $scope;
            }
            if ($a->requirement || $a->template) {
                $out['provenance'][] = ['field' => $a->field->value, 'source_url' => $a->sourceRecord->source_url, 'retrieved_at' => $a->sourceRecord->retrieved_at?->toIso8601String(), 'verified_at' => $selection->review->verified_at?->toIso8601String()];
            }
        }
        foreach ($out['provenance'] as &$p) {
            $p['source_url'] = $this->safeUrl($p['source_url']);
        }

        return $out;
    }

    private function safeUrl(?string $url): ?string
    {
        $parts = $url ? parse_url($url) : false;

        return $parts && in_array(strtolower($parts['scheme'] ?? ''), ['http', 'https'], true) && isset($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass']) ? $url : null;
    }
}

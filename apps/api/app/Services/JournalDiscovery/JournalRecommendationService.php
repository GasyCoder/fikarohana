<?php

namespace App\Services\JournalDiscovery;

use App\Models\JournalRevision;
use App\Models\Subject;
use Illuminate\Support\Facades\DB;

class JournalRecommendationService
{
    public function recommend(array $input): array
    {
        $discovery = app(JournalDiscoveryQuery::class);
        $scorer = app(JournalRelevanceScorer::class);
        $catalog = $discovery->catalog();
        $base = $discovery->query($catalog, $input);
        $total = (clone $base)->count();
        $limit = $input['limit'] ?? 5;
        $tokens = $scorer->tokens($input);
        $c = $scorer->settings();
        [$lex, $tax, $bindings] = $scorer->expressions($base, $input, $tokens);
        $activeTax = ! empty($input['subject_ids']);
        $activeLex = $tokens !== [];
        $weight = ($activeTax ? $c['weights']['taxonomy'] : 0) + ($activeLex ? $c['weights']['lexical'] : 0);
        $expression = $weight ? '100.0 * (taxonomy * '.($activeTax ? $c['weights']['taxonomy'] : 0).' + lexical * '.($activeLex ? $c['weights']['lexical'] : 0).') / '.$weight : '0.0';
        $scored = (clone $base)->select(['journal_revisions.id', 'journal_revisions.journal_id'])->selectRaw("$lex AS lexical, $tax AS taxonomy", $bindings);
        $ranked = DB::query()->fromSub($scored, 'scores')->select('*')->selectRaw($expression.' AS relevance_score')
            ->orderByDesc('relevance_score')->orderBy('journal_id')->limit($c['candidate_limit'])->get();
        $chosen = $ranked->take($limit);
        $revisions = $discovery->eager(JournalRevision::whereIn('id', $chosen->pluck('id')))->get()->keyBy('id');
        $requested = Subject::whereIn('id', $input['subject_ids'] ?? [])->get()->keyBy('id');
        $data = [];
        foreach ($chosen as $row) {
            $journal = app(JournalPayload::class)->make($revisions[$row->id], $input);
            $reasons = [];
            $global = app(JournalPayload::class)->make($revisions[$row->id]);
            $text = mb_strtolower($global['title'].' '.implode(' ', array_column($global['subjects'], 'name')));
            foreach ($tokens as $token) {
                if (str_contains($text, $token)) {
                    $reasons[] = ['type' => 'keyword_match', 'label' => $token];
                }
            }
            // One bounded query per distance for the whole requested taxonomy, never per result.
            $journal['relevance_score'] = round((float) $row->relevance_score, 2);
            $journal['score_components'] = ['taxonomy' => $activeTax ? round(100 * (float) $row->taxonomy, 2) : null,
                'lexical' => $activeLex ? round(100 * (float) $row->lexical, 2) : null];
            $journal['reasons'] = $reasons;
            $data[] = $journal;
        }
        if ($activeTax && $data !== []) {
            $distances = [];
            $returnedSubjects = array_values(array_unique(array_merge(...array_map(fn ($j) => array_column($j['subjects'], 'id'), $data))));
            for ($d = 0; $d <= $c['taxonomy_max_distance']; $d++) {
                $ids = $returnedSubjects === [] ? [] : DB::select('SELECT id FROM subjects WHERE id IN ('.implode(',', array_fill(0, count($returnedSubjects), '?')).') AND id IN ('.$scorer->relatedSql(array_keys($requested->all()), $d).')', [...$returnedSubjects, ...array_keys($requested->all()), ...array_keys($requested->all())]);
                foreach ($ids as $id) {
                    $distances[$id->id] ??= $d;
                }
            }
            foreach ($data as &$journal) {
                $globalSubjects = app(JournalPayload::class)->make($revisions[$chosen->firstWhere('journal_id', $journal['id'])->id])['subjects'];
                $matches = array_filter($globalSubjects, fn ($s) => isset($distances[$s['id']]));
                if ($matches) {
                    $best = min(array_map(fn ($s) => $distances[$s['id']], $matches));
                    foreach ($matches as $s) {
                        if ($distances[$s['id']] === $best) {
                            $journal['reasons'][] = ['type' => $best === 0 ? 'subject_match' : 'taxonomy_match', 'label' => $s['name'], 'subject_id' => $s['id'], 'distance' => $best];
                        }
                    }
                }
            }
            unset($journal);
        }

        return ['data' => $data, 'meta' => ['requested_limit' => $limit, 'candidates_after_filters' => $total,
            'returned' => count($data), 'scored_candidates_loaded' => $ranked->count(), 'catalog_revision_id' => $catalog?->id,
            'algorithm_version' => $c['version'], 'retrieval_mode' => 'deterministic_metadata',
            'score_semantics' => 'metadata_relevance_only']];
    }
}

<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Projects\ProjectRequest;
use App\Models\ResearchProject;
use App\Services\JournalDiscovery\JournalDiscoveryQuery;
use App\Services\Projects\WorkspaceJournal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class ResearchProjectController extends Controller
{
    private function payload(ResearchProject $project): array
    {
        return ['id' => $project->id, 'title' => $project->title, 'abstract' => $project->abstract, 'keywords' => $project->keywords, 'subject_ids' => $project->subject_ids, 'language' => $project->language, 'article_type' => $project->article_type, 'no_apc_only' => $project->no_apc_only, 'indexation_services' => $project->indexation_services, 'selected_journal_id' => $project->selected_journal_id, 'journal_selected_at' => $project->journal_selected_at?->toIso8601String(), 'created_at' => $project->created_at->toIso8601String(), 'updated_at' => $project->updated_at->toIso8601String(), 'journal' => app(WorkspaceJournal::class)->make($project)];
    }

    private function transaction(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            if (DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            return $callback();
        });
    }

    public function index(Request $request): mixed
    {
        $input = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'per_page' => ['sometimes', 'integer', 'min:1', 'max:50']]);

        return $this->transaction(function () use ($request, $input) {
            $page = ResearchProject::where('user_id', $request->user()->id)->orderByDesc('updated_at')->orderBy('id')->paginate($input['per_page'] ?? 20);

            return response()->json(['data' => $page->getCollection()->map(fn ($p) => $this->payload($p))->all(), 'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()]]);
        });
    }

    public function store(ProjectRequest $request): mixed
    {
        return $this->transaction(function () use ($request) {
            $project = new ResearchProject($request->validated() + ['keywords' => [], 'subject_ids' => []]);
            $project->user_id = $request->user()->id;
            $project->save();

            return response()->json(['data' => $this->payload($project)], 201);
        });
    }

    public function show(ResearchProject $project): mixed
    {
        Gate::authorize('view', $project);

        return $this->transaction(fn () => response()->json(['data' => $this->payload($project)]));
    }

    public function update(ProjectRequest $request, ResearchProject $project): mixed
    {
        Gate::authorize('update', $project);

        return $this->transaction(function () use ($request, $project) {
            $project->update($request->validated());

            return response()->json(['data' => $this->payload($project)]);
        });
    }

    public function destroy(ResearchProject $project): mixed
    {
        Gate::authorize('delete', $project);
        $project->delete();

        return response()->noContent();
    }

    public function selectJournal(Request $request, ResearchProject $project): mixed
    {
        Gate::authorize('update', $project);
        $input = $request->validate(['journal_id' => ['required', 'uuid']]);

        return $this->transaction(function () use ($input, $project) {
            $query = app(JournalDiscoveryQuery::class);
            if (! $query->query($query->catalog())->where('journal_id', $input['journal_id'])->exists()) {
                throw ValidationException::withMessages(['journal_id' => ['Select a currently available published journal.']]);
            }
            $project->selected_journal_id = $input['journal_id'];
            $project->journal_selected_at = now();
            $project->save();

            return response()->json(['data' => $this->payload($project)]);
        });
    }
}

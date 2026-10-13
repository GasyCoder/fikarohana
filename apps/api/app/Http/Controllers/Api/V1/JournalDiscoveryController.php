<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\JournalDiscovery\DiscoveryRequest;
use App\Http\Requests\JournalDiscovery\RecommendationRequest;
use App\Services\JournalDiscovery\JournalDiscoveryQuery;
use App\Services\JournalDiscovery\JournalPayload;
use App\Services\JournalDiscovery\JournalRecommendationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class JournalDiscoveryController extends Controller
{
    private function read(callable $callback): mixed
    {
        return DB::transaction(function () use ($callback) {
            // Requests run on one immutable catalog snapshot; no manuscript input persists.
            if (DB::transactionLevel() === 1) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            }

            return $callback();
        });
    }

    public function index(DiscoveryRequest $request, JournalDiscoveryQuery $query, JournalPayload $payload): mixed
    {
        return $this->read(function () use ($request, $query, $payload) {
            $filters = $request->validated();
            $catalog = $query->catalog();
            $page = $query->eager($query->query($catalog, $filters))->orderBy('journal_id')->paginate($filters['per_page'] ?? 20);

            return response()->json(['data' => $page->getCollection()->map(fn ($r) => $payload->make($r, $filters))->all(),
                'meta' => ['current_page' => $page->currentPage(), 'per_page' => $page->perPage(), 'total' => $page->total(),
                    'last_page' => $page->lastPage(), 'catalog_revision_id' => $catalog?->id]]);
        });
    }

    public function show(string $journal, JournalDiscoveryQuery $query, JournalPayload $payload): mixed
    {
        return $this->read(function () use ($journal, $query, $payload) {
            abort_unless(Str::isUuid($journal), 404);
            $revision = $query->eager($query->query($query->catalog()))->where('journal_id', $journal)->firstOrFail();

            return response()->json(['data' => $payload->make($revision, detail: true)]);
        });
    }

    public function recommend(RecommendationRequest $request, JournalRecommendationService $service): mixed
    {
        return $this->read(fn () => response()->json($service->recommend($request->validated())));
    }
}

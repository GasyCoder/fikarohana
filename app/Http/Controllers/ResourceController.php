<?php

namespace App\Http\Controllers;

use App\Models\Resource;
use App\Pipelines\ResourceFilters\DomainFilter;
use App\Pipelines\ResourceFilters\SearchFilter;
use App\Pipelines\ResourceFilters\SourceFilter;
use App\Pipelines\ResourceFilters\TypeFilter;
use App\Pipelines\ResourceFilters\UniversityFilter;
use App\Pipelines\ResourceFilters\YearRangeFilter;
use Illuminate\Http\Request;
use Illuminate\Pipeline\Pipeline;
use Inertia\Inertia;
use Inertia\Response;

class ResourceController extends Controller
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => trim((string) $request->string('search')),
            'domains' => collect($request->input('domains', []))->filter()->values()->all(),
            'universities' => collect($request->input('universities', []))->filter()->values()->all(),
            'types' => collect($request->input('types', []))->filter()->values()->all(),
            'sources' => collect($request->input('sources', []))->filter()->values()->all(),
            'years' => [
                'from' => $this->normalizeYear($request->input('years.from')),
                'to' => $this->normalizeYear($request->input('years.to')),
            ],
        ];

        $filteredQuery = app(Pipeline::class)
            ->send(Resource::query())
            ->through([
                new SearchFilter($filters),
                new DomainFilter($filters),
                new UniversityFilter($filters),
                new TypeFilter($filters),
                new SourceFilter($filters),
                new YearRangeFilter($filters),
            ])
            ->thenReturn();

        $resources = $filteredQuery
            ->orderByDesc('year')
            ->orderByDesc('id')
            ->paginate(9)
            ->withQueryString()
            ->through(fn (Resource $resource) => [
                'id' => $resource->id,
                'title' => $resource->title,
                'slug' => $resource->slug,
                'author' => $resource->author,
                'author_initials' => collect(explode(' ', trim($resource->author)))
                    ->filter()
                    ->take(2)
                    ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
                    ->implode(''),
                'year' => $resource->year,
                'abstract' => $resource->abstract,
                'source_name' => $resource->source_name,
                'source_url' => $resource->source_url,
                'source_host' => parse_url($resource->source_url, PHP_URL_HOST),
                'type' => $resource->type,
                'domain' => $resource->domain,
                'institution' => $resource->institution,
                'tags' => $resource->tags ?? [],
            ]);

        $yearBounds = Resource::query()
            ->selectRaw('MIN(year) as min_year, MAX(year) as max_year')
            ->first();

        return Inertia::render('Resources/Index', [
            'endpoint' => route('resources.index'),
            'filters' => $filters,
            'resources' => $resources,
            'options' => [
                'domains' => Resource::query()
                    ->selectRaw('domain, COUNT(*) as total')
                    ->whereNotNull('domain')
                    ->groupBy('domain')
                    ->orderByDesc('total')
                    ->orderBy('domain')
                    ->get(),
                'universities' => Resource::query()
                    ->selectRaw('institution, COUNT(*) as total')
                    ->whereNotNull('institution')
                    ->groupBy('institution')
                    ->orderByDesc('total')
                    ->orderBy('institution')
                    ->get(),
                'types' => Resource::query()
                    ->selectRaw('type, COUNT(*) as total')
                    ->groupBy('type')
                    ->orderBy('type')
                    ->get(),
                'sources' => Resource::query()
                    ->selectRaw('source_name, COUNT(*) as total')
                    ->groupBy('source_name')
                    ->orderByDesc('total')
                    ->orderBy('source_name')
                    ->get(),
                'years' => [
                    'min' => $yearBounds?->min_year,
                    'max' => $yearBounds?->max_year,
                ],
            ],
        ]);
    }

    private function normalizeYear(mixed $year): ?int
    {
        return is_numeric($year) ? (int) $year : null;
    }
}

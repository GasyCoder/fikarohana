<?php

namespace Tests\Feature;

use App\Models\Resource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResourceIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_renders_the_resource_library_page(): void
    {
        Resource::factory()->count(2)->create();

        $this->get('/bibliotheque')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Resources/Index')
                ->has('resources.data', 2)
                ->has('options.domains')
                ->has('options.sources'));
    }

    public function test_it_filters_resources_by_search_domain_year_and_source(): void
    {
        Resource::factory()->create([
            'title' => 'Agriculture durable a Madagascar',
            'author' => 'Rakoto',
            'year' => 2022,
            'domain' => 'Agronomie',
            'source_name' => 'HAL',
        ]);

        Resource::factory()->create([
            'title' => 'Histoire urbaine de Tamatave',
            'author' => 'Rabe',
            'year' => 2014,
            'domain' => 'Histoire',
            'source_name' => 'Cairn',
        ]);

        $this->get('/bibliotheque?search=Madagascar&domains[0]=Agronomie&sources[0]=HAL&years[from]=2020&years[to]=2024')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Resources/Index')
                ->where('filters.search', 'Madagascar')
                ->where('filters.domains.0', 'Agronomie')
                ->where('filters.sources.0', 'HAL')
                ->has('resources.data', 1)
                ->where('resources.data.0.title', 'Agriculture durable a Madagascar'));
    }
}

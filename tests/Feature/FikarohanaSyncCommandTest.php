<?php

namespace Tests\Feature;

use App\Contracts\ScraperServiceInterface;
use App\Jobs\SyncSourceResourcesJob;
use App\Models\Resource;
use App\Services\Scrapers\ScraperRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Mockery;
use Tests\TestCase;

class FikarohanaSyncCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_executes_sync_immediately_by_default(): void
    {
        $scraper = Mockery::mock(ScraperServiceInterface::class);
        $scraper->shouldReceive('fetch')
            ->once()
            ->with('gouvernance')
            ->andReturn([[
                'title' => 'Gouvernance numerique a Madagascar',
                'author' => 'Aina',
                'year' => 2024,
                'source_url' => 'https://example.test/doc',
                'source_name' => 'HAL',
                'original_id' => 'doc-1',
                'type' => 'article',
                'domain' => 'Gouvernance',
                'tags' => ['Numerique'],
            ]]);

        $registry = Mockery::mock(ScraperRegistry::class);
        $registry->shouldReceive('normalize')->once()->with('hal')->andReturn('hal');
        $registry->shouldReceive('availableSources')->once()->andReturn(['hal' => 'HAL']);
        $registry->shouldReceive('labelFor')->once()->with('hal')->andReturn('HAL');
        $registry->shouldReceive('forSource')->once()->with('hal')->andReturn($scraper);

        $this->app->instance(ScraperRegistry::class, $registry);

        $this->artisan('fikarohana:sync', ['source' => 'hal', '--query' => ['gouvernance']])
            ->assertSuccessful();

        $this->assertSame(1, Resource::count());
    }

    public function test_it_dispatches_one_job_per_query_when_queued_option_is_used(): void
    {
        Bus::fake();

        $this->artisan('fikarohana:sync', ['source' => 'hal', '--queued' => true])
            ->assertSuccessful();

        Bus::assertDispatchedTimes(SyncSourceResourcesJob::class, count(config('fikarohana.sync_queries')));
    }

    public function test_it_rejects_unknown_sources(): void
    {
        Bus::fake();

        $this->artisan('fikarohana:sync', ['source' => 'unknown'])
            ->assertExitCode(2);

        Bus::assertNothingDispatched();
    }
}

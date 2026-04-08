<?php

namespace App\Console\Commands;

use App\Jobs\SyncSourceResourcesJob;
use App\Services\Scrapers\ScraperRegistry;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('fikarohana:sync {source : hal|openalex|thesesfr|cairn|horizon|horizon-ird|all} {--query=* : Override the default sync queries} {--queued : Dispatch jobs to the queue instead of running immediately}')]
#[Description('Sync resources from one source or every configured source.')]
class FikarohanaSyncCommand extends Command
{
    public function handle(ScraperRegistry $registry): int
    {
        $requestedSource = (string) $this->argument('source');
        $queries = collect($this->option('query'))
            ->filter()
            ->values();

        if ($queries->isEmpty()) {
            $queries = collect(config('fikarohana.sync_queries', ['Madagascar']));
        }

        $sources = $requestedSource === 'all'
            ? array_keys($registry->availableSources())
            : [$registry->normalize($requestedSource)];

        foreach ($sources as $source) {
            if (! array_key_exists($source, $registry->availableSources())) {
                $this->error("Unsupported source [{$source}].");

                return self::INVALID;
            }
        }

        $dispatched = 0;
        $queued = (bool) $this->option('queued');

        foreach ($sources as $source) {
            foreach ($queries as $query) {
                if ($queued) {
                    SyncSourceResourcesJob::dispatch($source, $query);
                } else {
                    SyncSourceResourcesJob::dispatchSync($source, $query);
                }

                $dispatched++;
            }
        }

        $mode = $queued ? 'queued' : 'executed immediately';

        $this->info("{$dispatched} sync job(s) {$mode} for ".implode(', ', $sources).'.');

        return self::SUCCESS;
    }
}

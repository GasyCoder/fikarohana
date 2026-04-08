<?php

namespace App\Providers;

use App\Services\Scrapers\ScraperRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ScraperRegistry::class);
    }

    public function boot(): void
    {
        //
    }
}

<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        foreach (['register' => 5, 'login' => 10, 'tokens' => 10] as $name => $attempts) {
            RateLimiter::for($name, function (Request $request) use ($name, $attempts) {
                $email = is_string($request->input('email')) ? Str::lower(trim($request->input('email'))) : '';

                return [
                    Limit::perMinute($attempts)->by($name.':ip:'.$request->ip()),
                    Limit::perMinute($attempts)->by($name.':email:'.hash('sha256', $email)),
                ];
            });
        }
    }
}

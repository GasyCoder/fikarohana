<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\JournalDiscoveryController;
use App\Http\Middleware\RequireSpaSession;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;

Route::get('/health', HealthController::class)->withoutMiddleware(EnsureFrontendRequestsAreStateful::class);
Route::middleware('throttle:journal-discovery')->withoutMiddleware(EnsureFrontendRequestsAreStateful::class)->group(function (): void {
    Route::get('/journals', [JournalDiscoveryController::class, 'index']);
    Route::get('/journals/{journal}', [JournalDiscoveryController::class, 'show']);
    Route::post('/journal-recommendations', [JournalDiscoveryController::class, 'recommend']);
});
Route::post('/auth/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/auth/login', [AuthController::class, 'login'])->middleware([RequireSpaSession::class, 'throttle:login']);
Route::post('/auth/logout', [AuthController::class, 'logout'])->middleware(RequireSpaSession::class);
Route::post('/auth/tokens', [AuthController::class, 'token'])->middleware('throttle:tokens');
Route::delete('/auth/tokens/current', [AuthController::class, 'revoke'])->middleware('auth:sanctum');
Route::get('/me', fn (Request $request) => new UserResource($request->user()))->middleware('auth:sanctum');

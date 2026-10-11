<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        try {
            if (DB::connection()->getDriverName() !== 'pgsql') {
                throw new \RuntimeException;
            }

            DB::select('SELECT 1');
            $vector = DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'vector') AS enabled");

            if (! $vector->enabled) {
                throw new \RuntimeException;
            }
        } catch (Throwable) {
            return response()->json(['error' => [
                'code' => 'not_ready',
                'message' => 'Application is not ready.',
                'fields' => (object) [],
                'request_id' => request()->attributes->get('request_id'),
            ]], 503)->header('Cache-Control', 'no-store');
        }

        return response()->json(['data' => ['status' => 'ready']])->header('Cache-Control', 'no-store');
    }
}

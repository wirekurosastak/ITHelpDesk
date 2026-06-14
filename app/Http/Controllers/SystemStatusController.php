<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $databaseStatus = 'Online';

        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $databaseStatus = 'Offline';
        }

        $startTimeTimestamp = Cache::rememberForever('app_start_timestamp', fn () => now()->timestamp);
        $startTime = Carbon::createFromTimestamp($startTimeTimestamp);

        return response()->json([
            'data' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'database' => $databaseStatus,
                'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
                'uptime' => $startTime->diffForHumans(null, true),
                'start_timestamp' => $startTimeTimestamp,
            ],
        ]);
    }

    /**
     * Clear all application caches (admin only).
     */
    public function clearCache(): JsonResponse
    {
        Artisan::call('optimize:clear');

        // Reset uptime counter so it reflects fresh start
        Cache::forget('app_start_timestamp');

        return response()->json([
            'message' => 'All caches cleared successfully. Uptime counter reset.',
        ]);
    }
}

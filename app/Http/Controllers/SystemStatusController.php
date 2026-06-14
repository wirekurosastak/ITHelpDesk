<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class SystemStatusController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $databaseStatus = 'online';

        try {
            DB::connection()->getPdo();
        } catch (Throwable) {
            $databaseStatus = 'offline';
        }

        return response()->json([
            'data' => [
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'database' => $databaseStatus,
                'memory_usage_mb' => round(memory_get_usage() / 1024 / 1024, 2),
                'server_time' => now()->toISOString(),
            ],
        ]);
    }
}

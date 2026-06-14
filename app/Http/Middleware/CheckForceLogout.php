<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckForceLogout
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if ($user && Cache::has($user->forceLogoutCacheKey())) {
            Cache::forget($user->forceLogoutCacheKey());
            auth('api')->logout();

            return response()->json(['message' => 'Your session has been terminated by an administrator.'], 401);
        }

        return $next($request);
    }
}

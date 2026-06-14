<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class CheckForceLogout
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();
        if ($user) {
            if (Cache::has('force_logout_' . $user->id)) {
                Cache::forget('force_logout_' . $user->id);
                auth('api')->logout();
                return response()->json(['message' => 'Your session has been terminated by an administrator.'], 401);
            }
        }

        return $next($request);
    }
}

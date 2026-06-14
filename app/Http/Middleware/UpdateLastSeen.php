<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastSeen
{
    /**
     * Update the authenticated user's last_seen_at timestamp on every API request.
     * Uses a raw DB update to avoid touching model timestamps.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if ($user) {
            DB::table('users')
                ->where('id', $user->getKey())
                ->update(['last_seen_at' => now()]);
        }

        return $next($request);
    }
}

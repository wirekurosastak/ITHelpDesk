<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\Response;

class UpdateLastSeen
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = auth('api')->user();

        if ($user) {
            $ip = $request->ip();
            if ($ip === '::1') {
                $ip = '127.0.0.1';
            } elseif (str_starts_with($ip, '::ffff:')) {
                $ip = substr($ip, 7);
            }
            DB::table('users')
                ->where('id', $user->getKey())
                ->update(['last_seen_at' => now()]);
        }

        return $next($request);
    }
}

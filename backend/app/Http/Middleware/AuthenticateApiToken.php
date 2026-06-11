<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiToken
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $token = $request->bearerToken();

        if (! $token) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $user = User::query()
            ->where('api_token_hash', hash('sha256', $token))
            ->first();

        if (! $user) {
            return response()->json(['message' => 'Unauthenticated.'], 401);
        }

        $ttlMinutes = (int) config('auth.api_token_ttl_minutes', 1440);
        if (
            ! $user->api_token_issued_at
            || ($ttlMinutes > 0 && $user->api_token_issued_at->copy()->addMinutes($ttlMinutes)->isPast())
        ) {
            $user->forceFill([
                'api_token_hash' => null,
                'api_token_issued_at' => null,
                'api_token_last_used_at' => null,
            ])->save();

            return response()->json(['message' => 'Token expired.'], 401);
        }

        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $user->forceFill([
            'api_token_last_used_at' => now(),
        ])->save();

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}

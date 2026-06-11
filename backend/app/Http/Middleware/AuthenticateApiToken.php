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

        if ($roles !== [] && ! in_array($user->role, $roles, true)) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}

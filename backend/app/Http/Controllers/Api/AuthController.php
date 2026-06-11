<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request, AuditLogger $auditLogger): array
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::query()->where('email', $credentials['email'])->first();

        if (! $user || ! Hash::check($credentials['password'], $user->password)) {
            throw ValidationException::withMessages([
                'email' => ['The provided credentials are invalid.'],
            ]);
        }

        $plainToken = Str::random(64);
        $user->forceFill([
            'api_token_hash' => hash('sha256', $plainToken),
            'api_token_issued_at' => now(),
        ])->save();
        $request->setUserResolver(fn () => $user);
        $auditLogger->record($request, 'auth.login', $user, [
            'role' => $user->role,
        ]);

        return [
            'data' => [
                'token' => $plainToken,
                'user' => $this->userPayload($user),
            ],
        ];
    }

    public function me(Request $request): array
    {
        return [
            'data' => $this->userPayload($request->user()),
        ];
    }

    public function logout(Request $request, AuditLogger $auditLogger): array
    {
        $user = $request->user();
        $request->user()->forceFill([
            'api_token_hash' => null,
            'api_token_issued_at' => null,
        ])->save();
        $auditLogger->record($request, 'auth.logout', $user);

        return ['message' => 'Logged out.'];
    }

    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ];
    }
}

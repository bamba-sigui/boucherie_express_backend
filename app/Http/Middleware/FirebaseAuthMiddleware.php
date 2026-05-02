<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\FirebaseAuthService;
use Closure;
use Illuminate\Http\Request;

class FirebaseAuthMiddleware
{
    public function __construct(private FirebaseAuthService $firebase) {}

    public function handle(Request $request, Closure $next)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json(['success' => false, 'error' => 'Token manquant'], 401);
        }

        $payload = $this->firebase->verifyToken($token);

        if (!$payload) {
            return response()->json(['success' => false, 'error' => 'Token invalide ou expiré'], 401);
        }

        $user = User::firstOrCreate(
            ['firebase_uid' => $payload['uid']],
            [
                'email'        => $payload['email'] ?? null,
                'phone'        => $payload['phone'] ?? null,
                'name'         => $payload['name'] ?? 'Utilisateur',
                'photo_url'    => $payload['picture'] ?? null,
                'account_type' => 'b2c',
            ]
        );

        $request->setUserResolver(fn () => $user);

        return $next($request);
    }
}

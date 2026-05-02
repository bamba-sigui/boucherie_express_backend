<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class FirebaseAuthService
{
    public function verifyToken(string $idToken): ?array
    {
        try {
            $firebase = app('firebase.auth');
            $verifiedToken = $firebase->verifyIdToken($idToken);
            $claims = $verifiedToken->claims();

            return [
                'uid'            => $claims->get('sub'),
                'email'          => $claims->get('email'),
                'phone'          => $claims->get('phone_number'),
                'name'           => $claims->get('name'),
                'picture'        => $claims->get('picture'),
                'email_verified' => $claims->get('email_verified', false),
            ];
        } catch (\Throwable $e) {
            Log::warning('Firebase token verification failed', ['error' => $e->getMessage()]);
            return null;
        }
    }
}

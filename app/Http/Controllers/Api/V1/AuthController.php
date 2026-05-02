<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Traits\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    use ApiResponse;

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:8',
            'phone'    => 'nullable|string',
        ]);

        $user = User::create([
            'name'     => $validated['name'],
            'email'    => $validated['email'],
            'password' => Hash::make($validated['password']),
            'phone'    => $validated['phone'] ?? null,
        ]);

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->ok(['user' => new UserResource($user), 'token' => $token], 201);
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $validated['email'])->first();

        if (!$user || !Hash::check($validated['password'], $user->password)) {
            return $this->fail('Identifiants invalides', 401);
        }

        $token = $user->createToken('auth-token')->plainTextToken;

        return $this->ok(['user' => new UserResource($user), 'token' => $token]);
    }

    public function me(Request $request)
    {
        return $this->ok(new UserResource($request->user()->load('addresses')));
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return $this->ok(['message' => 'Déconnecté']);
    }

    public function checkPhone(Request $request)
    {
        $request->validate(['phone' => 'required|string']);
        $exists = User::where('phone', $request->query('phone'))
            ->where('account_type', '!=', 'admin')
            ->exists();
        return $this->ok(['exists' => $exists]);
    }
}

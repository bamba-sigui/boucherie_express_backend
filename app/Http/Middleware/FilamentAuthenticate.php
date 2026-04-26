<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate;

class FilamentAuthenticate extends Authenticate
{
    protected function redirectTo($request): ?string
    {
        return url('/login');
    }
}

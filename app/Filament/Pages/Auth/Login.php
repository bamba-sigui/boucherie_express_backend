<?php

namespace App\Filament\Pages\Auth;

use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    public function getTitle(): string
    {
        return 'Boucherie Express — Administration';
    }

    public function getHeading(): string
    {
        return 'Connexion';
    }

    public function getSubheading(): ?string
    {
        return 'Accès réservé aux administrateurs et partenaires';
    }
}

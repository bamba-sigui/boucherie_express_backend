<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->brandName('Boucherie Express')
            ->path('dashboard')
            ->login(\App\Filament\Pages\Auth\Login::class)
            ->colors([
                'primary' => '#dc2626',
            ])
            ->authMiddleware([
                \App\Http\Middleware\FilamentAuthenticate::class,
            ])
            ->resources([
                \App\Filament\Resources\ProductResource::class,
                \App\Filament\Resources\CategoryResource::class,
                \App\Filament\Resources\OrderResource::class,
                \App\Filament\Resources\UserResource::class,
            ])
            ->pages([
                \Filament\Pages\Dashboard::class,
            ]);
    }
}
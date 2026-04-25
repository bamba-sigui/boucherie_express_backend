<?php

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use Illuminate\Support\Facades\Auth;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->id('admin')
            ->title('Boucherie Express - Administration')
            ->navigationIcon('heroicon-o-shopping-bag')
            ->slug('admin')
            ->path('admin')
            ->login()
            ->colors([
                'primary' => '#dc2626',
            ])
            ->resources([
                \App\Filament\Resources\ProductResource::class,
                \App\Filament\Resources\CategoryResource::class,
                \App\Filament\Resources\OrderResource::class,
                \App\Filament\Resources\UserResource::class,
            ])
            ->pages([
                \Filament\Pages\Dashboard::class,
            ])
            ->discoverResources(in app_path('Filament/Resources'), 'App\\Filament\\Resources')
            ->discoverPages(in app_path('Filament/Pages'), 'App\\Filament\\Pages');
    }
}
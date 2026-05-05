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
            ->middleware([
                \Illuminate\Cookie\Middleware\EncryptCookies::class,
                \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
                \Illuminate\Session\Middleware\StartSession::class,
                \Illuminate\View\Middleware\ShareErrorsFromSession::class,
                \Illuminate\Foundation\Http\Middleware\VerifyCsrfToken::class,
                \Illuminate\Routing\Middleware\SubstituteBindings::class,
                \Filament\Http\Middleware\DisableBladeIconComponents::class,
                \Filament\Http\Middleware\DispatchServingFilamentEvent::class,
            ])
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
                \App\Filament\Resources\CustomerResource::class,
                \App\Filament\Resources\CourierResource::class,
                \App\Filament\Resources\CouponResource::class,
            ])
            ->pages([
                \Filament\Pages\Dashboard::class,
                \App\Filament\Pages\Settings::class,
                \App\Filament\Pages\CourierMap::class,
            ])
            ->widgets([
                \App\Filament\Widgets\RevenueOverview::class,
                \App\Filament\Widgets\OrdersChart::class,
                \App\Filament\Widgets\TopProducts::class,
                \App\Filament\Widgets\LowStockAlert::class,
            ]);
    }
}

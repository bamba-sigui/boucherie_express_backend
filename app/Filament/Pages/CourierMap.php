<?php

namespace App\Filament\Pages;

use App\Models\Courier;
use Filament\Pages\Page;

class CourierMap extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-map';
    protected static ?string $title = 'Carte livreurs';
    protected static string $view = 'filament.pages.courier-map';
    protected static ?int $navigationSort = 8;

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function getCouriersData(): array
    {
        return Courier::where('is_active', true)
            ->whereNotNull('current_lat')
            ->with(['activeOrder.user'])
            ->get()
            ->map(fn ($c) => [
                'id'          => $c->id,
                'name'        => $c->name,
                'lat'         => (float) $c->current_lat,
                'lng'         => (float) $c->current_lng,
                'photo'       => $c->photo_url,
                'order'       => $c->activeOrder ? [
                    'id'       => $c->activeOrder->id,
                    'customer' => $c->activeOrder->user?->name,
                    'address'  => $c->activeOrder->delivery_address,
                ] : null,
                'lastUpdate'  => $c->location_updated_at?->diffForHumans(),
            ])
            ->toArray();
    }
}

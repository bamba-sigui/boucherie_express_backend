<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RevenueOverview extends BaseWidget
{
    protected static ?int $sort = 1;
    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $today = Order::whereDate('ordered_at', today())
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $yesterday = Order::whereDate('ordered_at', today()->subDay())
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $delta = $yesterday > 0 ? round((($today - $yesterday) / $yesterday) * 100, 1) : 0;

        $monthRevenue = Order::whereMonth('ordered_at', now()->month)
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $avgBasket = Order::whereMonth('ordered_at', now()->month)
            ->where('payment_status', 'paid')
            ->avg('total_amount') ?? 0;

        $newCustomers = User::whereMonth('created_at', now()->month)
            ->where('account_type', 'b2c')
            ->count();

        $pendingOrders = Order::whereIn('status', ['pending', 'confirmed', 'preparing'])->count();

        return [
            Stat::make('CA Aujourd\'hui', number_format($today, 0, ',', ' ') . ' FCFA')
                ->description(($delta >= 0 ? '+' : '') . $delta . '% vs hier')
                ->descriptionIcon($delta >= 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->color($delta >= 0 ? 'success' : 'danger')
                ->chart($this->lastSevenDays()),

            Stat::make('CA du mois', number_format($monthRevenue, 0, ',', ' ') . ' FCFA')
                ->description('Mois en cours')
                ->color('primary'),

            Stat::make('Panier moyen', number_format($avgBasket, 0, ',', ' ') . ' FCFA')
                ->description('Cible : 9 000 FCFA')
                ->color($avgBasket >= 9000 ? 'success' : 'warning'),

            Stat::make('Nouveaux clients', $newCustomers)
                ->description('Ce mois-ci')
                ->descriptionIcon('heroicon-m-user-plus'),

            Stat::make('Commandes en cours', $pendingOrders)
                ->description('À traiter')
                ->color($pendingOrders > 10 ? 'warning' : 'success')
                ->descriptionIcon('heroicon-m-clock'),
        ];
    }

    private function lastSevenDays(): array
    {
        return collect(range(6, 0))->map(fn ($d) =>
            (int) Order::whereDate('ordered_at', today()->subDays($d))
                ->where('payment_status', 'paid')
                ->sum('total_amount')
        )->toArray();
    }
}

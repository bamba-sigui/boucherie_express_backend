<?php

namespace App\Filament\Widgets;

use App\Models\Order;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class OrdersChart extends ChartWidget
{
    protected static ?string $heading = 'Commandes — 30 derniers jours';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 2;

    protected function getData(): array
    {
        $data = Order::select(
            DB::raw('DATE(COALESCE(ordered_at, created_at)) as date'),
            DB::raw('count(*) as count')
        )
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'datasets' => [
                [
                    'label'           => 'Commandes',
                    'data'            => $data->pluck('count')->toArray(),
                    'borderColor'     => '#dc2626',
                    'backgroundColor' => 'rgba(220, 38, 38, 0.1)',
                    'fill'            => true,
                ],
            ],
            'labels' => $data->pluck('date')
                ->map(fn ($d) => \Carbon\Carbon::parse($d)->format('d/m'))
                ->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}

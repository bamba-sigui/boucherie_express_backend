<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Facades\DB;

class TopProducts extends BaseWidget
{
    protected static ?string $heading = 'Top produits du mois';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Product::select('products.*', DB::raw('COALESCE(SUM(order_items.quantity), 0) as total_sold'))
                    ->leftJoin('order_items', 'order_items.product_id', '=', 'products.id')
                    ->leftJoin('orders', function ($join) {
                        $join->on('orders.id', '=', 'order_items.order_id')
                            ->whereMonth('orders.created_at', now()->month)
                            ->where('orders.payment_status', 'paid');
                    })
                    ->groupBy('products.id')
                    ->orderByDesc('total_sold')
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('name')->label('Produit'),
                TextColumn::make('total_sold')->label('Vendus')->suffix(' kg'),
                TextColumn::make('stock')->badge()
                    ->color(fn ($state) => $state < 5 ? 'danger' : ($state < 15 ? 'warning' : 'success')),
            ]);
    }
}

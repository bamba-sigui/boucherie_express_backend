<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Tables\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Forms\Components\TextInput;

class LowStockAlert extends BaseWidget
{
    protected static ?int $sort = 4;
    protected int|string|array $columnSpan = 1;

    public function table(Table $table): Table
    {
        return $table
            ->query(Product::where('stock', '<', 10)->where('is_active', true))
            ->heading('Alertes stock bas')
            ->columns([
                TextColumn::make('name')->label('Produit'),
                TextColumn::make('stock')->badge()->color('danger'),
                TextColumn::make('category.name')->label('Catégorie'),
            ])
            ->actions([
                Action::make('restock')
                    ->label('Réapprovisionner')
                    ->icon('heroicon-o-arrow-path')
                    ->form([
                        TextInput::make('quantity')->numeric()->required()->label('Quantité à ajouter'),
                    ])
                    ->action(fn ($record, $data) => $record->increment('stock', $data['quantity'])),
            ]);
    }
}

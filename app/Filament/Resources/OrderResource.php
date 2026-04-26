<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrderResource\Pages;
use App\Models\Order;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrderResource extends Resource
{
    protected static ?string $model = Order::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-cart';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'partenaire']) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canEdit($record): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'partenaire']) ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        $isPartner = auth()->user()?->hasRole('partenaire');

        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->preload()
                    ->searchable()
                    ->disabled($isPartner),
                Forms\Components\Select::make('status')
                    ->options([
                        'pending' => 'En attente',
                        'paid' => 'Payé',
                        'preparing' => 'En préparation',
                        'shipping' => 'En livraison',
                        'delivered' => 'Livré',
                        'cancelled' => 'Annulé',
                    ]),
                Forms\Components\TextInput::make('total')
                    ->numeric()
                    ->prefix('€')
                    ->disabled($isPartner),
                Forms\Components\KeyValue::make('items')
                    ->keyLabel('Produit')
                    ->valueLabel('Quantité')
                    ->disabled($isPartner),
                Forms\Components\Textarea::make('shipping_address')
                    ->disabled($isPartner),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id'),
                Tables\Columns\TextColumn::make('user.name'),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'pending' => 'gray',
                        'paid' => 'blue',
                        'preparing' => 'yellow',
                        'shipping' => 'orange',
                        'delivered' => 'green',
                        'cancelled' => 'red',
                    }),
                Tables\Columns\TextColumn::make('total')->money('EUR'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending' => 'En attente',
                        'paid' => 'Payé',
                        'preparing' => 'En préparation',
                        'shipping' => 'En livraison',
                        'delivered' => 'Livré',
                        'cancelled' => 'Annulé',
                    ]),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\ViewAction::make(),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\TextEntry::make('user.name'),
                Infolists\Components\TextEntry::make('status')
                    ->badge(),
                Infolists\Components\TextEntry::make('total')->money('EUR'),
                Infolists\Components\TextEntry::make('shipping_address'),
                Infolists\Components\TextEntry::make('created_at')->dateTime(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrders::route('/'),
            'view' => Pages\ViewOrder::route('/{record}'),
            'edit' => Pages\EditOrder::route('/{record}/edit'),
        ];
    }
}
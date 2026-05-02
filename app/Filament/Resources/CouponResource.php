<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CouponResource\Pages;
use App\Models\Coupon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CouponResource extends Resource
{
    protected static ?string $model = Coupon::class;
    protected static ?string $navigationIcon = 'heroicon-o-ticket';
    protected static ?string $navigationLabel = 'Coupons';
    protected static ?string $modelLabel = 'Coupon';
    protected static ?int $navigationSort = 7;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('code')->required()->unique(ignoreRecord: true)->placeholder('BIENVENUE10'),
            Select::make('type')
                ->options(['percent' => 'Pourcentage', 'fixed' => 'Montant fixe', 'free_delivery' => 'Livraison gratuite'])
                ->required()
                ->live(),
            TextInput::make('value')
                ->numeric()
                ->required()
                ->suffix(fn (Get $get) => $get('type') === 'percent' ? '%' : 'FCFA'),
            TextInput::make('min_order_amount')->numeric()->suffix('FCFA')->default(0),
            TextInput::make('max_uses')->numeric()->placeholder('Illimité'),
            TextInput::make('max_uses_per_user')->numeric()->default(1),
            DateTimePicker::make('starts_at')->label('Début'),
            DateTimePicker::make('expires_at')->label('Expiration'),
            Toggle::make('first_order_only')->label('Première commande uniquement'),
            Toggle::make('premium_only')->label('Premium uniquement'),
            Toggle::make('is_active')->default(true)->label('Actif'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')->searchable()->badge(),
                TextColumn::make('type')->badge(),
                TextColumn::make('value'),
                TextColumn::make('uses_count')->label('Utilisations'),
                TextColumn::make('expires_at')->dateTime()->label('Expiration'),
                IconColumn::make('is_active')->boolean()->label('Actif'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCoupons::route('/'),
            'create' => Pages\CreateCoupon::route('/create'),
            'edit'   => Pages\EditCoupon::route('/{record}/edit'),
        ];
    }
}

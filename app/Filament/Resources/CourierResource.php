<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CourierResource\Pages;
use App\Models\Courier;
use App\Models\Order;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CourierResource extends Resource
{
    protected static ?string $model = Courier::class;
    protected static ?string $navigationIcon = 'heroicon-o-truck';
    protected static ?string $navigationLabel = 'Livreurs';
    protected static ?string $modelLabel = 'Livreur';
    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            FileUpload::make('photo_url')->image()->avatar()->directory('couriers')->label('Photo'),
            TextInput::make('name')->required(),
            TextInput::make('phone')->required()->tel(),
            TextInput::make('vehicle')->default('Moto'),
            TextInput::make('license_plate')->label('Plaque d\'immatriculation'),
            TextInput::make('rating')->numeric()->default(5.0)->step(0.1)->disabled(),
            Toggle::make('is_active')->default(true)->label('Actif'),
            Toggle::make('is_available')->default(true)->label('Disponible maintenant'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_url')->circular()->label(''),
                TextColumn::make('name')->searchable(),
                TextColumn::make('phone'),
                TextColumn::make('vehicle'),
                TextColumn::make('rating')->badge()->color('warning')->prefix('⭐ '),
                TextColumn::make('total_deliveries')
                    ->getStateUsing(fn ($record) => Order::where('courier_id', $record->id)->where('status', 'delivered')->count())
                    ->label('Livraisons'),
                TextColumn::make('active_deliveries')
                    ->getStateUsing(fn ($record) => Order::where('courier_id', $record->id)->where('status', 'delivering')->count())
                    ->badge()->color('warning')->label('En cours'),
                IconColumn::make('is_active')->boolean()->label('Actif'),
                IconColumn::make('is_available')->boolean()->label('Dispo'),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListCouriers::route('/'),
            'create' => Pages\CreateCourier::route('/create'),
            'edit'   => Pages\EditCourier::route('/{record}/edit'),
        ];
    }
}

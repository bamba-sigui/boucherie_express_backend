<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ProductResource\Pages;
use App\Filament\Resources\ProductResource\RelationManagers;
use App\Models\Product;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static ?string $navigationIcon = 'heroicon-o-shopping-bag';

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
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function canDelete($record): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Forms\Components\Textarea::make('description')
                    ->rows(3),
                Forms\Components\TextInput::make('price')
                    ->required()
                    ->numeric()
                    ->prefix('€'),
                Forms\Components\TextInput::make('stock')
                    ->numeric()
                    ->default(0),
                Forms\Components\Select::make('category_id')
                    ->relationship('category', 'name')
                    ->preload(),
                Forms\Components\Radio::make('image_source')
                    ->label('Source de l\'image')
                    ->options(['upload' => 'Uploader une photo', 'url' => 'URL externe'])
                    ->default('upload')
                    ->live()
                    ->dehydrated(false)
                    ->afterStateHydrated(function (Forms\Components\Radio $component, $record) {
                        if ($record && $record->image && empty($record->images)) {
                            $component->state('url');
                        } else {
                            $component->state('upload');
                        }
                    })
                    ->columnSpanFull(),
                Forms\Components\FileUpload::make('images')
                    ->label('Photos du produit')
                    ->multiple()
                    ->image()
                    ->disk('public')
                    ->directory('products')
                    ->maxSize(2048)
                    ->reorderable()
                    ->panelLayout('grid')
                    ->columnSpanFull()
                    ->hidden(fn (Forms\Get $get) => $get('image_source') !== 'upload'),
                Forms\Components\TextInput::make('image')
                    ->label('URL de l\'image')
                    ->url()
                    ->placeholder('https://...')
                    ->columnSpanFull()
                    ->hidden(fn (Forms\Get $get) => $get('image_source') !== 'url'),
                Forms\Components\TextInput::make('video_url')
                    ->label('URL de la vidéo')
                    ->url()
                    ->placeholder('https://...')
                    ->columnSpanFull(),
                Forms\Components\Toggle::make('is_active')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->searchable(),
                Tables\Columns\TextColumn::make('category.name'),
                Tables\Columns\TextColumn::make('price')->money('EUR'),
                Tables\Columns\TextColumn::make('stock'),
                Tables\Columns\ImageColumn::make('images_preview')
                    ->label('Photos')
                    ->getStateUsing(function ($record) {
                        $images = $record->images;
                        if (!empty($images)) {
                            return array_slice($images, 0, 2);
                        }
                        return $record->image ? [$record->image] : [];
                    })
                    ->stacked()
                    ->limit(2)
                    ->defaultImageUrl('https://placehold.co/60x60?text=No+image'),
                Tables\Columns\TextColumn::make('video_url')
                    ->label('Vidéo')
                    ->formatStateUsing(fn ($state) => $state ? '▶ Voir' : '—')
                    ->url(fn ($record) => $record->video_url)
                    ->openUrlInNewTab()
                    ->color('primary'),
                Tables\Columns\BooleanColumn::make('is_active'),
                Tables\Columns\TextColumn::make('created_at')->dateTime(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->relationship('category', 'name'),
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\TextEntry::make('name'),
                Infolists\Components\TextEntry::make('description'),
                Infolists\Components\TextEntry::make('price')->money('EUR'),
                Infolists\Components\TextEntry::make('stock'),
                Infolists\Components\BooleanEntry::make('is_active'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListProducts::route('/'),
            'create' => Pages\CreateProduct::route('/create'),
            'edit' => Pages\EditProduct::route('/{record}/edit'),
        ];
    }
}
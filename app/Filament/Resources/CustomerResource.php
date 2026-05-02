<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CustomerResource\Pages;
use App\Models\User;
use App\Services\FirebaseMessagingService;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Infolist;
use Filament\Resources\Resource;
use Filament\Tables\Actions\Action;
use Filament\Tables\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CustomerResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-users';
    protected static ?string $navigationLabel = 'Clients';
    protected static ?string $modelLabel = 'Client';
    protected static ?int $navigationSort = 5;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasAnyRole(['admin', 'partenaire']) ?? false;
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('account_type', 'b2c');
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            TextInput::make('name')->required(),
            TextInput::make('email')->email(),
            TextInput::make('phone'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('photo_url')->circular()->label('')->defaultImageUrl(fn () => 'https://ui-avatars.com/api/?name=U'),
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('email')->searchable(),
                TextColumn::make('orders_count')->counts('orders')->label('Cmd'),
                IconColumn::make('is_premium')->boolean()->label('Premium'),
                TextColumn::make('created_at')->date()->label('Inscrit le')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('is_premium'),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('send_notification')
                    ->icon('heroicon-o-bell')
                    ->label('Notifier')
                    ->form([
                        TextInput::make('title')->required(),
                        Textarea::make('body')->required(),
                    ])
                    ->action(function ($record, $data) {
                        if ($record->fcm_token) {
                            app(FirebaseMessagingService::class)->sendToToken(
                                $record->fcm_token,
                                $data['title'],
                                $data['body']
                            );
                        }
                    }),
                Action::make('grant_premium')
                    ->icon('heroicon-o-star')
                    ->label('Premium')
                    ->visible(fn ($record) => !$record->is_premium)
                    ->form([
                        DatePicker::make('until')->required()->default(now()->addYear()->format('Y-m-d')),
                    ])
                    ->action(fn ($record, $data) => $record->update([
                        'is_premium'    => true,
                        'premium_until' => $data['until'],
                    ])),
            ]);
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Section::make('Profil')->schema([
                TextEntry::make('name'),
                TextEntry::make('phone'),
                TextEntry::make('email'),
                TextEntry::make('created_at')->dateTime(),
            ])->columns(2),

            Section::make('Statistiques')->schema([
                TextEntry::make('orders_total')
                    ->label('Total dépensé')
                    ->getStateUsing(fn ($record) =>
                        number_format($record->orders()->where('payment_status', 'paid')->sum('total_amount'), 0, ',', ' ') . ' FCFA'
                    ),
                TextEntry::make('orders_count')
                    ->label('Commandes')
                    ->getStateUsing(fn ($record) => $record->orders()->count()),
            ])->columns(2),
        ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCustomers::route('/'),
            'view'  => Pages\ViewCustomer::route('/{record}'),
        ];
    }
}

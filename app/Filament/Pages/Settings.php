<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Tabs\Tab;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class Settings extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';
    protected static ?string $title = 'Paramètres';
    protected static string $view = 'filament.pages.settings';
    protected static ?int $navigationSort = 99;

    public ?array $data = [];

    public static function canAccess(): bool
    {
        return auth()->user()?->hasRole('admin') ?? false;
    }

    public function mount(): void
    {
        $this->form->fill([
            'company_name'             => Setting::get('company_name', 'Boucherie Express'),
            'support_phone'            => Setting::get('support_phone'),
            'support_email'            => Setting::get('support_email'),
            'opening_hours'            => Setting::get('opening_hours', '08:00-20:00'),
            'min_order_amount'         => Setting::get('min_order_amount', 5000),
            'default_delivery_fee'     => Setting::get('default_delivery_fee', 1500),
            'free_delivery_threshold'  => Setting::get('free_delivery_threshold', 30000),
            'whatsapp_number'          => Setting::get('whatsapp_number'),
            'maintenance_mode'         => Setting::get('maintenance_mode', false),
            'maintenance_message'      => Setting::get('maintenance_message'),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form->schema([
            Tabs::make()->tabs([
                Tab::make('Général')->schema([
                    TextInput::make('company_name')->label('Nom de l\'entreprise'),
                    TextInput::make('support_phone')->label('Téléphone support')->tel(),
                    TextInput::make('support_email')->label('Email support')->email(),
                    TextInput::make('whatsapp_number')->label('WhatsApp'),
                    TextInput::make('opening_hours')->label('Horaires d\'ouverture'),
                ]),
                Tab::make('Commandes')->schema([
                    TextInput::make('min_order_amount')->numeric()->suffix('FCFA')->label('Commande minimum'),
                    TextInput::make('default_delivery_fee')->numeric()->suffix('FCFA')->label('Frais de livraison par défaut'),
                    TextInput::make('free_delivery_threshold')->numeric()->suffix('FCFA')->label('Seuil livraison gratuite'),
                ]),
                Tab::make('Maintenance')->schema([
                    Toggle::make('maintenance_mode')->label('Mode maintenance (app indisponible)'),
                    Textarea::make('maintenance_message')->label('Message de maintenance'),
                ]),
            ]),
        ])->statePath('data');
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::set($key, $value);
        }
        Notification::make()->success()->title('Paramètres enregistrés')->send();
    }
}

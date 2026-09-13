<?php

namespace App\Filament\Resources\Origins\Schemas;

use App\Models\Origin;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class OriginForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Negocio')
                    ->description('Cada negocio de ReservaYa es un origen con sus propias credenciales.')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                if (blank($get('slug'))) {
                                    $set('slug', Str::slug($state ?? ''));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Identificador (X-RY-ORIGIN)')
                            ->helperText('Es el valor que ReservaYa manda en el encabezado X-RY-ORIGIN.')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Toggle::make('is_active')
                            ->label('Activo')
                            ->helperText('Un origen inactivo rechaza sus webhooks y no envía nada.')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('ReservaYa — origen de los datos')
                    ->columns(2)
                    ->schema([
                        TextInput::make('reservaya_base_url')
                            ->label('URL base')
                            ->url()
                            ->placeholder(config('notificaya.reservaya.base_url'))
                            ->maxLength(255),
                        TextInput::make('reservaya_token')
                            ->label('Token de la API')
                            ->helperText('Panel de ReservaYa → Configuración → API externa. Solo hace falta para verificar y resincronizar.')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                        Toggle::make('verify_with_api')
                            ->label('Verificar cada webhook contra ReservaYa')
                            ->helperText('Vuelve a consultar la cita antes de programar los SMS. Más lento, pero inmune a un payload manipulado.')
                            ->columnSpanFull(),
                        TextInput::make('webhook_secret')
                            ->label('Secreto del webhook')
                            ->helperText('Con este valor ReservaYa firma el cuerpo en X-RY-SIGNATURE.')
                            ->password()
                            ->revealable()
                            ->required()
                            ->default(fn () => Origin::generateWebhookSecret())
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('FastSMS — destino de los mensajes')
                    ->columns(2)
                    ->schema([
                        TextInput::make('fastsms_base_url')
                            ->label('URL base')
                            ->url()
                            ->placeholder(config('notificaya.fastsms.base_url'))
                            ->helperText('Raíz de la instalación, sin /api/v1.')
                            ->maxLength(255),
                        TextInput::make('fastsms_token')
                            ->label('Token Sanctum')
                            ->helperText('Panel de FastSMS → Perfil → API Tokens.')
                            ->password()
                            ->revealable()
                            ->maxLength(255),
                    ])
                    ->columnSpanFull(),

                Section::make('Regionalización')
                    ->columns(3)
                    ->schema([
                        Select::make('timezone')
                            ->label('Zona horaria del negocio')
                            ->options(self::timezones())
                            ->searchable()
                            ->required()
                            ->default(config('notificaya.defaults.timezone')),
                        Select::make('fastsms_timezone')
                            ->label('Zona horaria de FastSMS')
                            ->helperText('Solo si FastSMS corre en otra zona. Vacío = la del negocio.')
                            ->options(self::timezones())
                            ->searchable(),
                        TextInput::make('phone_prefix')
                            ->label('Prefijo telefónico')
                            ->helperText('Se antepone a los números que no vienen en E.164.')
                            ->required()
                            ->maxLength(8)
                            ->default(config('notificaya.defaults.phone_prefix')),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    /**
     * @return array<string, string>
     */
    private static function timezones(): array
    {
        return collect(timezone_identifiers_list())
            ->mapWithKeys(fn (string $timezone) => [$timezone => $timezone])
            ->all();
    }
}

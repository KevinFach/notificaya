<?php

namespace App\Filament\Resources\NotificationRules\Schemas;

use App\Enums\NotificationTrigger;
use App\Enums\ReminderOffsetUnit;
use App\Services\Notifications\MessageTemplateRenderer;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class NotificationRuleForm
{
    /**
     * @param  bool  $withOrigin  False inside the origin's relation manager, where the origin is already implied.
     */
    public static function configure(Schema $schema, bool $withOrigin = true): Schema
    {
        return $schema
            ->components([
                Section::make('Cuándo se dispara')
                    ->columns(3)
                    ->schema([
                        Select::make('origin_id')
                            ->label('Origen')
                            ->relationship('origin', 'name')
                            ->required()
                            ->searchable()
                            ->preload()
                            ->visible($withOrigin)
                            ->columnSpan(3),
                        TextInput::make('name')
                            ->label('Nombre interno')
                            ->required()
                            ->maxLength(255)
                            ->columnSpan(3),
                        Select::make('trigger')
                            ->label('Disparador')
                            ->options(self::triggerOptions())
                            ->required()
                            ->live()
                            ->columnSpan(1),
                        TextInput::make('offset_value')
                            ->label('Anticipación')
                            ->numeric()
                            ->minValue(1)
                            ->required(fn (Get $get): bool => self::isReminder($get))
                            ->visible(fn (Get $get): bool => self::isReminder($get))
                            ->columnSpan(1),
                        Select::make('offset_unit')
                            ->label('Unidad')
                            ->options(self::offsetUnitOptions())
                            ->required(fn (Get $get): bool => self::isReminder($get))
                            ->visible(fn (Get $get): bool => self::isReminder($get))
                            ->columnSpan(1),
                        Toggle::make('is_active')
                            ->label('Activa')
                            ->columnSpan(3),
                    ])
                    ->columnSpanFull(),

                Section::make('Qué se envía')
                    ->schema([
                        Textarea::make('template')
                            ->label('Plantilla del SMS')
                            ->required()
                            ->rows(4)
                            ->helperText('Marcadores disponibles: '.implode(' ', MessageTemplateRenderer::PLACEHOLDERS))
                            ->columnSpanFull(),
                        TagsInput::make('service_ids')
                            ->label('Solo para estos servicios')
                            ->helperText('Ids de servicio de ReservaYa. Vacío = todos los servicios.')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    private static function isReminder(Get $get): bool
    {
        return $get('trigger') === NotificationTrigger::Recordatorio->value;
    }

    /**
     * @return array<string, string>
     */
    private static function triggerOptions(): array
    {
        return collect(NotificationTrigger::cases())
            ->mapWithKeys(fn (NotificationTrigger $trigger) => [$trigger->value => $trigger->label()])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    private static function offsetUnitOptions(): array
    {
        return collect(ReminderOffsetUnit::cases())
            ->mapWithKeys(fn (ReminderOffsetUnit $unit) => [$unit->value => $unit->label()])
            ->all();
    }
}

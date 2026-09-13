<?php

namespace App\Filament\Resources\Appointments\Schemas;

use App\Enums\AppointmentKind;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class AppointmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Cita')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('lookup_code')->label('Código')->badge()->copyable(),
                        TextEntry::make('origin.name')->label('Origen'),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (AppointmentStatus $state) => $state->label())
                            ->color(fn (AppointmentStatus $state) => $state->color()),
                        TextEntry::make('kind')
                            ->label('Tipo')
                            ->formatStateUsing(fn (AppointmentKind $state) => $state->label()),
                        TextEntry::make('starts_at')
                            ->label('Inicio')
                            ->dateTime('d/m/Y H:i')
                            ->timezone(fn (Appointment $record) => $record->origin?->timezone),
                        TextEntry::make('ends_at')
                            ->label('Fin')
                            ->dateTime('d/m/Y H:i')
                            ->timezone(fn (Appointment $record) => $record->origin?->timezone)
                            ->placeholder('—'),
                    ])
                    ->columnSpanFull(),

                Section::make('Cliente')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('customer_name')->label('Nombre')->placeholder('—'),
                        TextEntry::make('customer_phone')->label('Teléfono')->placeholder('Sin teléfono'),
                        TextEntry::make('customer_email')->label('Correo')->placeholder('—'),
                    ])
                    ->columnSpanFull(),

                Section::make('Servicio o evento')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('service_external_id')->label('Id de servicio')->placeholder('—'),
                        TextEntry::make('service_name')->label('Servicio')->placeholder('—'),
                        TextEntry::make('service_slug')->label('Slug')->placeholder('—'),
                        TextEntry::make('event_name')->label('Evento')->placeholder('—'),
                        TextEntry::make('event_type')->label('Tipo de evento')->placeholder('—'),
                        TextEntry::make('responsable_email')->label('Responsable')->placeholder('—'),
                    ])
                    ->columnSpanFull(),

                Section::make('Payload recibido')
                    ->collapsed()
                    ->schema([
                        TextEntry::make('payload')
                            ->hiddenLabel()
                            ->formatStateUsing(fn (?array $state): string => json_encode(
                                $state ?? [],
                                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
                            ))
                            ->fontFamily('mono')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}

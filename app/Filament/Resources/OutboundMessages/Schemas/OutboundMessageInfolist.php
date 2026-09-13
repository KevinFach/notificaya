<?php

namespace App\Filament\Resources\OutboundMessages\Schemas;

use App\Enums\NotificationStatus;
use App\Enums\NotificationTrigger;
use App\Models\OutboundMessage;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class OutboundMessageInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Mensaje')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('destinatario')->label('Destinatario')->placeholder('—'),
                        TextEntry::make('numero')->label('Número')->copyable(),
                        TextEntry::make('trigger')
                            ->label('Disparador')
                            ->badge()
                            ->formatStateUsing(fn (NotificationTrigger $state) => $state->label()),
                        TextEntry::make('cuerpo')
                            ->label('Texto enviado')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Entrega')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (NotificationStatus $state) => $state->label())
                            ->color(fn (NotificationStatus $state) => $state->color()),
                        TextEntry::make('scheduled_for')
                            ->label('Programado para')
                            ->dateTime('d/m/Y H:i')
                            ->timezone(fn (OutboundMessage $record) => $record->origin?->timezone)
                            ->placeholder('Inmediato'),
                        TextEntry::make('fastsms_msg_id')->label('msg_id de FastSMS')->copyable()->placeholder('—'),
                        TextEntry::make('fastsms_status')->label('Estado en FastSMS')->placeholder('—'),
                        TextEntry::make('attempts')->label('Intentos'),
                        TextEntry::make('dispatched_at')->label('Entregado')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('synced_at')->label('Última consulta')->dateTime('d/m/Y H:i')->placeholder('—'),
                        TextEntry::make('error_message')
                            ->label('Detalle')
                            ->placeholder('Sin incidencias')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Origen del mensaje')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('origin.name')->label('Negocio'),
                        TextEntry::make('appointment.lookup_code')->label('Cita')->badge(),
                        TextEntry::make('notificationRule.name')->label('Regla')->placeholder('—'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\WebhookDeliveries\Schemas;

use App\Enums\WebhookDeliveryStatus;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class WebhookDeliveryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Entrega')
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')->label('Recibido')->dateTime('d/m/Y H:i:s'),
                        TextEntry::make('origin.name')->label('Origen')->placeholder('—'),
                        TextEntry::make('event')->label('Evento')->badge(),
                        TextEntry::make('lookup_code')->label('Código')->placeholder('—'),
                        TextEntry::make('external_event_id')->label('X-RY-EVENT-ID')->placeholder('—'),
                        TextEntry::make('ip')->label('IP')->placeholder('—'),
                        TextEntry::make('signature_valid')
                            ->label('Firma')
                            ->badge()
                            ->formatStateUsing(fn (bool $state) => $state ? 'Válida' : 'Inválida')
                            ->color(fn (bool $state) => $state ? 'success' : 'danger'),
                        TextEntry::make('status')
                            ->label('Estado')
                            ->badge()
                            ->formatStateUsing(fn (WebhookDeliveryStatus $state) => $state->label())
                            ->color(fn (WebhookDeliveryStatus $state) => $state->color()),
                        TextEntry::make('processed_at')->label('Procesado')->dateTime('d/m/Y H:i:s')->placeholder('—'),
                        TextEntry::make('error_message')
                            ->label('Detalle')
                            ->placeholder('Sin incidencias')
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Cuerpo recibido')
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

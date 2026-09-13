<?php

namespace App\Filament\Resources\WebhookDeliveries\Tables;

use App\Enums\WebhookDeliveryStatus;
use App\Jobs\ProcessReservaYaEvent;
use App\Models\WebhookDelivery;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class WebhookDeliveriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Recibido')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('origin.name')
                    ->label('Origen')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('event')
                    ->label('Evento')
                    ->badge()
                    ->searchable(),
                TextColumn::make('lookup_code')
                    ->label('Código')
                    ->searchable()
                    ->placeholder('—'),
                IconColumn::make('signature_valid')
                    ->label('Firma')
                    ->boolean(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (WebhookDeliveryStatus $state) => $state->label())
                    ->color(fn (WebhookDeliveryStatus $state) => $state->color()),
                TextColumn::make('error_message')
                    ->label('Detalle')
                    ->limit(40)
                    ->tooltip(fn (WebhookDelivery $record) => $record->error_message)
                    ->placeholder('—'),
                TextColumn::make('ip')
                    ->label('IP')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('origin')
                    ->label('Origen')
                    ->relationship('origin', 'name'),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->multiple()
                    ->options(collect(WebhookDeliveryStatus::cases())
                        ->mapWithKeys(fn (WebhookDeliveryStatus $status) => [$status->value => $status->label()])
                        ->all()),
                TernaryFilter::make('signature_valid')->label('Firma válida'),
            ])
            ->recordActions([
                ViewAction::make(),
                self::reprocessAction(),
            ])
            ->emptyStateHeading('Nada ha llegado todavía')
            ->emptyStateDescription('Aquí queda registrada cada llamada de ReservaYa al webhook, incluidas las rechazadas.');
    }

    /**
     * Replays a delivery. Messages already created are not duplicated, so this
     * is safe to use on a delivery that failed halfway through.
     */
    private static function reprocessAction(): Action
    {
        return Action::make('reprocesar')
            ->label('Reprocesar')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->visible(fn (WebhookDelivery $record): bool => $record->signature_valid
                && $record->status !== WebhookDeliveryStatus::Procesado)
            ->action(function (WebhookDelivery $record): void {
                ProcessReservaYaEvent::dispatch($record);

                Notification::make()
                    ->title('Reprocesamiento encolado')
                    ->success()
                    ->send();
            });
    }
}

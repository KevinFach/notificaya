<?php

namespace App\Filament\Resources\OutboundMessages\Tables;

use App\Enums\NotificationStatus;
use App\Enums\NotificationTrigger;
use App\Jobs\CancelOutboundMessage;
use App\Jobs\RelayOutboundMessage;
use App\Models\OutboundMessage;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class OutboundMessagesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('appointment.lookup_code')
                    ->label('Cita')
                    ->badge()
                    ->searchable(),
                TextColumn::make('origin.name')
                    ->label('Origen')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('destinatario')
                    ->label('Destinatario')
                    ->searchable()
                    ->description(fn (OutboundMessage $record): string => $record->cuerpo ?? '')
                    ->wrap(),
                TextColumn::make('trigger')
                    ->label('Disparador')
                    ->badge()
                    ->formatStateUsing(fn (NotificationTrigger $state) => $state->label()),
                TextColumn::make('scheduled_for')
                    ->label('Programado para')
                    ->dateTime('d/m/Y H:i')
                    ->timezone(fn (OutboundMessage $record) => $record->origin?->timezone)
                    ->placeholder('Inmediato')
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (NotificationStatus $state) => $state->label())
                    ->color(fn (NotificationStatus $state) => $state->color()),
                TextColumn::make('fastsms_msg_id')
                    ->label('msg_id')
                    ->copyable()
                    ->limit(12)
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('error_message')
                    ->label('Detalle')
                    ->limit(40)
                    ->tooltip(fn (OutboundMessage $record) => $record->error_message)
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->since()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('origin')
                    ->label('Origen')
                    ->relationship('origin', 'name'),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->multiple()
                    ->options(collect(NotificationStatus::cases())
                        ->mapWithKeys(fn (NotificationStatus $status) => [$status->value => $status->label()])
                        ->all()),
                SelectFilter::make('trigger')
                    ->label('Disparador')
                    ->options(collect(NotificationTrigger::cases())
                        ->mapWithKeys(fn (NotificationTrigger $trigger) => [$trigger->value => $trigger->label()])
                        ->all()),
            ])
            ->recordActions([
                ViewAction::make(),
                self::resendAction(),
                self::cancelAction(),
            ])
            ->emptyStateHeading('Sin envíos todavía')
            ->emptyStateDescription('Cada SMS que NotificaYa entrega a FastSMS queda registrado aquí.');
    }

    private static function resendAction(): Action
    {
        return Action::make('reenviar')
            ->label('Reenviar')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('warning')
            ->requiresConfirmation()
            ->modalDescription('Si FastSMS quedó inalcanzable, el mensaje pudo haberse creado allá. Revísalo antes de reenviar para no duplicar el SMS.')
            ->visible(fn (OutboundMessage $record): bool => $record->status === NotificationStatus::Error
                && $record->fastsms_msg_id === null)
            ->action(function (OutboundMessage $record): void {
                RelayOutboundMessage::dispatch($record);

                Notification::make()
                    ->title('Reenvío encolado')
                    ->success()
                    ->send();
            });
    }

    private static function cancelAction(): Action
    {
        return Action::make('cancelar')
            ->label('Cancelar')
            ->icon(Heroicon::OutlinedXCircle)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (OutboundMessage $record): bool => ! $record->status->isFinal())
            ->action(function (OutboundMessage $record): void {
                CancelOutboundMessage::dispatch($record);

                Notification::make()
                    ->title('Cancelación encolada')
                    ->body('FastSMS solo cancela mensajes que aún no salieron.')
                    ->success()
                    ->send();
            });
    }
}

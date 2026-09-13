<?php

namespace App\Filament\Resources\NotificationRules\Tables;

use App\Enums\NotificationTrigger;
use App\Models\NotificationRule;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class NotificationRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('origin.name')
                    ->label('Origen')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('name')
                    ->label('Regla')
                    ->searchable(),
                TextColumn::make('trigger')
                    ->label('Disparador')
                    ->badge()
                    ->formatStateUsing(fn (NotificationTrigger $state) => $state->label()),
                TextColumn::make('offset_value')
                    ->label('Anticipación')
                    ->state(fn (NotificationRule $record): string => $record->trigger === NotificationTrigger::Recordatorio
                        ? "{$record->offset_value} {$record->offset_unit?->label()}"
                        : '—'),
                TextColumn::make('template')
                    ->label('Plantilla')
                    ->limit(60)
                    ->tooltip(fn (NotificationRule $record) => $record->template)
                    ->toggleable(),
                TextColumn::make('service_ids')
                    ->label('Servicios')
                    ->state(fn (NotificationRule $record): string => blank($record->service_ids)
                        ? 'Todos'
                        : implode(', ', $record->service_ids))
                    ->toggleable(),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->defaultSort('trigger')
            ->filters([
                SelectFilter::make('origin')
                    ->label('Origen')
                    ->relationship('origin', 'name'),
                SelectFilter::make('trigger')
                    ->label('Disparador')
                    ->options(collect(NotificationTrigger::cases())
                        ->mapWithKeys(fn (NotificationTrigger $trigger) => [$trigger->value => $trigger->label()])
                        ->all()),
                TernaryFilter::make('is_active')->label('Activa'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Sin reglas todavía')
            ->emptyStateDescription('Sin al menos una regla activa, las citas que lleguen no generan ningún SMS.');
    }
}

<?php

namespace App\Filament\Resources\Origins\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class OriginsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Negocio')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('slug')
                    ->label('X-RY-ORIGIN')
                    ->badge()
                    ->copyable()
                    ->searchable(),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean(),
                IconColumn::make('verify_with_api')
                    ->label('Verifica')
                    ->boolean()
                    ->toggleable(),
                TextColumn::make('timezone')
                    ->label('Zona horaria')
                    ->toggleable(),
                TextColumn::make('notification_rules_count')
                    ->label('Reglas')
                    ->counts('notificationRules')
                    ->alignRight(),
                TextColumn::make('appointments_count')
                    ->label('Citas')
                    ->counts('appointments')
                    ->alignRight(),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->filters([
                TernaryFilter::make('is_active')->label('Activo'),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ])
            ->emptyStateHeading('Todavía no hay orígenes')
            ->emptyStateDescription('Da de alta el negocio de ReservaYa cuyas citas quieres convertir en SMS.');
    }
}

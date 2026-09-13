<?php

namespace App\Filament\Resources\Origins\RelationManagers;

use App\Enums\NotificationTrigger;
use App\Filament\Resources\NotificationRules\Schemas\NotificationRuleForm;
use App\Models\NotificationRule;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class NotificationRulesRelationManager extends RelationManager
{
    protected static string $relationship = 'notificationRules';

    protected static ?string $title = 'Reglas de notificación';

    public function form(Schema $schema): Schema
    {
        return NotificationRuleForm::configure($schema, withOrigin: false);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
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
                    ->limit(50)
                    ->tooltip(fn (NotificationRule $record) => $record->template),
                IconColumn::make('is_active')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->headerActions([
                CreateAction::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}

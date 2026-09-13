<?php

namespace App\Filament\Resources\Appointments\Tables;

use App\Enums\AppointmentKind;
use App\Enums\AppointmentStatus;
use App\Models\Appointment;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AppointmentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('lookup_code')
                    ->label('Código')
                    ->badge()
                    ->copyable()
                    ->searchable(),
                TextColumn::make('origin.name')
                    ->label('Origen')
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->searchable()
                    ->description(fn (Appointment $record): ?string => $record->service_name),
                TextColumn::make('kind')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (AppointmentKind $state) => $state->label())
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('starts_at')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i')
                    ->timezone(fn (Appointment $record) => $record->origin?->timezone)
                    ->sortable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (AppointmentStatus $state) => $state->label())
                    ->color(fn (AppointmentStatus $state) => $state->color()),
                TextColumn::make('outbound_messages_count')
                    ->label('SMS')
                    ->counts('outboundMessages')
                    ->alignRight(),
                TextColumn::make('last_synced_at')
                    ->label('Última revisión')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('starts_at', 'desc')
            ->filters([
                SelectFilter::make('origin')
                    ->label('Origen')
                    ->relationship('origin', 'name'),
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(collect(AppointmentStatus::cases())
                        ->mapWithKeys(fn (AppointmentStatus $status) => [$status->value => $status->label()])
                        ->all()),
                SelectFilter::make('kind')
                    ->label('Tipo')
                    ->options(collect(AppointmentKind::cases())
                        ->mapWithKeys(fn (AppointmentKind $kind) => [$kind->value => $kind->label()])
                        ->all()),
                Filter::make('proximas')
                    ->label('Solo próximas')
                    ->query(fn (Builder $query) => $query->where('starts_at', '>', now())),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->emptyStateHeading('Sin citas recibidas')
            ->emptyStateDescription('Aquí aparecen las citas que ReservaYa manda al webhook.');
    }
}

<?php

namespace App\Filament\Resources\Origins;

use App\Filament\Resources\Origins\Pages\CreateOrigin;
use App\Filament\Resources\Origins\Pages\EditOrigin;
use App\Filament\Resources\Origins\Pages\ListOrigins;
use App\Filament\Resources\Origins\RelationManagers\NotificationRulesRelationManager;
use App\Filament\Resources\Origins\Schemas\OriginForm;
use App\Filament\Resources\Origins\Tables\OriginsTable;
use App\Models\Origin;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OriginResource extends Resource
{
    protected static ?string $model = Origin::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static string|UnitEnum|null $navigationGroup = 'Configuración';

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'origen';

    protected static ?string $pluralModelLabel = 'orígenes';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return OriginForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OriginsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            NotificationRulesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOrigins::route('/'),
            'create' => CreateOrigin::route('/create'),
            'edit' => EditOrigin::route('/{record}/edit'),
        ];
    }
}

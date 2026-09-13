<?php

namespace App\Filament\Resources\OutboundMessages;

use App\Enums\NotificationStatus;
use App\Filament\Resources\OutboundMessages\Pages\ListOutboundMessages;
use App\Filament\Resources\OutboundMessages\Pages\ViewOutboundMessage;
use App\Filament\Resources\OutboundMessages\Schemas\OutboundMessageInfolist;
use App\Filament\Resources\OutboundMessages\Tables\OutboundMessagesTable;
use App\Models\OutboundMessage;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class OutboundMessageResource extends Resource
{
    protected static ?string $model = OutboundMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static string|UnitEnum|null $navigationGroup = 'Operación';

    protected static ?string $recordTitleAttribute = 'destinatario';

    protected static ?string $modelLabel = 'envío';

    protected static ?string $pluralModelLabel = 'envíos';

    protected static ?int $navigationSort = 2;

    public static function infolist(Schema $schema): Schema
    {
        return OutboundMessageInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OutboundMessagesTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['origin', 'appointment']);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(mixed $record): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $failed = static::getModel()::query()->where('status', NotificationStatus::Error->value)->count();

        return $failed > 0 ? (string) $failed : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOutboundMessages::route('/'),
            'view' => ViewOutboundMessage::route('/{record}'),
        ];
    }
}

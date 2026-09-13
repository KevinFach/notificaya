<?php

namespace App\Filament\Resources\Appointments\RelationManagers;

use App\Filament\Resources\OutboundMessages\Tables\OutboundMessagesTable;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;

class OutboundMessagesRelationManager extends RelationManager
{
    protected static string $relationship = 'outboundMessages';

    protected static ?string $title = 'SMS de esta cita';

    public function table(Table $table): Table
    {
        return OutboundMessagesTable::configure($table)
            ->recordTitleAttribute('destinatario')
            ->filters([]);
    }
}

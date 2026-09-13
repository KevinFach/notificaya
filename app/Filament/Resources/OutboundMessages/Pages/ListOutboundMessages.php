<?php

namespace App\Filament\Resources\OutboundMessages\Pages;

use App\Filament\Resources\OutboundMessages\OutboundMessageResource;
use Filament\Resources\Pages\ListRecords;

class ListOutboundMessages extends ListRecords
{
    protected static string $resource = OutboundMessageResource::class;
}

<?php

namespace App\Filament\Widgets;

use App\Enums\NotificationStatus;
use App\Enums\WebhookDeliveryStatus;
use App\Models\OutboundMessage;
use App\Models\WebhookDelivery;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class RelayOverview extends StatsOverviewWidget
{
    protected ?string $heading = 'Puente ReservaYa → FastSMS';

    protected function getStats(): array
    {
        $sentToday = OutboundMessage::query()
            ->where('status', NotificationStatus::Enviado->value)
            ->whereDate('synced_at', today())
            ->count();

        $scheduled = OutboundMessage::query()
            ->where('status', NotificationStatus::Programado->value)
            ->count();

        $failed = OutboundMessage::query()
            ->where('status', NotificationStatus::Error->value)
            ->count();

        $rejected = WebhookDelivery::query()
            ->where('signature_valid', false)
            ->orWhere('status', WebhookDeliveryStatus::Error->value)
            ->count();

        return [
            Stat::make('Enviados hoy', $sentToday)
                ->description('SMS que FastSMS confirmó')
                ->color('success'),
            Stat::make('Programados', $scheduled)
                ->description('Recordatorios esperando su hora en FastSMS')
                ->color('info'),
            Stat::make('En error', $failed)
                ->description('Requieren revisión manual')
                ->color($failed > 0 ? 'danger' : 'gray'),
            Stat::make('Webhooks con problema', $rejected)
                ->description('Firma inválida o procesamiento fallido')
                ->color($rejected > 0 ? 'warning' : 'gray'),
        ];
    }
}

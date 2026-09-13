<?php

namespace App\Models;

use App\Enums\NotificationStatus;
use App\Enums\NotificationTrigger;
use Database\Factories\OutboundMessageFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'origin_id',
    'appointment_id',
    'notification_rule_id',
    'trigger',
    'destinatario',
    'numero',
    'cuerpo',
    'scheduled_for',
    'status',
    'fastsms_msg_id',
    'fastsms_status',
    'error_message',
    'attempts',
    'dispatched_at',
    'synced_at',
])]
class OutboundMessage extends Model
{
    /** @use HasFactory<OutboundMessageFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'pendiente',
        'attempts' => 0,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger' => NotificationTrigger::class,
            'status' => NotificationStatus::class,
            'numero' => 'encrypted',
            'cuerpo' => 'encrypted',
            'scheduled_for' => 'datetime',
            'dispatched_at' => 'datetime',
            'synced_at' => 'datetime',
            'attempts' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Origin, $this>
     */
    public function origin(): BelongsTo
    {
        return $this->belongsTo(Origin::class);
    }

    /**
     * @return BelongsTo<Appointment, $this>
     */
    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    /**
     * @return BelongsTo<NotificationRule, $this>
     */
    public function notificationRule(): BelongsTo
    {
        return $this->belongsTo(NotificationRule::class);
    }

    /**
     * Messages already handed to FastSMS whose outcome is still unknown.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    #[Scope]
    protected function awaitingFastSmsOutcome(Builder $query): Builder
    {
        return $query->whereNotNull('fastsms_msg_id')->whereIn('status', [
            NotificationStatus::EntregadoAFastSms->value,
            NotificationStatus::Programado->value,
        ]);
    }

    /**
     * Messages that have not gone out yet and can still be pulled back.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    #[Scope]
    protected function cancellable(Builder $query): Builder
    {
        return $query->whereIn('status', [
            NotificationStatus::Pendiente->value,
            NotificationStatus::EntregadoAFastSms->value,
            NotificationStatus::Programado->value,
            NotificationStatus::Error->value,
        ]);
    }

    public function isScheduled(): bool
    {
        return $this->scheduled_for !== null;
    }
}

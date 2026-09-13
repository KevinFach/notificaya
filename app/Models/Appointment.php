<?php

namespace App\Models;

use App\Enums\AppointmentKind;
use App\Enums\AppointmentStatus;
use App\Enums\NotificationStatus;
use Database\Factories\AppointmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'origin_id',
    'lookup_code',
    'kind',
    'status',
    'customer_name',
    'customer_phone',
    'customer_email',
    'service_external_id',
    'service_name',
    'service_slug',
    'event_name',
    'event_type',
    'responsable_email',
    'starts_at',
    'ends_at',
    'payload',
    'last_synced_at',
])]
class Appointment extends Model
{
    /** @use HasFactory<AppointmentFactory> */
    use HasFactory;

    protected $attributes = [
        'kind' => 'cita',
        'status' => 'confirmed',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => AppointmentKind::class,
            'status' => AppointmentStatus::class,
            'customer_phone' => 'encrypted',
            'customer_email' => 'encrypted',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'last_synced_at' => 'datetime',
            'payload' => 'array',
            'service_external_id' => 'integer',
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
     * @return HasMany<OutboundMessage, $this>
     */
    public function outboundMessages(): HasMany
    {
        return $this->hasMany(OutboundMessage::class);
    }

    /**
     * Appointments that still have SMS waiting to go out.
     *
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    #[Scope]
    protected function withPendingMessages(Builder $query): Builder
    {
        return $query->whereHas('outboundMessages', fn (Builder $messages) => $messages->whereIn('status', [
            NotificationStatus::Pendiente->value,
            NotificationStatus::EntregadoAFastSms->value,
            NotificationStatus::Programado->value,
        ]));
    }

    public function isCancelled(): bool
    {
        return $this->status === AppointmentStatus::Cancelled;
    }

    public function displayName(): string
    {
        return $this->event_name ?: $this->service_name ?: $this->lookup_code;
    }
}

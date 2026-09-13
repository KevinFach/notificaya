<?php

namespace App\Models;

use App\Enums\WebhookDeliveryStatus;
use Database\Factories\WebhookDeliveryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'origin_id',
    'event',
    'lookup_code',
    'external_event_id',
    'payload_hash',
    'payload',
    'signature_valid',
    'ip',
    'status',
    'error_message',
    'processed_at',
])]
class WebhookDelivery extends Model
{
    /** @use HasFactory<WebhookDeliveryFactory> */
    use HasFactory;

    protected $attributes = [
        'status' => 'recibido',
        'signature_valid' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'signature_valid' => 'boolean',
            'status' => WebhookDeliveryStatus::class,
            'processed_at' => 'datetime',
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
     * @param  array<string, mixed>  $payload
     */
    public static function hashPayload(array $payload): string
    {
        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function markProcessed(): void
    {
        $this->update([
            'status' => WebhookDeliveryStatus::Procesado,
            'processed_at' => now(),
            'error_message' => null,
        ]);
    }

    public function markIgnored(string $reason): void
    {
        $this->update([
            'status' => WebhookDeliveryStatus::Ignorado,
            'processed_at' => now(),
            'error_message' => $reason,
        ]);
    }

    public function markFailed(string $message): void
    {
        $this->update([
            'status' => WebhookDeliveryStatus::Error,
            'processed_at' => now(),
            'error_message' => $message,
        ]);
    }
}

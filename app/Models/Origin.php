<?php

namespace App\Models;

use Database\Factories\OriginFactory;
use Illuminate\Database\Eloquent\Attributes\Boot;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'name',
    'slug',
    'is_active',
    'reservaya_base_url',
    'reservaya_token',
    'verify_with_api',
    'webhook_secret',
    'fastsms_base_url',
    'fastsms_token',
    'timezone',
    'fastsms_timezone',
    'phone_prefix',
])]
class Origin extends Model
{
    /** @use HasFactory<OriginFactory> */
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
        'verify_with_api' => false,
        'timezone' => 'America/Mexico_City',
        'phone_prefix' => '+52',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'verify_with_api' => 'boolean',
            'reservaya_token' => 'encrypted',
            'fastsms_token' => 'encrypted',
            'webhook_secret' => 'encrypted',
        ];
    }

    #[Boot]
    protected static function assignWebhookSecret(): void
    {
        static::creating(function (self $origin): void {
            $origin->webhook_secret ??= static::generateWebhookSecret();
            $origin->slug ??= Str::slug($origin->name ?? '');
        });
    }

    public static function generateWebhookSecret(): string
    {
        return Str::random(64);
    }

    /**
     * @return HasMany<NotificationRule, $this>
     */
    public function notificationRules(): HasMany
    {
        return $this->hasMany(NotificationRule::class);
    }

    /**
     * @return HasMany<Appointment, $this>
     */
    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    /**
     * @return HasMany<OutboundMessage, $this>
     */
    public function outboundMessages(): HasMany
    {
        return $this->hasMany(OutboundMessage::class);
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function webhookDeliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function reservayaBaseUrl(): string
    {
        return rtrim($this->reservaya_base_url ?: config('notificaya.reservaya.base_url'), '/');
    }

    public function fastsmsBaseUrl(): string
    {
        return rtrim($this->fastsms_base_url ?: config('notificaya.fastsms.base_url'), '/');
    }

    /**
     * Timezone FastSMS reads fecha_envio/hora_envio in. Defaults to the
     * timezone of the business, which is only correct when both systems run
     * with the same application timezone.
     */
    public function fastsmsTimezone(): string
    {
        return $this->fastsms_timezone ?: $this->timezone;
    }

    public function isFullyConfigured(): bool
    {
        return filled($this->fastsms_token) && filled($this->fastsmsBaseUrl());
    }
}

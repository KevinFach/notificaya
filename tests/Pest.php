<?php

use App\Models\Origin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

/**
 * Stand-in for pest-plugin-livewire, which is not installed here.
 *
 * @param  array<string, mixed>  $params
 */
function livewire(string $component, array $params = []): Livewire\Features\SupportTesting\Testable
{
    return Livewire\Livewire::test($component, $params);
}

/**
 * Build the headers ReservaYa has to send for a payload to be accepted.
 *
 * @param  array<string, mixed>  $payload
 * @return array<string, string>
 */
function reservayaHeaders(Origin $origin, array $payload, ?string $eventId = null): array
{
    // Must match byte-for-byte what postJson() puts on the wire.
    $body = json_encode($payload);

    return array_filter([
        'X-RY-ORIGIN' => $origin->slug,
        'X-RY-SIGNATURE' => 'sha256='.hash_hmac('sha256', $body, (string) $origin->webhook_secret),
        'X-RY-EVENT-ID' => $eventId,
    ]);
}

/**
 * The body of an appointment.created event, shaped like ReservaYa sends it.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function appointmentCreatedPayload(array $overrides = []): array
{
    return [
        'event' => 'appointment.created',
        'data' => array_merge([
            'lookup_code' => '40821',
            'status' => 'confirmed',
            'service' => ['id' => 11, 'name' => 'Corte de cabello', 'slug' => 'corte-de-cabello'],
            'customer_name' => 'Ana López',
            'customer_email' => 'ana@example.com',
            'customer_phone' => '5512345678',
            'starts_at' => now()->addDays(3)->setTime(9, 0)->toIso8601String(),
            'ends_at' => now()->addDays(3)->setTime(9, 30)->toIso8601String(),
        ], $overrides),
    ];
}

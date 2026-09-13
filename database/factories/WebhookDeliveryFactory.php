<?php

namespace Database\Factories;

use App\Enums\WebhookDeliveryStatus;
use App\Models\Origin;
use App\Models\WebhookDelivery;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WebhookDelivery>
 */
class WebhookDeliveryFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $payload = [
            'event' => 'appointment.created',
            'data' => ['lookup_code' => (string) fake()->numberBetween(10000, 99999)],
        ];

        return [
            'origin_id' => Origin::factory(),
            'event' => $payload['event'],
            'lookup_code' => $payload['data']['lookup_code'],
            'external_event_id' => fake()->uuid(),
            'payload_hash' => WebhookDelivery::hashPayload($payload),
            'payload' => $payload,
            'signature_valid' => true,
            'ip' => fake()->ipv4(),
            'status' => WebhookDeliveryStatus::Recibido,
            'error_message' => null,
            'processed_at' => null,
        ];
    }
}

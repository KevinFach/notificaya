<?php

use App\Enums\WebhookDeliveryStatus;
use App\Jobs\ProcessReservaYaEvent;
use App\Models\Appointment;
use App\Models\NotificationRule;
use App\Models\Origin;
use App\Models\OutboundMessage;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->origin = Origin::factory()->create(['slug' => 'barberia-centro']);
});

it('accepts a correctly signed event and queues it', function () {
    Queue::fake();

    $payload = appointmentCreatedPayload();

    $this->postJson(route('api.v1.reservaya.eventos'), $payload, reservayaHeaders($this->origin, $payload))
        ->assertStatus(202)
        ->assertJsonPath('status', 'recibido');

    $delivery = WebhookDelivery::sole();

    expect($delivery->origin_id)->toBe($this->origin->id)
        ->and($delivery->event)->toBe('appointment.created')
        ->and($delivery->lookup_code)->toBe('40821')
        ->and($delivery->signature_valid)->toBeTrue();

    Queue::assertPushed(ProcessReservaYaEvent::class);
});

it('rejects an event whose signature does not match', function () {
    Queue::fake();

    $payload = appointmentCreatedPayload();
    $headers = reservayaHeaders($this->origin, $payload);
    $headers['X-RY-SIGNATURE'] = 'sha256='.str_repeat('a', 64);

    $this->postJson(route('api.v1.reservaya.eventos'), $payload, $headers)
        ->assertUnauthorized()
        ->assertJsonPath('error', 'Firma inválida.');

    Queue::assertNothingPushed();

    expect(WebhookDelivery::sole())
        ->signature_valid->toBeFalse()
        ->status->toBe(WebhookDeliveryStatus::Ignorado);
});

it('rejects an event for an unknown or inactive origin', function () {
    Queue::fake();

    $inactive = Origin::factory()->inactive()->create(['slug' => 'pausado']);
    $payload = appointmentCreatedPayload();

    $this->postJson(route('api.v1.reservaya.eventos'), $payload, reservayaHeaders($inactive, $payload))
        ->assertUnauthorized()
        ->assertJsonPath('error', 'Origen no autorizado o inactivo.');

    $headers = reservayaHeaders($this->origin, $payload);
    unset($headers['X-RY-ORIGIN']);

    $this->postJson(route('api.v1.reservaya.eventos'), $payload, $headers)
        ->assertUnauthorized();

    Queue::assertNothingPushed();
});

it('rejects a payload that is missing required fields', function () {
    Queue::fake();

    $payload = ['event' => 'appointment.created', 'data' => ['lookup_code' => '40821']];

    $this->postJson(route('api.v1.reservaya.eventos'), $payload, reservayaHeaders($this->origin, $payload))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('data.starts_at');

    Queue::assertNothingPushed();
});

it('does not create a second SMS when ReservaYa retries the same event', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/api/v1/messages' => Http::response(['msg_id' => 'ecd6-1', 'status' => 'por_enviar'], 201),
    ]);

    NotificationRule::factory()->for($this->origin)->create();

    $payload = appointmentCreatedPayload();
    $headers = reservayaHeaders($this->origin, $payload, eventId: '9f3c-retry');

    $this->postJson(route('api.v1.reservaya.eventos'), $payload, $headers)->assertStatus(202);

    $this->postJson(route('api.v1.reservaya.eventos'), $payload, $headers)
        ->assertSuccessful()
        ->assertJsonPath('status', 'duplicado');

    expect(WebhookDelivery::count())->toBe(1)
        ->and(Appointment::count())->toBe(1)
        ->and(OutboundMessage::count())->toBe(1);
});

it('deduplicates an identical body even without an event id', function () {
    Queue::fake();

    $payload = appointmentCreatedPayload();
    $headers = reservayaHeaders($this->origin, $payload);

    $this->postJson(route('api.v1.reservaya.eventos'), $payload, $headers)->assertStatus(202);
    $this->postJson(route('api.v1.reservaya.eventos'), $payload, $headers)
        ->assertSuccessful()
        ->assertJsonPath('status', 'duplicado');

    expect(WebhookDelivery::count())->toBe(1);
    Queue::assertPushed(ProcessReservaYaEvent::class, 1);
});

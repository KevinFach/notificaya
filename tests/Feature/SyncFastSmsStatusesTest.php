<?php

use App\Enums\NotificationStatus;
use App\Models\Appointment;
use App\Models\Origin;
use App\Models\OutboundMessage;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->origin = Origin::factory()->create(['fastsms_base_url' => 'https://fastsms.test']);
});

function awaitingMessage(Origin $origin, array $attributes = []): OutboundMessage
{
    $appointment = Appointment::factory()->for($origin)->create();

    return OutboundMessage::factory()->for($appointment)->handedToFastSms()->create(array_merge([
        'origin_id' => $origin->id,
        'fastsms_msg_id' => 'msg-1',
    ], $attributes));
}

it('maps the FastSMS outcome onto the local status', function (string $remote, NotificationStatus $expected) {
    Http::fake(['*/api/v1/messages/msg-1' => Http::response(['msg_id' => 'msg-1', 'status' => $remote])]);

    $message = awaitingMessage($this->origin);

    $this->artisan('notificaya:sync-fastsms')->assertSuccessful();

    expect($message->refresh())
        ->status->toBe($expected)
        ->fastsms_status->toBe($remote)
        ->synced_at->not->toBeNull();
})->with([
    'enviado' => ['enviado', NotificationStatus::Enviado],
    'cancelado' => ['cancelado', NotificationStatus::Cancelado],
    'error' => ['error', NotificationStatus::Error],
    'en cola' => ['en_cola', NotificationStatus::EntregadoAFastSms],
]);

it('stores the reason FastSMS reports for a failed message', function () {
    Http::fake(['*/api/v1/messages/msg-1' => Http::response([
        'status' => 'error',
        'error' => 'Sin señal GSM',
    ])]);

    $message = awaitingMessage($this->origin);

    $this->artisan('notificaya:sync-fastsms')->assertSuccessful();

    expect($message->refresh())
        ->status->toBe(NotificationStatus::Error)
        ->error_message->toBe('Sin señal GSM');
});

it('flags a message FastSMS no longer knows', function () {
    Http::fake(['*/api/v1/messages/msg-1' => Http::response(['error' => 'Mensaje no encontrado'], 404)]);

    $message = awaitingMessage($this->origin);

    $this->artisan('notificaya:sync-fastsms')->assertSuccessful();

    expect($message->refresh())
        ->status->toBe(NotificationStatus::Error)
        ->error_message->toContain('ya no reconoce');
});

it('leaves the message untouched when FastSMS is unreachable', function () {
    Http::fake(['*/api/v1/messages/msg-1' => Http::failedConnection()]);

    $message = awaitingMessage($this->origin);

    $this->artisan('notificaya:sync-fastsms')->assertSuccessful();

    expect($message->refresh())
        ->status->toBe(NotificationStatus::EntregadoAFastSms)
        ->synced_at->toBeNull();
});

it('skips messages that already reached a final state', function () {
    Http::fake();

    awaitingMessage($this->origin, ['status' => NotificationStatus::Enviado]);

    $this->artisan('notificaya:sync-fastsms')->assertSuccessful();

    Http::assertNothingSent();
});

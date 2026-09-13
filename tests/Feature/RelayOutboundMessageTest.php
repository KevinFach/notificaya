<?php

use App\Enums\NotificationStatus;
use App\Jobs\RelayOutboundMessage;
use App\Models\Appointment;
use App\Models\Origin;
use App\Models\OutboundMessage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->origin = Origin::factory()->create([
        'fastsms_base_url' => 'https://fastsms.test',
        'fastsms_token' => 'token-de-prueba',
        'timezone' => 'America/Mexico_City',
    ]);
});

function messageFor(Origin $origin, array $attributes = []): OutboundMessage
{
    $appointment = Appointment::factory()->for($origin)->create();

    return OutboundMessage::factory()->for($appointment)->create(array_merge([
        'origin_id' => $origin->id,
        'numero' => '+525512345678',
        'cuerpo' => 'Tu cita quedó agendada.',
        'destinatario' => 'Ana López',
    ], $attributes));
}

it('sends an immediate message without a schedule', function () {
    Http::fake(['*/api/v1/messages' => Http::response(['msg_id' => 'abc-123', 'status' => 'por_enviar'], 201)]);

    $message = messageFor($this->origin);

    RelayOutboundMessage::dispatchSync($message);

    Http::assertSent(function ($request) {
        expect($request->url())->toBe('https://fastsms.test/api/v1/messages')
            ->and($request->header('Authorization'))->toBe(['Bearer token-de-prueba'])
            ->and($request->data())->toBe([
                'nombre' => 'Ana López',
                'numero' => '+525512345678',
                'mensaje' => 'Tu cita quedó agendada.',
            ]);

        return true;
    });

    expect($message->refresh())
        ->status->toBe(NotificationStatus::EntregadoAFastSms)
        ->fastsms_msg_id->toBe('abc-123')
        ->fastsms_status->toBe('por_enviar')
        ->attempts->toBe(1)
        ->dispatched_at->not->toBeNull();
});

it('converts the schedule to the FastSMS timezone across a day boundary', function () {
    Http::fake(['*/api/v1/messages' => Http::response(['msg_id' => 'abc-456', 'status' => 'programado'], 201)]);

    // 2026-09-03 23:30 in Mexico City is 2026-09-04 05:30 UTC; the reminder 24h
    // earlier must go out as 2026-09-02, not 2026-09-03.
    $message = messageFor($this->origin, [
        'scheduled_for' => Carbon::parse('2026-09-02T23:30:00', 'America/Mexico_City')->utc(),
    ]);

    RelayOutboundMessage::dispatchSync($message);

    Http::assertSent(function ($request) {
        expect($request->data())->toMatchArray([
            'fecha_envio' => '2026-09-02',
            'hora_envio' => '23:30',
        ]);

        return true;
    });

    expect($message->refresh())
        ->status->toBe(NotificationStatus::Programado)
        ->fastsms_msg_id->toBe('abc-456');
});

it('honours a FastSMS timezone that differs from the business one', function () {
    Http::fake(['*/api/v1/messages' => Http::response(['msg_id' => 'abc-789', 'status' => 'programado'], 201)]);

    $this->origin->update(['fastsms_timezone' => 'UTC']);

    $message = messageFor($this->origin->refresh(), [
        'scheduled_for' => Carbon::parse('2026-09-02T23:30:00', 'America/Mexico_City')->utc(),
    ]);

    RelayOutboundMessage::dispatchSync($message);

    Http::assertSent(function ($request) {
        expect($request->data())->toMatchArray([
            'fecha_envio' => '2026-09-03',
            'hora_envio' => '05:30',
        ]);

        return true;
    });
});

it('records the validation error FastSMS returns without retrying', function () {
    Http::fake(['*/api/v1/messages' => Http::response([
        'message' => 'The numero field is required.',
        'errors' => ['numero' => ['The numero field is required.']],
    ], 422)]);

    $message = messageFor($this->origin);

    RelayOutboundMessage::dispatchSync($message);

    expect($message->refresh())
        ->status->toBe(NotificationStatus::Error)
        ->error_message->toBe('The numero field is required.')
        ->fastsms_msg_id->toBeNull();

    Http::assertSentCount(1);
});

it('flags an unreachable FastSMS for manual review instead of resending', function () {
    Http::fake(['*/api/v1/messages' => Http::failedConnection()]);

    $message = messageFor($this->origin);

    RelayOutboundMessage::dispatchSync($message);

    expect($message->refresh())
        ->status->toBe(NotificationStatus::Error)
        ->fastsms_msg_id->toBeNull()
        ->error_message->toContain('pudo haberse creado');
});

it('does not relay a message that already reached FastSMS', function () {
    Http::fake();

    $message = messageFor($this->origin, [
        'status' => NotificationStatus::EntregadoAFastSms,
        'fastsms_msg_id' => 'ya-entregado',
    ]);

    RelayOutboundMessage::dispatchSync($message);

    Http::assertNothingSent();
});

it('errors when the origin has no FastSMS credentials', function () {
    Http::fake();

    $this->origin->update(['fastsms_token' => null]);

    $message = messageFor($this->origin->refresh());

    RelayOutboundMessage::dispatchSync($message);

    expect($message->refresh())
        ->status->toBe(NotificationStatus::Error)
        ->error_message->toContain('credenciales de FastSMS');

    Http::assertNothingSent();
});

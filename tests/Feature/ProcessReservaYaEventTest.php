<?php

use App\Enums\AppointmentKind;
use App\Enums\AppointmentStatus;
use App\Enums\NotificationStatus;
use App\Enums\NotificationTrigger;
use App\Enums\ReminderOffsetUnit;
use App\Enums\WebhookDeliveryStatus;
use App\Jobs\ProcessReservaYaEvent;
use App\Jobs\RelayOutboundMessage;
use App\Models\Appointment;
use App\Models\NotificationRule;
use App\Models\Origin;
use App\Models\OutboundMessage;
use App\Models\WebhookDelivery;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->origin = Origin::factory()->create(['slug' => 'barberia-centro']);
});

/**
 * @param  array<string, mixed>  $payload
 */
function deliveryFor(Origin $origin, array $payload): WebhookDelivery
{
    return WebhookDelivery::create([
        'origin_id' => $origin->id,
        'event' => $payload['event'],
        'lookup_code' => $payload['data']['lookup_code'] ?? null,
        'payload_hash' => WebhookDelivery::hashPayload($payload),
        'payload' => $payload,
        'signature_valid' => true,
    ]);
}

it('stores the appointment and plans one message per applicable rule', function () {
    Bus::fake(RelayOutboundMessage::class);

    NotificationRule::factory()->for($this->origin)->create(['name' => 'Confirmación']);
    NotificationRule::factory()->for($this->origin)->reminder(24)->create();
    NotificationRule::factory()->for($this->origin)->cancellation()->create();

    ProcessReservaYaEvent::dispatchSync(deliveryFor($this->origin, appointmentCreatedPayload()));

    $appointment = Appointment::sole();

    expect($appointment->lookup_code)->toBe('40821')
        ->and($appointment->kind)->toBe(AppointmentKind::Cita)
        ->and($appointment->status)->toBe(AppointmentStatus::Confirmed)
        ->and($appointment->customer_phone)->toBe('5512345678')
        ->and($appointment->service_external_id)->toBe(11)
        ->and($appointment->service_name)->toBe('Corte de cabello');

    expect(OutboundMessage::pluck('trigger')->all())->toEqualCanonicalizing([
        NotificationTrigger::CitaCreada,
        NotificationTrigger::Recordatorio,
    ]);

    expect(WebhookDelivery::sole()->status)->toBe(WebhookDeliveryStatus::Procesado);

    Bus::assertDispatchedTimes(RelayOutboundMessage::class, 2);
});

it('renders the template with the appointment data', function () {
    Bus::fake(RelayOutboundMessage::class);

    NotificationRule::factory()->for($this->origin)->create([
        'template' => 'Hola {cliente}, tu cita de {servicio} es el {fecha} a las {hora}. Código {codigo}.',
    ]);

    $payload = appointmentCreatedPayload([
        'starts_at' => '2026-09-02T09:30:00-06:00',
    ]);

    ProcessReservaYaEvent::dispatchSync(deliveryFor($this->origin, $payload));

    expect(OutboundMessage::sole())
        ->cuerpo->toBe('Hola Ana López, tu cita de Corte de cabello es el miércoles 2 de septiembre a las 09:30. Código 40821.')
        ->numero->toBe('+525512345678')
        ->destinatario->toBe('Ana López')
        ->status->toBe(NotificationStatus::Pendiente);
});

it('skips a reminder whose moment already passed', function () {
    Bus::fake(RelayOutboundMessage::class);

    NotificationRule::factory()->for($this->origin)->reminder(24)->create();

    $payload = appointmentCreatedPayload([
        'starts_at' => now()->addHours(2)->toIso8601String(),
    ]);

    ProcessReservaYaEvent::dispatchSync(deliveryFor($this->origin, $payload));

    expect(OutboundMessage::count())->toBe(0);
    Bus::assertNothingDispatched();
});

it('skips a rule restricted to other services', function () {
    Bus::fake(RelayOutboundMessage::class);

    NotificationRule::factory()->for($this->origin)->forServices([99])->create();
    NotificationRule::factory()->for($this->origin)->forServices([11])->reminder(2)->create();

    ProcessReservaYaEvent::dispatchSync(deliveryFor($this->origin, appointmentCreatedPayload()));

    expect(OutboundMessage::sole()->trigger)->toBe(NotificationTrigger::Recordatorio);
});

it('skips an appointment with no usable phone number', function () {
    Bus::fake(RelayOutboundMessage::class);

    NotificationRule::factory()->for($this->origin)->create();

    ProcessReservaYaEvent::dispatchSync(deliveryFor($this->origin, appointmentCreatedPayload([
        'customer_phone' => null,
    ])));

    expect(Appointment::count())->toBe(1)
        ->and(OutboundMessage::count())->toBe(0);
});

it('ignores an inactive rule', function () {
    Bus::fake(RelayOutboundMessage::class);

    NotificationRule::factory()->for($this->origin)->inactive()->create();

    ProcessReservaYaEvent::dispatchSync(deliveryFor($this->origin, appointmentCreatedPayload()));

    expect(OutboundMessage::count())->toBe(0);
});

it('stores a scheduled event with its responsible party', function () {
    Bus::fake(RelayOutboundMessage::class);

    NotificationRule::factory()->for($this->origin)->event()->create();

    $payload = [
        'event' => 'event.created',
        'data' => [
            'lookup_code' => '40899',
            'status' => 'confirmed',
            'service_id' => 18,
            'event_name' => 'Firma de contrato',
            'event_type' => 'Firmas',
            'responsable_email' => 'notario@example.com',
            'customer_name' => 'Ana López',
            'customer_phone' => '5512345678',
            'starts_at' => now()->addDays(2)->setTime(9, 0)->toIso8601String(),
            'ends_at' => now()->addDays(2)->setTime(10, 0)->toIso8601String(),
        ],
    ];

    ProcessReservaYaEvent::dispatchSync(deliveryFor($this->origin, $payload));

    expect(Appointment::sole())
        ->kind->toBe(AppointmentKind::Evento)
        ->event_name->toBe('Firma de contrato')
        ->service_external_id->toBe(18)
        ->responsable_email->toBe('notario@example.com');

    expect(OutboundMessage::sole()->cuerpo)->toContain('Firma de contrato');
});

it('verifies the payload against ReservaYa when the origin asks for it', function () {
    Bus::fake(RelayOutboundMessage::class);
    Http::preventStrayRequests();

    $origin = Origin::factory()->verifyingWithApi()->create(['slug' => 'verificado']);
    NotificationRule::factory()->for($origin)->create();

    Http::fake([
        '*/api/v1/appointments/40821' => Http::response(['data' => [
            'lookup_code' => '40821',
            'status' => 'confirmed',
            'service' => ['id' => 11, 'name' => 'Corte premium', 'slug' => 'corte-premium'],
            'customer_name' => 'Ana Verificada',
            'customer_phone' => '5599887766',
            'starts_at' => now()->addDays(3)->setTime(11, 0)->toIso8601String(),
        ]]),
    ]);

    ProcessReservaYaEvent::dispatchSync(deliveryFor($origin, appointmentCreatedPayload()));

    expect(Appointment::sole())
        ->customer_name->toBe('Ana Verificada')
        ->service_name->toBe('Corte premium');
});

it('ignores a delivery whose code ReservaYa no longer knows', function () {
    Bus::fake(RelayOutboundMessage::class);
    Http::preventStrayRequests();

    $origin = Origin::factory()->verifyingWithApi()->create(['slug' => 'verificado']);
    NotificationRule::factory()->for($origin)->create();

    Http::fake(['*/api/v1/appointments/40821' => Http::response(['message' => 'No encontrado'], 404)]);

    ProcessReservaYaEvent::dispatchSync(deliveryFor($origin, appointmentCreatedPayload()));

    expect(Appointment::count())->toBe(0)
        ->and(WebhookDelivery::sole()->status)->toBe(WebhookDeliveryStatus::Ignorado);
});

it('cancels the armed reminders and announces the cancellation', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/api/v1/messages/*' => Http::response(['message' => 'Mensaje cancelado', 'status' => 'cancelado']),
        '*/api/v1/messages' => Http::response(['msg_id' => 'nuevo-1', 'status' => 'por_enviar'], 201),
    ]);

    NotificationRule::factory()->for($this->origin)->reminder(24, ReminderOffsetUnit::Horas)->create();
    NotificationRule::factory()->for($this->origin)->cancellation()->create();

    ProcessReservaYaEvent::dispatchSync(deliveryFor($this->origin, appointmentCreatedPayload()));

    $reminder = OutboundMessage::where('trigger', NotificationTrigger::Recordatorio)->sole();
    expect($reminder->fastsms_msg_id)->toBe('nuevo-1');

    ProcessReservaYaEvent::dispatchSync(deliveryFor($this->origin, [
        'event' => 'appointment.cancelled',
        'data' => ['lookup_code' => '40821', 'status' => 'cancelled'],
    ]));

    expect(Appointment::sole()->status)->toBe(AppointmentStatus::Cancelled)
        ->and($reminder->refresh()->status)->toBe(NotificationStatus::Cancelado)
        ->and(OutboundMessage::where('trigger', NotificationTrigger::CitaCancelada)->sole()->cuerpo)
        ->toContain('fue cancelada');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_contains($request->url(), '/api/v1/messages/nuevo-1'));
});

it('keeps the message as sent when FastSMS refuses the cancellation', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*/api/v1/messages/*' => Http::response(['error' => 'No se puede cancelar un mensaje ya enviado o finalizado.'], 422),
        '*/api/v1/messages' => Http::response(['msg_id' => 'nuevo-1', 'status' => 'por_enviar'], 201),
    ]);

    NotificationRule::factory()->for($this->origin)->reminder(24)->create();

    ProcessReservaYaEvent::dispatchSync(deliveryFor($this->origin, appointmentCreatedPayload()));

    ProcessReservaYaEvent::dispatchSync(deliveryFor($this->origin, [
        'event' => 'appointment.cancelled',
        'data' => ['lookup_code' => '40821', 'status' => 'cancelled'],
    ]));

    expect(OutboundMessage::sole())
        ->status->toBe(NotificationStatus::EntregadoAFastSms)
        ->error_message->toContain('ya enviado');
});

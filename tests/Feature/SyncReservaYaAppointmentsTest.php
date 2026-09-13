<?php

use App\Enums\AppointmentStatus;
use App\Enums\NotificationStatus;
use App\Enums\NotificationTrigger;
use App\Models\Appointment;
use App\Models\NotificationRule;
use App\Models\Origin;
use App\Models\OutboundMessage;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::preventStrayRequests();

    $this->origin = Origin::factory()->create([
        'reservaya_base_url' => 'https://ry.51x.mx/api/v1',
        'fastsms_base_url' => 'https://fastsms.test',
    ]);
});

function armedAppointment(Origin $origin): Appointment
{
    $appointment = Appointment::factory()->for($origin)->create(['lookup_code' => '40821']);

    OutboundMessage::factory()->for($appointment)->scheduled()->create([
        'origin_id' => $origin->id,
        'fastsms_msg_id' => 'msg-1',
    ]);

    return $appointment;
}

it('cancels the armed reminders when ReservaYa reports the appointment cancelled', function () {
    Http::fake([
        '*/api/v1/appointments/40821' => Http::response(['data' => ['lookup_code' => '40821', 'status' => 'cancelled']]),
        '*/api/v1/messages/msg-1' => Http::response(['message' => 'Mensaje cancelado', 'status' => 'cancelado']),
        '*/api/v1/messages' => Http::response(['msg_id' => 'aviso-1', 'status' => 'por_enviar'], 201),
    ]);

    NotificationRule::factory()->for($this->origin)->cancellation()->create();

    $appointment = armedAppointment($this->origin);

    $this->artisan('notificaya:sync-reservaya')->assertSuccessful();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and(OutboundMessage::where('trigger', NotificationTrigger::Recordatorio)->sole()->status)
        ->toBe(NotificationStatus::Cancelado)
        ->and(OutboundMessage::where('trigger', NotificationTrigger::CitaCancelada)->sole()->fastsms_msg_id)
        ->toBe('aviso-1');
});

it('treats a code ReservaYa no longer knows as cancelled', function () {
    Http::fake([
        '*/api/v1/appointments/40821' => Http::response(['message' => 'No encontrado'], 404),
        '*/api/v1/messages/msg-1' => Http::response(['message' => 'Mensaje cancelado', 'status' => 'cancelado']),
    ]);

    $appointment = armedAppointment($this->origin);

    $this->artisan('notificaya:sync-reservaya')->assertSuccessful();

    expect($appointment->refresh()->status)->toBe(AppointmentStatus::Cancelled)
        ->and(OutboundMessage::sole()->status)->toBe(NotificationStatus::Cancelado);
});

it('leaves a still confirmed appointment alone but records the check', function () {
    Http::fake([
        '*/api/v1/appointments/40821' => Http::response(['data' => ['lookup_code' => '40821', 'status' => 'confirmed']]),
    ]);

    $appointment = armedAppointment($this->origin);

    $this->artisan('notificaya:sync-reservaya')->assertSuccessful();

    expect($appointment->refresh())
        ->status->toBe(AppointmentStatus::Confirmed)
        ->last_synced_at->not->toBeNull()
        ->and(OutboundMessage::sole()->status)->toBe(NotificationStatus::Programado);
});

it('ignores appointments that already started or have nothing armed', function () {
    Http::fake();

    Appointment::factory()->for($this->origin)->create([
        'lookup_code' => '40001',
        'starts_at' => now()->subHour(),
    ]);

    Appointment::factory()->for($this->origin)->create(['lookup_code' => '40002']);

    $this->artisan('notificaya:sync-reservaya')->assertSuccessful();

    Http::assertNothingSent();
});

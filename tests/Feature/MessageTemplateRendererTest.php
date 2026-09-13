<?php

use App\Models\Appointment;
use App\Models\Origin;
use App\Services\Notifications\MessageTemplateRenderer;
use Illuminate\Support\Carbon;

beforeEach(function () {
    $this->renderer = app(MessageTemplateRenderer::class);
});

it('replaces every placeholder with the appointment data', function () {
    $origin = Origin::factory()->create([
        'name' => 'Barbería Centro',
        'timezone' => 'America/Mexico_City',
    ]);

    $appointment = Appointment::factory()->for($origin)->create([
        'lookup_code' => '40821',
        'customer_name' => 'Ana López',
        'service_name' => 'Corte de cabello',
        'event_name' => 'Firma de contrato',
        'responsable_email' => 'notario@example.com',
        'starts_at' => Carbon::parse('2026-09-02T09:30:00', 'America/Mexico_City')->utc(),
    ]);

    $rendered = $this->renderer->render(
        '{negocio}: {cliente} — {servicio} / {evento} el {fecha} a las {hora}. Código {codigo}. Responsable {responsable}.',
        $appointment->load('origin'),
    );

    expect($rendered)->toBe(
        'Barbería Centro: Ana López — Corte de cabello / Firma de contrato el miércoles 2 de septiembre a las 09:30. Código 40821. Responsable notario@example.com.'
    );
});

it('renders the hour in the timezone of the origin', function () {
    $origin = Origin::factory()->create(['timezone' => 'UTC']);

    $appointment = Appointment::factory()->for($origin)->create([
        'starts_at' => Carbon::parse('2026-09-02T09:30:00', 'America/Mexico_City')->utc(),
    ]);

    expect($this->renderer->render('{fecha} {hora}', $appointment->load('origin')))
        ->toBe('miércoles 2 de septiembre 15:30');
});

it('leaves no double spaces when a placeholder is empty', function () {
    $origin = Origin::factory()->create();

    $appointment = Appointment::factory()->for($origin)->create(['customer_name' => null]);

    expect($this->renderer->render('Hola {cliente} , tu cita sigue en pie.', $appointment->load('origin')))
        ->toBe('Hola , tu cita sigue en pie.');
});

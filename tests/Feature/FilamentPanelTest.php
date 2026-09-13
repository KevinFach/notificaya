<?php

use App\Enums\NotificationStatus;
use App\Filament\Resources\Appointments\Pages\ListAppointments;
use App\Filament\Resources\NotificationRules\Pages\CreateNotificationRule;
use App\Filament\Resources\Origins\Pages\ListOrigins;
use App\Filament\Resources\OutboundMessages\Pages\ListOutboundMessages;
use App\Filament\Resources\WebhookDeliveries\Pages\ListWebhookDeliveries;
use App\Jobs\CancelOutboundMessage;
use App\Jobs\RelayOutboundMessage;
use App\Models\Appointment;
use App\Models\NotificationRule;
use App\Models\Origin;
use App\Models\OutboundMessage;
use App\Models\User;
use App\Models\WebhookDelivery;
use Filament\Actions\Testing\TestAction;
use Illuminate\Support\Facades\Bus;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
    $this->origin = Origin::factory()->create();
});

it('lists the origins', function () {
    livewire(ListOrigins::class)->assertCanSeeTableRecords([$this->origin]);
});

it('lists the appointments received', function () {
    $appointments = Appointment::factory()->for($this->origin)->count(3)->create();

    livewire(ListAppointments::class)->assertCanSeeTableRecords($appointments);
});

it('lists the webhook deliveries', function () {
    $deliveries = WebhookDelivery::factory()->for($this->origin)->count(2)->create();

    livewire(ListWebhookDeliveries::class)->assertCanSeeTableRecords($deliveries);
});

it('lists the outbound messages and filters them by status', function () {
    $appointment = Appointment::factory()->for($this->origin)->create();

    $sent = OutboundMessage::factory()->for($appointment)->create([
        'origin_id' => $this->origin->id,
        'status' => NotificationStatus::Enviado,
    ]);
    $failed = OutboundMessage::factory()->for($appointment)->failed()->create([
        'origin_id' => $this->origin->id,
    ]);

    livewire(ListOutboundMessages::class)
        ->assertCanSeeTableRecords([$sent, $failed])
        ->filterTable('status', [NotificationStatus::Error->value])
        ->assertCanSeeTableRecords([$failed])
        ->assertCanNotSeeTableRecords([$sent]);
});

it('queues a resend from the outbound messages table', function () {
    Bus::fake();

    $appointment = Appointment::factory()->for($this->origin)->create();
    $failed = OutboundMessage::factory()->for($appointment)->failed()->create([
        'origin_id' => $this->origin->id,
    ]);

    livewire(ListOutboundMessages::class)
        ->callAction(TestAction::make('reenviar')->table($failed))
        ->assertNotified();

    Bus::assertDispatched(RelayOutboundMessage::class);
});

it('queues a cancellation from the outbound messages table', function () {
    Bus::fake();

    $appointment = Appointment::factory()->for($this->origin)->create();
    $scheduled = OutboundMessage::factory()->for($appointment)->scheduled()->create([
        'origin_id' => $this->origin->id,
    ]);

    livewire(ListOutboundMessages::class)
        ->callAction(TestAction::make('cancelar')->table($scheduled))
        ->assertNotified();

    Bus::assertDispatched(CancelOutboundMessage::class);
});

it('requires an offset when the rule is a reminder', function () {
    livewire(CreateNotificationRule::class)
        ->fillForm([
            'origin_id' => $this->origin->id,
            'name' => 'Recordatorio',
            'trigger' => 'recordatorio',
            'template' => 'Hola {cliente}',
        ])
        ->call('create')
        ->assertHasFormErrors(['offset_value' => 'required', 'offset_unit' => 'required']);
});

it('creates a reminder rule', function () {
    livewire(CreateNotificationRule::class)
        ->fillForm([
            'origin_id' => $this->origin->id,
            'name' => 'Recordatorio 24h',
            'trigger' => 'recordatorio',
            'offset_value' => 24,
            'offset_unit' => 'horas',
            'template' => 'Hola {cliente}, tu cita es el {fecha}.',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(NotificationRule::sole())
        ->offset_value->toBe(24)
        ->offsetInMinutes()->toBe(1440);
});

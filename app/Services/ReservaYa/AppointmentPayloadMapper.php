<?php

namespace App\Services\ReservaYa;

use App\Enums\AppointmentKind;
use App\Enums\AppointmentStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;

/**
 * Translates the `data` object of a ReservaYa payload — both the webhook body
 * and the response of GET /appointments/{lookup_code} — into Appointment
 * attributes. Keys that are absent are returned as null so the caller can
 * decide whether to overwrite what it already has.
 */
class AppointmentPayloadMapper
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function toAttributes(array $data, string $event): array
    {
        return [
            'kind' => $this->kind($data, $event),
            'status' => $this->status($data),
            'customer_name' => Arr::get($data, 'customer_name'),
            'customer_phone' => Arr::get($data, 'customer_phone'),
            'customer_email' => Arr::get($data, 'customer_email'),
            'service_external_id' => $this->serviceId($data),
            'service_name' => Arr::get($data, 'service.name'),
            'service_slug' => Arr::get($data, 'service.slug'),
            'event_name' => Arr::get($data, 'event_name'),
            'event_type' => Arr::get($data, 'event_type'),
            'responsable_email' => Arr::get($data, 'responsable_email'),
            'starts_at' => $this->date(Arr::get($data, 'starts_at')),
            'ends_at' => $this->date(Arr::get($data, 'ends_at')),
        ];
    }

    /**
     * The same attributes with the nulls stripped, for merging onto an
     * appointment that is already stored.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function toFilledAttributes(array $data, string $event): array
    {
        return array_filter(
            $this->toAttributes($data, $event),
            static fn (mixed $value) => $value !== null,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function kind(array $data, string $event): AppointmentKind
    {
        return $event === 'event.created' || filled(Arr::get($data, 'event_name'))
            ? AppointmentKind::Evento
            : AppointmentKind::Cita;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function status(array $data): AppointmentStatus
    {
        return AppointmentStatus::tryFrom((string) Arr::get($data, 'status')) ?? AppointmentStatus::Confirmed;
    }

    /**
     * ReservaYa nests the service on appointments but sends a flat
     * `service_id` on the events endpoint.
     *
     * @param  array<string, mixed>  $data
     */
    private function serviceId(array $data): ?int
    {
        $id = Arr::get($data, 'service.id') ?? Arr::get($data, 'service_id');

        return is_numeric($id) ? (int) $id : null;
    }

    private function date(mixed $value): ?Carbon
    {
        if (! is_string($value) || $value === '') {
            return null;
        }

        return Carbon::parse($value)->utc();
    }
}

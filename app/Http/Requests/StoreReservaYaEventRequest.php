<?php

namespace App\Http\Requests;

use App\Http\Middleware\VerifyReservaYaSignature;
use App\Models\Origin;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReservaYaEventRequest extends FormRequest
{
    /**
     * @var array<int, string>
     */
    public const EVENTS = [
        'appointment.created',
        'appointment.cancelled',
        'event.created',
    ];

    public function authorize(): bool
    {
        return $this->origin() instanceof Origin;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $needsSchedule = 'required_if:event,appointment.created,event.created';

        return [
            'event' => ['required', 'string', Rule::in(self::EVENTS)],
            'data' => ['required', 'array'],
            'data.lookup_code' => ['required', 'string', 'max:64'],
            'data.status' => ['nullable', 'string', Rule::in(['confirmed', 'cancelled'])],
            'data.customer_name' => ['nullable', 'string', 'max:255'],
            'data.customer_phone' => ['nullable', 'string', 'max:30'],
            'data.customer_email' => ['nullable', 'email', 'max:255'],
            'data.starts_at' => [$needsSchedule, 'nullable', 'date'],
            'data.ends_at' => ['nullable', 'date'],
            'data.service' => ['nullable', 'array'],
            'data.service.id' => ['nullable', 'integer'],
            'data.service.name' => ['nullable', 'string', 'max:255'],
            'data.service.slug' => ['nullable', 'string', 'max:255'],
            'data.service_id' => ['nullable', 'integer'],
            'data.event_name' => ['nullable', 'string', 'max:255'],
            'data.event_type' => ['nullable', 'string', 'max:255'],
            'data.responsable_email' => ['nullable', 'email', 'max:255'],
        ];
    }

    public function origin(): ?Origin
    {
        $origin = $this->attributes->get(VerifyReservaYaSignature::ORIGIN_ATTRIBUTE);

        return $origin instanceof Origin ? $origin : null;
    }

    public function event(): string
    {
        return (string) $this->input('event');
    }

    public function lookupCode(): string
    {
        return (string) $this->input('data.lookup_code');
    }
}

<?php

namespace App\Models;

use App\Enums\NotificationTrigger;
use App\Enums\ReminderOffsetUnit;
use Database\Factories\NotificationRuleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'origin_id',
    'name',
    'trigger',
    'offset_value',
    'offset_unit',
    'template',
    'service_ids',
    'is_active',
])]
class NotificationRule extends Model
{
    /** @use HasFactory<NotificationRuleFactory> */
    use HasFactory;

    protected $attributes = [
        'is_active' => true,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'trigger' => NotificationTrigger::class,
            'offset_unit' => ReminderOffsetUnit::class,
            'service_ids' => 'array',
            'is_active' => 'boolean',
            'offset_value' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Origin, $this>
     */
    public function origin(): BelongsTo
    {
        return $this->belongsTo(Origin::class);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    #[Scope]
    protected function active(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @param  Builder<$this>  $query
     * @return Builder<$this>
     */
    #[Scope]
    protected function forTrigger(Builder $query, NotificationTrigger $trigger): Builder
    {
        return $query->where('trigger', $trigger);
    }

    public function appliesToService(?int $serviceId): bool
    {
        if (blank($this->service_ids)) {
            return true;
        }

        return $serviceId !== null && in_array($serviceId, array_map('intval', $this->service_ids), true);
    }

    public function offsetInMinutes(): int
    {
        if ($this->offset_value === null || ! $this->offset_unit instanceof ReminderOffsetUnit) {
            return 0;
        }

        return $this->offset_unit->toMinutes($this->offset_value);
    }
}

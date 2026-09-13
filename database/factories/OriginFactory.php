<?php

namespace Database\Factories;

use App\Models\Origin;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Origin>
 */
class OriginFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numberBetween(1, 99999),
            'is_active' => true,
            'reservaya_base_url' => 'https://ry.51x.mx/api/v1',
            'reservaya_token' => 'ry_'.Str::random(32),
            'verify_with_api' => false,
            'webhook_secret' => Str::random(64),
            'fastsms_base_url' => 'https://fastsms.test',
            'fastsms_token' => 'fs_'.Str::random(32),
            'timezone' => 'America/Mexico_City',
            'fastsms_timezone' => null,
            'phone_prefix' => '+52',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    public function verifyingWithApi(): static
    {
        return $this->state(fn (array $attributes) => [
            'verify_with_api' => true,
        ]);
    }
}

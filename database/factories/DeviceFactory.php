<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Device;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Device>
 */
class DeviceFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'platform' => fake()->randomElement(['ios', 'android']),
            'app_version' => '1.0.0',
            'last_seen_at' => now(),
        ];
    }

    public function blocked(): static
    {
        return $this->state(fn (array $attributes): array => ['blocked_at' => now()]);
    }
}

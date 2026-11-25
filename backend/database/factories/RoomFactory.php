<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class RoomFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Sala '.fake()->unique()->numberBetween(1, 9999),
            'description' => fake()->sentence(),
            'capacity' => fake()->numberBetween(2, 30),
            'opens_at' => '08:00',
            'closes_at' => '20:00',
            'max_booking_minutes' => 120,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}

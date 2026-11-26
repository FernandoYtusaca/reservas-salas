<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BookingFactory extends Factory
{
    public function definition(): array
    {
        $start = now()->addDay()->startOfDay()->setTime(10, 0);

        return [
            'room_id' => Room::factory(),
            'user_id' => User::factory(),
            'title' => fake()->sentence(3),
            'starts_at' => $start,
            'ends_at' => $start->copy()->addHour(),
        ];
    }

    public function cancelled(): static
    {
        return $this->state(fn () => [
            'status' => BookingStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private function at(string $time, int $daysAhead = 1): Carbon
    {
        return now()->addDays($daysAhead)->startOfDay()->setTimeFromTimeString($time);
    }

    private function payload(Room $room, string $from = '10:00', string $to = '11:00', array $extra = []): array
    {
        return array_merge([
            'room_id' => $room->id,
            'title' => 'Reunión de equipo',
            'starts_at' => $this->at($from)->toIso8601String(),
            'ends_at' => $this->at($to)->toIso8601String(),
        ], $extra);
    }

    private function existing(Room $room, string $from = '10:00', string $to = '11:00', array $extra = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'room_id' => $room->id,
            'starts_at' => $this->at($from),
            'ends_at' => $this->at($to),
        ], $extra));
    }

    public function test_user_can_create_a_booking(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/bookings', $this->payload($room))
            ->assertCreated()
            ->assertJsonPath('status', 'confirmed')
            ->assertJsonPath('room.name', $room->name);

        $this->assertDatabaseHas('bookings', ['user_id' => $user->id, 'room_id' => $room->id]);
    }

    public function test_user_id_and_status_cannot_be_set_by_the_client(): void
    {
        $user = User::factory()->create();
        $other = User::factory()->create();
        $room = Room::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/bookings', $this->payload($room, extra: ['user_id' => $other->id, 'status' => 'cancelled']))
            ->assertCreated()
            ->assertJsonPath('user_id', $user->id)
            ->assertJsonPath('status', 'confirmed');
    }

    public function test_overlapping_bookings_are_rejected(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $this->existing($room, '10:00', '11:00');

        $overlaps = [
            ['10:00', '11:00'],
            ['10:30', '11:30'],
            ['09:30', '10:30'],
            ['09:30', '11:30'],
            ['10:15', '10:45'],
        ];

        foreach ($overlaps as [$from, $to]) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/bookings', $this->payload($room, $from, $to))
                ->assertStatus(409);
        }

        $this->assertDatabaseCount('bookings', 1);
    }

    public function test_back_to_back_bookings_are_allowed(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $this->existing($room, '10:00', '11:00');

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/bookings', $this->payload($room, '11:00', '12:00'))
            ->assertCreated();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/bookings', $this->payload($room, '09:00', '10:00'))
            ->assertCreated();
    }

    public function test_bookings_in_other_rooms_do_not_conflict(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $other = Room::factory()->create();
        $this->existing($room);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/bookings', $this->payload($other))
            ->assertCreated();
    }

    public function test_cancelled_bookings_free_the_slot(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        Booking::factory()->cancelled()->create([
            'room_id' => $room->id,
            'starts_at' => $this->at('10:00'),
            'ends_at' => $this->at('11:00'),
        ]);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/bookings', $this->payload($room))
            ->assertCreated();
    }

    public function test_booking_data_is_validated(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(); // abre de 08:00 a 20:00, máximo 120 minutos
        $inactive = Room::factory()->inactive()->create();

        $cases = [
            'starts_at' => $this->payload($room, extra: [
                'starts_at' => now()->subHour()->toIso8601String(),
                'ends_at' => now()->addHour()->toIso8601String(),
            ]),
            'ends_at' => $this->payload($room, '11:00', '10:00'),
            'room_id' => $this->payload($inactive),
            'title' => $this->payload($room, extra: ['title' => '']),
        ];

        foreach ($cases as $field => $data) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/bookings', $data)
                ->assertUnprocessable()
                ->assertJsonValidationErrors($field);
        }

        $business = [
            'starts_at' => $this->payload($room, '07:00', '08:00'),
            'starts_at ' => $this->payload($room, '19:30', '20:30'),
            'ends_at' => $this->payload($room, '10:00', '13:00'),
            'starts_at  ' => $this->payload($room, '10:10', '11:10'),
        ];

        foreach ($business as $field => $data) {
            $this->actingAs($user, 'sanctum')
                ->postJson('/api/bookings', $data)
                ->assertUnprocessable()
                ->assertJsonValidationErrors(trim($field));
        }

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_booking_cannot_cross_midnight(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create(['opens_at' => '00:00', 'closes_at' => '23:45']);

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/bookings', [
                'room_id' => $room->id,
                'title' => 'Turno de noche',
                'starts_at' => $this->at('23:00')->toIso8601String(),
                'ends_at' => $this->at('01:00', 2)->toIso8601String(),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ends_at');
    }

    public function test_guests_cannot_book(): void
    {
        $room = Room::factory()->create();

        $this->postJson('/api/bookings', $this->payload($room))->assertUnauthorized();
        $this->getJson('/api/bookings')->assertUnauthorized();
    }

    public function test_users_only_list_their_own_bookings(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $room = Room::factory()->create();
        $this->existing($room, '10:00', '11:00', ['user_id' => $user->id]);
        $this->existing($room, '12:00', '13:00');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/bookings')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/bookings?all=1')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/bookings?all=1')
            ->assertOk()
            ->assertJsonCount(2, 'data');
    }

    public function test_bookings_can_be_filtered_by_scope(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();
        $this->existing($room, '10:00', '11:00', ['user_id' => $user->id]);
        Booking::factory()->create([
            'room_id' => $room->id,
            'user_id' => $user->id,
            'starts_at' => now()->subDays(3)->setTime(10, 0),
            'ends_at' => now()->subDays(3)->setTime(11, 0),
        ]);

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/bookings?scope=upcoming')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/bookings?scope=past')
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_users_cannot_see_or_cancel_other_users_bookings(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $booking = $this->existing(Room::factory()->create(), extra: ['user_id' => $owner->id]);

        $this->actingAs($other, 'sanctum')->getJson("/api/bookings/{$booking->id}")->assertNotFound();
        $this->actingAs($other, 'sanctum')->postJson("/api/bookings/{$booking->id}/cancel")->assertNotFound();

        $this->actingAs($owner, 'sanctum')->getJson("/api/bookings/{$booking->id}")->assertOk();
        $this->actingAs($admin, 'sanctum')->getJson("/api/bookings/{$booking->id}")->assertOk();
    }

    public function test_owner_can_cancel_a_future_booking_once(): void
    {
        $owner = User::factory()->create();
        $booking = $this->existing(Room::factory()->create(), extra: ['user_id' => $owner->id]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');

        $this->assertNotNull($booking->fresh()->cancelled_at);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel")
            ->assertStatus(409);
    }

    public function test_users_cannot_cancel_a_started_booking_but_admins_can(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $booking = Booking::factory()->create([
            'room_id' => Room::factory(),
            'user_id' => $owner->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $this->actingAs($owner, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel")
            ->assertUnprocessable();

        $this->actingAs($admin, 'sanctum')
            ->postJson("/api/bookings/{$booking->id}/cancel")
            ->assertOk()
            ->assertJsonPath('status', 'cancelled');
    }
}

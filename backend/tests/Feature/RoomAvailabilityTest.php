<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RoomAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function at(string $time, int $daysAhead = 1): Carbon
    {
        return now()->addDays($daysAhead)->startOfDay()->setTimeFromTimeString($time);
    }

    private function zulu(Carbon $moment): string
    {
        return $moment->copy()->utc()->toIso8601ZuluString();
    }

    private function url(Room $room, int $daysAhead = 1): string
    {
        return "/api/rooms/{$room->id}/availability?date=".$this->at('00:00', $daysAhead)->toDateString();
    }

    private function book(Room $room, string $from, string $to, array $extra = []): Booking
    {
        return Booking::factory()->create(array_merge([
            'room_id' => $room->id,
            'starts_at' => $this->at($from),
            'ends_at' => $this->at($to),
        ], $extra));
    }

    public function test_an_empty_day_is_entirely_free(): void
    {
        $room = Room::factory()->create(); // abre de 08:00 a 20:00

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson($this->url($room))
            ->assertOk()
            ->assertJsonPath('opens_at', $this->zulu($this->at('08:00')))
            ->assertJsonPath('closes_at', $this->zulu($this->at('20:00')))
            ->assertJsonCount(0, 'bookings')
            ->assertJsonCount(1, 'free')
            ->assertJsonPath('free.0.starts_at', $this->zulu($this->at('08:00')))
            ->assertJsonPath('free.0.ends_at', $this->zulu($this->at('20:00')));
    }

    public function test_free_ranges_are_the_gaps_between_bookings(): void
    {
        $room = Room::factory()->create();
        $this->book($room, '10:00', '11:00');
        $this->book($room, '14:00', '15:30');

        $response = $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson($this->url($room))
            ->assertOk()
            ->assertJsonCount(2, 'bookings')
            ->assertJsonCount(3, 'free');

        $expected = [['08:00', '10:00'], ['11:00', '14:00'], ['15:30', '20:00']];

        foreach ($expected as $i => [$from, $to]) {
            $response
                ->assertJsonPath("free.$i.starts_at", $this->zulu($this->at($from)))
                ->assertJsonPath("free.$i.ends_at", $this->zulu($this->at($to)));
        }
    }

    public function test_back_to_back_bookings_leave_no_gap(): void
    {
        $room = Room::factory()->create();
        $this->book($room, '10:00', '11:00');
        $this->book($room, '11:00', '12:00');

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson($this->url($room))
            ->assertOk()
            ->assertJsonCount(2, 'free')
            ->assertJsonPath('free.0.ends_at', $this->zulu($this->at('10:00')))
            ->assertJsonPath('free.1.starts_at', $this->zulu($this->at('12:00')));
    }

    public function test_cancelled_bookings_do_not_block_the_slot(): void
    {
        $room = Room::factory()->create();
        Booking::factory()->cancelled()->create([
            'room_id' => $room->id,
            'starts_at' => $this->at('10:00'),
            'ends_at' => $this->at('11:00'),
        ]);

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson($this->url($room))
            ->assertOk()
            ->assertJsonCount(0, 'bookings')
            ->assertJsonCount(1, 'free');
    }

    public function test_a_fully_booked_day_has_no_free_ranges(): void
    {
        $room = Room::factory()->create(['opens_at' => '09:00', 'closes_at' => '10:00']);
        $this->book($room, '09:00', '10:00');

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson($this->url($room))
            ->assertOk()
            ->assertJsonCount(1, 'bookings')
            ->assertJsonCount(0, 'free');
    }

    public function test_titles_of_other_peoples_bookings_are_hidden(): void
    {
        $user = User::factory()->create();
        $admin = User::factory()->admin()->create();
        $room = Room::factory()->create();
        $this->book($room, '10:00', '11:00', ['user_id' => $user->id, 'title' => 'Mi reunión']);
        $this->book($room, '12:00', '13:00', ['title' => 'Reunión ajena']);

        $this->actingAs($user, 'sanctum')
            ->getJson($this->url($room))
            ->assertOk()
            ->assertJsonPath('bookings.0.mine', true)
            ->assertJsonPath('bookings.0.title', 'Mi reunión')
            ->assertJsonPath('bookings.1.mine', false)
            ->assertJsonPath('bookings.1.title', null);

        $this->actingAs($admin, 'sanctum')
            ->getJson($this->url($room))
            ->assertOk()
            ->assertJsonPath('bookings.1.title', 'Reunión ajena');
    }

    public function test_time_that_has_already_passed_is_not_free_today(): void
    {
        $room = Room::factory()->create();
        $user = User::factory()->create();

        $this->travelTo(Carbon::parse('2026-12-02 12:07:00'));

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/rooms/{$room->id}/availability?date=2026-12-02")
            ->assertOk()
            ->assertJsonCount(1, 'free')
            ->assertJsonPath('free.0.starts_at', $this->zulu(Carbon::parse('2026-12-02 12:15:00')))
            ->assertJsonPath('free.0.ends_at', $this->zulu(Carbon::parse('2026-12-02 20:00:00')));
    }

    public function test_past_days_and_closed_hours_have_no_free_ranges(): void
    {
        $room = Room::factory()->create();
        $user = User::factory()->create();

        $this->travelTo(Carbon::parse('2026-12-02 21:00:00'));

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/rooms/{$room->id}/availability?date=2026-12-02")
            ->assertOk()
            ->assertJsonCount(0, 'free');

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/rooms/{$room->id}/availability?date=2026-12-01")
            ->assertOk()
            ->assertJsonCount(0, 'free');
    }

    public function test_the_date_is_validated(): void
    {
        $room = Room::factory()->create();
        $user = User::factory()->create();

        foreach (['', 'hoy', '02/12/2026', '2026-13-45'] as $date) {
            $this->actingAs($user, 'sanctum')
                ->getJson("/api/rooms/{$room->id}/availability?date={$date}")
                ->assertUnprocessable()
                ->assertJsonValidationErrors('date');
        }
    }

    public function test_inactive_rooms_are_only_available_to_admins(): void
    {
        $room = Room::factory()->inactive()->create();

        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson($this->url($room))
            ->assertNotFound();

        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson($this->url($room))
            ->assertOk();
    }

    public function test_guests_cannot_see_availability(): void
    {
        $room = Room::factory()->create();

        $this->getJson($this->url($room))->assertUnauthorized();
    }
}

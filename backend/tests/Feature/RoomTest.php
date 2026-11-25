<?php

namespace Tests\Feature;

use App\Models\Room;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoomTest extends TestCase
{
    use RefreshDatabase;

    private function validData(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Sala Mercurio',
            'description' => 'Sala con pizarra',
            'capacity' => 8,
            'opens_at' => '08:00',
            'closes_at' => '20:00',
            'max_booking_minutes' => 120,
        ], $overrides);
    }

    private function admin(): User
    {
        return User::factory()->admin()->create();
    }

    public function test_admin_can_create_a_room(): void
    {
        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/rooms', $this->validData())
            ->assertCreated()
            ->assertJsonPath('name', 'Sala Mercurio')
            ->assertJsonPath('is_active', true)
            ->assertJsonPath('opens_at', '08:00');

        $this->assertDatabaseHas('rooms', ['name' => 'Sala Mercurio']);
    }

    public function test_regular_users_cannot_manage_rooms(): void
    {
        $user = User::factory()->create();
        $room = Room::factory()->create();

        $this->actingAs($user, 'sanctum')
            ->postJson('/api/rooms', $this->validData())
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->putJson("/api/rooms/{$room->id}", $this->validData())
            ->assertForbidden();

        $this->actingAs($user, 'sanctum')
            ->deleteJson("/api/rooms/{$room->id}")
            ->assertForbidden();

        $this->assertDatabaseCount('rooms', 1);
    }

    public function test_guests_cannot_access_rooms(): void
    {
        $this->getJson('/api/rooms')->assertUnauthorized();
        $this->postJson('/api/rooms', $this->validData())->assertUnauthorized();
    }

    public function test_room_data_is_validated(): void
    {
        $admin = $this->admin();

        $invalid = [
            ['name' => ''],
            ['capacity' => 0],
            ['opens_at' => '8am'],
            ['closes_at' => '07:00'],
            ['max_booking_minutes' => 20],
        ];

        foreach ($invalid as $overrides) {
            $this->actingAs($admin, 'sanctum')
                ->postJson('/api/rooms', $this->validData($overrides))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(array_key_first($overrides));
        }
    }

    public function test_room_name_must_be_unique(): void
    {
        Room::factory()->create(['name' => 'Sala Mercurio']);

        $this->actingAs($this->admin(), 'sanctum')
            ->postJson('/api/rooms', $this->validData())
            ->assertUnprocessable()
            ->assertJsonValidationErrors('name');
    }

    public function test_users_only_see_active_rooms_and_admins_see_all(): void
    {
        $active = Room::factory()->create();
        $inactive = Room::factory()->inactive()->create();
        $user = User::factory()->create();
        $admin = $this->admin();

        $this->actingAs($user, 'sanctum')
            ->getJson('/api/rooms')
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.id', $active->id);

        $this->actingAs($user, 'sanctum')
            ->getJson("/api/rooms/{$inactive->id}")
            ->assertNotFound();

        $this->actingAs($admin, 'sanctum')
            ->getJson('/api/rooms')
            ->assertOk()
            ->assertJsonCount(2);

        $this->actingAs($admin, 'sanctum')
            ->getJson("/api/rooms/{$inactive->id}")
            ->assertOk();
    }

    public function test_admin_can_update_and_deactivate_a_room(): void
    {
        $room = Room::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/rooms/{$room->id}", $this->validData(['name' => 'Sala Nueva', 'is_active' => false]))
            ->assertOk()
            ->assertJsonPath('name', 'Sala Nueva')
            ->assertJsonPath('is_active', false);
    }

    public function test_a_room_can_keep_its_own_name_when_updated(): void
    {
        $room = Room::factory()->create(['name' => 'Sala Mercurio']);

        $this->actingAs($this->admin(), 'sanctum')
            ->putJson("/api/rooms/{$room->id}", $this->validData())
            ->assertOk();
    }

    public function test_room_without_bookings_can_be_deleted(): void
    {
        $room = Room::factory()->create();

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/rooms/{$room->id}")
            ->assertNoContent();

        $this->assertDatabaseMissing('rooms', ['id' => $room->id]);
    }

    public function test_room_with_bookings_cannot_be_deleted(): void
    {
        $room = Room::factory()->create();
        $user = User::factory()->create();

        $user->bookings()->create([
            'room_id' => $room->id,
            'title' => 'Reunión de equipo',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHour(),
        ]);

        $this->actingAs($this->admin(), 'sanctum')
            ->deleteJson("/api/rooms/{$room->id}")
            ->assertStatus(409);

        $this->assertDatabaseHas('rooms', ['id' => $room->id]);
    }
}

<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_registered_users_are_always_regular_users(): void
    {
        $this->postJson('/api/register', [
            'name' => 'Test',
            'email' => 'test@mail.com',
            'password' => '12345678',
            'password_confirmation' => '12345678',
            'role' => 'admin', // intento de escalada de privilegios
        ])
            ->assertCreated()
            ->assertJsonPath('user.role', 'user');

        $this->assertDatabaseHas('users', ['email' => 'test@mail.com', 'role' => 'user']);
    }

    public function test_regular_users_cannot_access_admin_routes(): void
    {
        $this->actingAs(User::factory()->create(), 'sanctum')
            ->getJson('/api/admin/ping')
            ->assertForbidden();
    }

    public function test_admins_can_access_admin_routes(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/admin/ping')
            ->assertOk()
            ->assertJson(['ok' => true]);
    }

    public function test_guests_cannot_access_admin_routes(): void
    {
        $this->getJson('/api/admin/ping')->assertUnauthorized();
    }

    public function test_user_endpoint_returns_the_role(): void
    {
        $this->actingAs(User::factory()->admin()->create(), 'sanctum')
            ->getJson('/api/user')
            ->assertOk()
            ->assertJsonPath('role', 'admin');
    }

    public function test_login_is_rate_limited(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->postJson('/api/login', ['email' => 'nadie@mail.com', 'password' => 'x']);
        }

        $this->postJson('/api/login', ['email' => 'nadie@mail.com', 'password' => 'x'])
            ->assertStatus(429);
    }

    public function test_seeder_creates_the_admin_from_configuration(): void
    {
        config([
            'reservas.admin.email' => 'boss@example.com',
            'reservas.admin.password' => 'una-contraseña-larga-123',
        ]);

        $this->seed(DatabaseSeeder::class);

        $admin = User::where('email', 'boss@example.com')->first();

        $this->assertNotNull($admin);
        $this->assertTrue($admin->isAdmin());
        $this->assertTrue(Hash::check('una-contraseña-larga-123', $admin->password));
    }

    public function test_seeder_does_not_create_an_admin_without_a_password(): void
    {
        config(['reservas.admin.password' => null]);

        $this->seed(DatabaseSeeder::class);

        $this->assertDatabaseCount('users', 0);
    }
}

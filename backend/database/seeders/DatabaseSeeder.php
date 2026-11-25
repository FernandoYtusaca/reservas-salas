<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $password = config('reservas.admin.password');

        if (! $password) {
            $this->command?->error('Define ADMIN_PASSWORD en backend/.env para crear el administrador.');

            return;
        }

        $admin = User::firstOrNew(['email' => config('reservas.admin.email')]);
        $admin->name = config('reservas.admin.name');
        $admin->password = $password; // el cast 'hashed' la encripta
        $admin->role = Role::Admin;
        $admin->save();
    }
}

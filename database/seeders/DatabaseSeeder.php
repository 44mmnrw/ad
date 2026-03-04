<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $user = User::query()->firstOrCreate([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ], [
            'password' => 'password',
            'role' => 'admin',
        ]);

        if (! $user->wasRecentlyCreated && $user->role !== 'admin') {
            $user->update(['role' => 'admin']);
        }

        $this->call(RolePermissionSeeder::class);

        $user->syncRoles(['admin']);
    }
}

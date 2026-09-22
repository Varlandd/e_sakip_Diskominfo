<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $users = [
            [
                'name' => 'Administrator',
                'username' => 'admin',
                'email' => 'admin@kominfo.go.id',
                'password' => Hash::make('admin123'),
                'role' => 'admin',
            ],
            [
                'name' => 'Komunikasi',
                'username' => 'komunikasi',
                'email' => 'komunikasi@kominfo.go.id',
                'password' => Hash::make('komunikasi123'),
                'role' => 'komunikasi',
            ],
            [
                'name' => 'Statistik',
                'username' => 'statistik',
                'email' => 'statistik@kominfo.go.id',
                'password' => Hash::make('statistik123'),
                'role' => 'statistik',
            ],
            [
                'name' => 'Persandian',
                'username' => 'persandian',
                'email' => 'persandian@kominfo.go.id',
                'password' => Hash::make('persandian123'),
                'role' => 'persandian',
            ],
            [
                'name' => 'Aplikasi',
                'username' => 'aplikasi',
                'email' => 'aplikasi@kominfo.go.id',
                'password' => Hash::make('aplikasi123'),
                'role' => 'aplikasi',
            ],
            [
                'name' => 'Kesekretariatan',
                'username' => 'kesekretariatan',
                'email' => 'kesekretariatan@kominfo.go.id',
                'password' => Hash::make('kesekretariatan123'),
                'role' => 'kesekretariatan',
            ],
        ];

        foreach ($users as $user) {
            User::updateOrCreate(
                ['username' => $user['username']],
                $user
            );
        }
    }
}

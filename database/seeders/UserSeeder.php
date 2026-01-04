<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "SEEDER: Creating users...\n";
        echo str_repeat("-", 50) . "\n";
        
        $users = [
            // INI ATMIN YGY
            [
                'name' => 'Atmin YGY',
                'email' => 'atmin@ipwija.ac.id',
                'password' => bcrypt('admin123'),
                'role' => 'admin',
                'nim' => null,
                'phone' => null,
                'address' => null,
                'tempat_lahir' => null,
                'tanggal_lahir' => null,
                'agama' => null,
                'status' => 'ACTIVE',
                'profile_picture' => null,
            ],

            // USER BIASA
            [
                'name' => 'User Biasa',
                'email' => 'user@ipwija.com',
                'password' => bcrypt('user1234'),
                'role' => 'user',
                'nim' => null,
                'phone' => null,
                'address' => null,
                'tempat_lahir' => null,
                'tanggal_lahir' => null,
                'agama' => null,
                'status' => 'pending',
                'profile_picture' => null,
            ],
        ];

        foreach ($users as $userData) {
            \App\Models\User::updateOrCreate(
                ['email' => $userData['email']],
                $userData
            );
        }

        echo "Users seeding successfully.\n";
    }
}

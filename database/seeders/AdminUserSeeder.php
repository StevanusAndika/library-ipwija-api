<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        echo "🔧 SEEDER: Creating users...\n";
        echo str_repeat("-", 50) . "\n";

        // ==================== METHOD 1: TRUNCATE FIRST ====================
        // Uncomment jika mau reset semua user dulu
        /*
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');
        User::truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        echo "🗑️  All users deleted\n";
        */

        // ==================== METHOD 2: UPDATE OR CREATE ====================
        $users = [
            [
                'email' => 'superadmin@perpustakaan.com',
                'name' => 'Super Admin',
                'password' => 'superadmin123',
                'nim' => 'SUPER001',
                'phone' => '081111111111',
                'role' => 'admin',
                'tempat_lahir' => 'Jakarta',
                'tanggal_lahir' => '1990-01-01',
                'agama' => 'Islam',
                'alamat_asal' => 'Jl. Admin No. 1, Jakarta',
                'alamat_sekarang' => 'Jl. Admin No. 1, Jakarta',
                'is_anggota' => true,
                'is_active' => true,
            ],
            [
                'email' => 'admin@perpustakaan.com',
                'name' => 'Admin Perpustakaan',
                'password' => 'admin123',
                'nim' => 'ADM001',
                'phone' => '082222222222',
                'role' => 'admin',
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '1992-05-15',
                'agama' => 'Kristen',
                'alamat_asal' => 'Jl. Bandung No. 10, Bandung',
                'alamat_sekarang' => 'Jl. Bandung No. 10, Bandung',
                'is_anggota' => true,
                'is_active' => true,
            ],
            [
                'email' => 'admin.it@perpustakaan.com',
                'name' => 'Admin IT',
                'password' => 'adminit123',
                'nim' => 'ADM002',
                'phone' => '083333333333',
                'role' => 'admin',
                'tempat_lahir' => 'Surabaya',
                'tanggal_lahir' => '1993-08-20',
                'agama' => 'Islam',
                'alamat_asal' => 'Jl. Surabaya No. 20, Surabaya',
                'alamat_sekarang' => 'Jl. Surabaya No. 20, Surabaya',
                'is_anggota' => true,
                'is_active' => true,
            ],
            [
                'email' => 'mahasiswa@example.com',
                'name' => 'Mahasiswa Test',
                'password' => 'password123',
                'nim' => '20240001',
                'phone' => '084444444444',
                'role' => 'mahasiswa',
                'is_anggota' => true,
                'is_active' => true,
            ],
            [
                'email' => 'mahasiswa2@example.com',
                'name' => 'John Doe',
                'password' => 'password123',
                'nim' => '20240002',
                'phone' => '085555555555',
                'role' => 'mahasiswa',
                'tempat_lahir' => 'Jakarta',
                'tanggal_lahir' => '2001-06-15',
                'agama' => 'Islam',
                'alamat_asal' => 'Jl. Contoh No. 1',
                'alamat_sekarang' => 'Jl. Kost No. 2',
                'is_anggota' => true,
                'is_active' => true,
            ],
        ];

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($users as $userData) {
            try {
                // Gunakan updateOrCreate untuk aman
                $user = User::updateOrCreate(
                    ['email' => $userData['email']], // Cari berdasarkan email
                    [
                        'name' => $userData['name'],
                        'password' => Hash::make($userData['password']),
                        'nim' => $userData['nim'],
                        'phone' => $userData['phone'],
                        'role' => $userData['role'],
                        'tempat_lahir' => $userData['tempat_lahir'] ?? null,
                        'tanggal_lahir' => $userData['tanggal_lahir'] ?? null,
                        'agama' => $userData['agama'] ?? null,
                        'alamat_asal' => $userData['alamat_asal'] ?? null,
                        'alamat_sekarang' => $userData['alamat_sekarang'] ?? null,
                        'is_anggota' => $userData['is_anggota'],
                        'is_active' => $userData['is_active'],
                        'email_verified_at' => now(),
                    ]
                );

                if ($user->wasRecentlyCreated) {
                    echo "✅ CREATED: {$user->name} ({$user->email})\n";
                    $created++;
                } else {
                    echo "🔄 UPDATED: {$user->name} ({$user->email})\n";
                    $updated++;
                }

            } catch (\Exception $e) {
                echo "❌ ERROR for {$userData['email']}: " . $e->getMessage() . "\n";
                $skipped++;
            }
        }

        echo "\n" . str_repeat("=", 50) . "\n";
        echo "📊 SEEDER SUMMARY:\n";
        echo str_repeat("-", 50) . "\n";
        echo "✅ Created: {$created} users\n";
        echo "🔄 Updated: {$updated} users\n";
        echo "❌ Skipped: {$skipped} users\n";
        echo "📈 Total in database: " . User::count() . " users\n";
        echo str_repeat("=", 50) . "\n\n";

        echo "🎯 CREDENTIALS FOR TESTING:\n";
        echo str_repeat("-", 30) . "\n";
        echo "👑 ADMIN:\n";
        echo "   Email: admin@perpustakaan.com\n";
        echo "   Password: admin123\n\n";

        echo "🎓 MAHASISWA:\n";
        echo "   Email: mahasiswa@example.com\n";
        echo "   Password: password123\n\n";

        echo "🚀 Ready for API testing!\n";
    }
}

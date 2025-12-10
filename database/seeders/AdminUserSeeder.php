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
                'gender' => 'laki-laki',
                'agama' => 'ISLAM',
                'address' => 'Jl. Admin No. 1, Jakarta',
                'status' => 'ACTIVE',
                'profile_picture' => null,
                'email_verified_at' => now(),
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
                'gender' => 'laki-laki',
                'agama' => 'KRISTEN',
                'address' => 'Jl. Bandung No. 10, Bandung',
                'status' => 'ACTIVE',
                'profile_picture' => null,
                'email_verified_at' => now(),
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
                'gender' => 'perempuan',
                'agama' => 'ISLAM',
                'address' => 'Jl. Surabaya No. 20, Surabaya',
                'status' => 'ACTIVE',
                'profile_picture' => null,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'user@example.com',
                'name' => 'User Test',
                'password' => 'password123',
                'nim' => '20240001',
                'phone' => '084444444444',
                'role' => 'user',
                'tempat_lahir' => 'Jakarta',
                'tanggal_lahir' => '2001-06-15',
                'gender' => 'laki-laki',
                'agama' => 'ISLAM',
                'address' => 'Jl. Contoh No. 1, Jakarta',
                'status' => 'ACTIVE',
                'profile_picture' => null,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'john.doe@example.com',
                'name' => 'John Doe',
                'password' => 'password123',
                'nim' => '20240002',
                'phone' => '085555555555',
                'role' => 'user',
                'tempat_lahir' => 'Bandung',
                'tanggal_lahir' => '2002-03-10',
                'gender' => 'laki-laki',
                'agama' => 'KRISTEN',
                'address' => 'Jl. Merdeka No. 45, Bandung',
                'status' => 'ACTIVE',
                'profile_picture' => null,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'jane.smith@example.com',
                'name' => 'Jane Smith',
                'password' => 'password123',
                'nim' => '20240003',
                'phone' => '086666666666',
                'role' => 'user',
                'tempat_lahir' => 'Surabaya',
                'tanggal_lahir' => '2001-11-25',
                'gender' => 'perempuan',
                'agama' => 'KATOLIK',
                'address' => 'Jl. Sudirman No. 78, Surabaya',
                'status' => 'PENDING', // User belum complete membership
                'profile_picture' => null,
                'email_verified_at' => null,
            ],
            [
                'email' => 'michael.wong@example.com',
                'name' => 'Michael Wong',
                'password' => 'password123',
                'nim' => '20240004',
                'phone' => '087777777777',
                'role' => 'user',
                'tempat_lahir' => 'Medan',
                'tanggal_lahir' => '2000-09-05',
                'gender' => 'laki-laki',
                'agama' => 'BUDDHA',
                'address' => 'Jl. Asia No. 12, Medan',
                'status' => 'ACTIVE',
                'profile_picture' => null,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'sarah.conor@example.com',
                'name' => 'Sarah Conor',
                'password' => 'password123',
                'nim' => '20240005',
                'phone' => '088888888888',
                'role' => 'user',
                'tempat_lahir' => 'Yogyakarta',
                'tanggal_lahir' => '2001-07-30',
                'gender' => 'perempuan',
                'agama' => 'HINDU',
                'address' => 'Jl. Malioboro No. 33, Yogyakarta',
                'status' => 'INACTIVE', // User tidak aktif
                'profile_picture' => null,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'david.lee@example.com',
                'name' => 'David Lee',
                'password' => 'password123',
                'nim' => '20240006',
                'phone' => '089999999999',
                'role' => 'user',
                'tempat_lahir' => 'Bali',
                'tanggal_lahir' => '2002-02-14',
                'gender' => 'laki-laki',
                'agama' => 'KONGHUCU',
                'address' => 'Jl. Kuta No. 56, Bali',
                'status' => 'SUSPENDED', // User suspended
                'profile_picture' => null,
                'email_verified_at' => now(),
            ],
            [
                'email' => 'lisa.ray@example.com',
                'name' => 'Lisa Ray',
                'password' => 'password123',
                'nim' => '20240007',
                'phone' => '081010101010',
                'role' => 'user',
                'tempat_lahir' => 'Semarang',
                'tanggal_lahir' => '2001-12-08',
                'gender' => 'perempuan',
                'agama' => 'ISLAM',
                'address' => 'Jl. Pemuda No. 22, Semarang',
                'status' => 'ACTIVE',
                'profile_picture' => null,
                'email_verified_at' => now(),
            ],
        ];

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($users as $userData) {
            try {
                // Hash password
                $userData['password'] = Hash::make($userData['password']);

                // Gunakan updateOrCreate untuk aman
                $user = User::updateOrCreate(
                    ['email' => $userData['email']], // Cari berdasarkan email
                    $userData
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
        echo str_repeat("-", 40) . "\n";
        echo "👑 SUPER ADMIN:\n";
        echo "   Email: superadmin@perpustakaan.com\n";
        echo "   Password: superadmin123\n";
        echo "   NIM: SUPER001\n";
        echo "   Status: ACTIVE\n\n";

        echo "👨‍💼 ADMIN:\n";
        echo "   Email: admin@perpustakaan.com\n";
        echo "   Password: admin123\n";
        echo "   NIM: ADM001\n";
        echo "   Status: ACTIVE\n\n";

        echo "👩‍💻 ADMIN IT:\n";
        echo "   Email: admin.it@perpustakaan.com\n";
        echo "   Password: adminit123\n";
        echo "   NIM: ADM002\n";
        echo "   Status: ACTIVE\n\n";

        echo "👤 ACTIVE USER:\n";
        echo "   Email: user@example.com\n";
        echo "   Password: password123\n";
        echo "   NIM: 20240001\n";
        echo "   Status: ACTIVE\n\n";

        echo "👤 PENDING USER (need complete membership):\n";
        echo "   Email: jane.smith@example.com\n";
        echo "   Password: password123\n";
        echo "   NIM: 20240003\n";
        echo "   Status: PENDING\n\n";

        echo "👤 INACTIVE USER:\n";
        echo "   Email: sarah.conor@example.com\n";
        echo "   Password: password123\n";
        echo "   NIM: 20240005\n";
        echo "   Status: INACTIVE\n\n";

        echo "👤 SUSPENDED USER:\n";
        echo "   Email: david.lee@example.com\n";
        echo "   Password: password123\n";
        echo "   NIM: 20240006\n";
        echo "   Status: SUSPENDED\n\n";

        echo "📋 STATUS BREAKDOWN:\n";
        echo str_repeat("-", 20) . "\n";
        $statusCount = User::select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status')
            ->toArray();

        foreach ($statusCount as $status => $total) {
            echo "   {$status}: {$total} users\n";
        }

        echo "\n👥 ROLE BREAKDOWN:\n";
        echo str_repeat("-", 20) . "\n";
        $roleCount = User::select('role', DB::raw('count(*) as total'))
            ->groupBy('role')
            ->pluck('total', 'role')
            ->toArray();

        foreach ($roleCount as $role => $total) {
            echo "   {$role}: {$total} users\n";
        }

        echo "\n🚀 Ready for API testing!\n";
    }
}

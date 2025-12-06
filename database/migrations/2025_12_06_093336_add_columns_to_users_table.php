<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('nim')->nullable()->unique()->after('email');
            $table->string('phone')->nullable()->after('nim');
            $table->enum('role', ['admin', 'mahasiswa'])->default('mahasiswa')->after('phone');
            $table->string('tempat_lahir')->nullable()->after('role');
            $table->date('tanggal_lahir')->nullable()->after('tempat_lahir');
            $table->string('agama')->nullable()->after('tanggal_lahir');
            $table->text('alamat_asal')->nullable()->after('agama');
            $table->text('alamat_sekarang')->nullable()->after('alamat_asal');
            $table->string('foto')->nullable()->after('alamat_sekarang');
            $table->boolean('is_anggota')->default(false)->after('foto');
            $table->boolean('is_active')->default(true)->after('is_anggota');
            $table->softDeletes();
        });

        // Insert admin default
        DB::table('users')->insert([
            'name' => 'Admin Perpustakaan',
            'email' => 'admin@perpustakaan.com',
            'nim' => 'ADM001',
            'phone' => '081234567890',
            'role' => 'admin',
            'password' => bcrypt('admin123'),
            'is_anggota' => true,
            'email_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'nim', 'phone', 'role', 'tempat_lahir',
                'tanggal_lahir', 'agama', 'alamat_asal',
                'alamat_sekarang', 'foto', 'is_anggota', 'is_active'
            ]);
            $table->dropSoftDeletes();
        });
    }
};

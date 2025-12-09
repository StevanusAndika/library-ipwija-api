<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            // Tambah kolom timestamp untuk tracking
            $table->timestamp('approved_at')->nullable()->after('status');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->timestamp('borrowed_at')->nullable()->after('rejected_at');
            $table->timestamp('returned_at')->nullable()->after('borrowed_at');
            $table->text('rejection_reason')->nullable()->after('rejected_at');

            // Hapus kolom yang tidak sesuai jika ada
            $table->dropColumn('extended_date'); // Kita sudah punya extended_due_date
        });
    }

    public function down(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropColumn(['approved_at', 'rejected_at', 'borrowed_at', 'returned_at', 'rejection_reason']);
            $table->date('extended_date')->nullable()->after('is_extended');
        });
    }
};

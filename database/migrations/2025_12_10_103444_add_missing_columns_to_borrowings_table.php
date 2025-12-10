<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            // Tambahkan kolom yang hilang
            if (!Schema::hasColumn('borrowings', 'approved_at')) {
                $table->datetime('approved_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('borrowings', 'borrowed_at')) {
                $table->datetime('borrowed_at')->nullable()->after('approved_at');
            }

            if (!Schema::hasColumn('borrowings', 'returned_at')) {
                $table->datetime('returned_at')->nullable()->after('borrowed_at');
            }

            if (!Schema::hasColumn('borrowings', 'rejected_at')) {
                $table->datetime('rejected_at')->nullable()->after('returned_at');
            }

            if (!Schema::hasColumn('borrowings', 'rejection_reason')) {
                $table->text('rejection_reason')->nullable()->after('rejected_at');
            }

            if (!Schema::hasColumn('borrowings', 'extended_at')) {
                $table->datetime('extended_at')->nullable()->after('extended_due_date');
            }
        });
    }

    public function down(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            $table->dropColumn([
                'approved_at',
                'borrowed_at',
                'returned_at',
                'rejected_at',
                'rejection_reason',
                'extended_at'
            ]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            // Hapus unique constraint sementara
            $table->dropUnique(['borrow_code']);

            // Buat kolom nullable atau beri default value
            $table->string('borrow_code')->nullable()->change();
        });

        // Generate borrow_code untuk data existing
        $this->generateBorrowCodes();
    }

    public function down(): void
    {
        Schema::table('borrowings', function (Blueprint $table) {
            // Kembalikan ke semula
            $table->string('borrow_code')->nullable(false)->change();
            $table->unique('borrow_code');
        });
    }

    private function generateBorrowCodes()
    {
        // Update semua data yang borrow_code-nya null
        \DB::table('borrowings')->whereNull('borrow_code')->get()->each(function ($borrowing) {
            $borrowCode = 'BOR-' . strtoupper(Str::random(6)) . '-' . date('Ymd');
            \DB::table('borrowings')
                ->where('id', $borrowing->id)
                ->update(['borrow_code' => $borrowCode]);
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('books', function (Blueprint $table) {
            $table->id();
            $table->string('isbn')->unique()->nullable();
            $table->string('title');
            $table->string('slug')->unique();
            $table->foreignId('category_id')->constrained()->onDelete('cascade');
            $table->string('author');
            $table->string('publisher');
            $table->year('publication_year');
            $table->integer('stock')->default(0);
            $table->integer('available_stock')->default(0);
            $table->enum('book_type', ['hardcopy', 'softcopy'])->default('hardcopy');
            $table->string('file_path')->nullable();
            $table->string('cover_image')->nullable();
            $table->text('description')->nullable();
            $table->text('synopsis')->nullable(); // TAMBAH KOLOM SINOPIS
            $table->integer('pages')->nullable(); // TAMBAH KOLOM JUMLAH HALAMAN
            $table->string('language')->default('Indonesia'); // TAMBAH KOLOM BAHASA
            $table->unsignedTinyInteger('status')->default(1); // UBAH STATUS: 1 aktif, 0 tidak aktif
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('books');
    }
};

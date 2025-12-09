<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('type', ['umum', 'penelitian', 'fiksi', 'non-fiksi', 'akademik', 'referensi'])->default('umum');
            $table->text('description')->nullable();
            $table->integer('max_borrow_days')->default(7);
            $table->boolean('can_borrow')->default(true);
            $table->tinyInteger('status')->default(1); // GANTI is_active menjadi status
            $table->timestamps();
            $table->softDeletes();
        });

        // Insert default categories
        $categories = [
            ['name' => 'Novel', 'slug' => 'novel', 'type' => 'fiksi', 'can_borrow' => true, 'status' => 1],
            ['name' => 'Teknologi', 'slug' => 'teknologi', 'type' => 'umum', 'can_borrow' => true, 'status' => 1],
            ['name' => 'Penelitian', 'slug' => 'penelitian', 'type' => 'penelitian', 'can_borrow' => false, 'status' => 1],
            ['name' => 'Akademik', 'slug' => 'akademik', 'type' => 'akademik', 'can_borrow' => true, 'status' => 1],
            ['name' => 'Sejarah', 'slug' => 'sejarah', 'type' => 'non-fiksi', 'can_borrow' => true, 'status' => 1],
            ['name' => 'Referensi', 'slug' => 'referensi', 'type' => 'referensi', 'can_borrow' => true, 'status' => 1],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->insert([
                'name' => $category['name'],
                'slug' => $category['slug'],
                'type' => $category['type'],
                'can_borrow' => $category['can_borrow'],
                'status' => $category['status'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};

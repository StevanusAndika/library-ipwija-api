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
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        // Insert default categories
        $categories = [
            ['name' => 'Novel', 'slug' => 'novel', 'type' => 'fiksi', 'can_borrow' => true],
            ['name' => 'Teknologi', 'slug' => 'teknologi', 'type' => 'umum', 'can_borrow' => true],
            ['name' => 'Penelitian', 'slug' => 'penelitian', 'type' => 'penelitian', 'can_borrow' => false],
            ['name' => 'Akademik', 'slug' => 'akademik', 'type' => 'akademik', 'can_borrow' => true],
            ['name' => 'Sejarah', 'slug' => 'sejarah', 'type' => 'non-fiksi', 'can_borrow' => true],
            ['name' => 'Referensi', 'slug' => 'referensi', 'type' => 'referensi', 'can_borrow' => true],
        ];

        foreach ($categories as $category) {
            DB::table('categories')->insert([
                'name' => $category['name'],
                'slug' => $category['slug'],
                'type' => $category['type'],
                'can_borrow' => $category['can_borrow'],
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

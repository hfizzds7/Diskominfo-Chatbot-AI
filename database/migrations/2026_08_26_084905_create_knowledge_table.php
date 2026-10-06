<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('knowledge')) {
            return;
        }

        Schema::create('knowledge', function (Blueprint $table) {
            $table->id();
            $table->string('source_url');       // Menyimpan URL web asal scraping
            $table->string('title')->nullable(); // Menyimpan judul halaman/berita
            $table->longText('content');       // Menyimpan isi teks hasil scraping
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge');
    }
};
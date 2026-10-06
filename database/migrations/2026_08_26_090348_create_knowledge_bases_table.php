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
        Schema::create('knowledge_bases', function (Blueprint $table) {
            $table->id();
            $table->string('url')->unique(); // Mencegah duplikasi scraping & sebagai sumber sitasi AI
            $table->string('cluster'); // Membedakan klaster layanan
            $table->string('topic')->nullable(); // Judul/heading utama dari web atau nama dokumen PDF
            $table->longText('content'); // Menggunakan longText agar menampung ekstraksi teks yang panjang
            $table->json('keywords')->nullable(); // Tipe JSON agar selaras dengan $casts array di Model
            $table->text('description')->nullable(); // Meta deskripsi/ringkasan singkat konteks
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('knowledge_bases');
    }
};
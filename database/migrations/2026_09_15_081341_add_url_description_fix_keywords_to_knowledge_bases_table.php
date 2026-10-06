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
        Schema::table('knowledge_bases', function (Blueprint $table) {
            //
            if (! Schema::hasColumn('knowledge_bases', 'url')) {
                $table->string('url')->nullable()->unique();
            }
            if (! Schema::hasColumn('knowledge_bases', 'description')) {
                $table->text('description')->nullable();
            }
            if (! Schema::hasColumn('knowledge_bases', 'keywords')) {
                $table->json('keywords')->nullable();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('knowledge_bases', function (Blueprint $table) {
            //
            $columnsToDrop = [];

            if (Schema::hasColumn('knowledge_bases', 'url')) {
                $columnsToDrop[] = 'url';
            }
            if (Schema::hasColumn('knowledge_bases', 'description')) {
                $columnsToDrop[] = 'description';
            }
            if (Schema::hasColumn('knowledge_bases', 'keywords')) {
                $columnsToDrop[] = 'keywords';
            }

            if (! empty($columnsToDrop)) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};

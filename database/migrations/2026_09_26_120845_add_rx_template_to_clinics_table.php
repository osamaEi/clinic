<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            // Scanned prescription paper the clinic prints on, and where the text goes on it.
            $table->string('rx_template_path')->nullable()->after('fees');
            $table->json('rx_settings')->nullable()->after('rx_template_path');
        });
    }

    public function down(): void
    {
        Schema::table('clinics', function (Blueprint $table) {
            $table->dropColumn(['rx_template_path', 'rx_settings']);
        });
    }
};

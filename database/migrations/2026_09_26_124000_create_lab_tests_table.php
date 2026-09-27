<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The doctor's list of tests / imaging / procedures picked from in the exam screen.
 * Synced like the other clinic data (client ids, version, last-write-wins).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('lab_tests', function (Blueprint $table) {
            $table->unsignedBigInteger('id')->primary();
            $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
            $table->unsignedBigInteger('version')->default(0);
            $table->unsignedBigInteger('client_updated_at')->default(0);
            $table->string('name');
            $table->string('kind', 30)->nullable(); // تحليل | أشعة | إجراء
            $table->string('note')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['clinic_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_tests');
    }
};

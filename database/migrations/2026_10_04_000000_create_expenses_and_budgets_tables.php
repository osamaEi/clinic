<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The clinic's budget: what it spends (expenses) and the monthly limit per spending
 * category (budgets). Income is not stored here — it is the paid appointments.
 * Synced like the other clinic data (client ids, version, last-write-wins).
 */
return new class extends Migration
{
    private function syncColumns(Blueprint $table): void
    {
        $table->unsignedBigInteger('id')->primary();
        $table->foreignId('clinic_id')->constrained('clinics')->cascadeOnDelete();
        $table->unsignedBigInteger('version')->default(0);
        $table->unsignedBigInteger('client_updated_at')->default(0);
    }

    private function syncIndexes(Blueprint $table): void
    {
        $table->timestamps();
        $table->softDeletes();
        $table->index(['clinic_id', 'version']);
    }

    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $this->syncColumns($table);
            $table->string('category', 60);
            $table->decimal('amount', 12, 2);
            $table->date('date');
            $table->string('method', 20)->nullable();
            $table->string('note')->nullable();
            $this->syncIndexes($table);
            $table->index(['clinic_id', 'date']);
        });

        Schema::create('budgets', function (Blueprint $table) {
            $this->syncColumns($table);
            $table->string('category', 60);
            $table->decimal('amount', 12, 2);
            $this->syncIndexes($table);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
        Schema::dropIfExists('expenses');
    }
};

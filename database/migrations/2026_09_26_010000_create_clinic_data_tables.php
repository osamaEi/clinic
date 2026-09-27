<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Clinic data is created offline on the client, so primary keys are
 * client-generated numeric ids (not auto-increment). Every row carries:
 *  - version            per-clinic sync counter value at last write (pull cursor)
 *  - client_updated_at  client clock in ms, used for last-write-wins
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
        Schema::create('patients', function (Blueprint $table) {
            $this->syncColumns($table);
            $table->unsignedInteger('file_no')->nullable();
            $table->string('name');
            $table->string('phone')->nullable();
            $table->unsignedSmallInteger('age')->nullable();
            $table->string('gender', 10)->nullable();
            $table->string('blood', 20)->nullable();
            $table->text('chronic')->nullable();
            $table->text('allergy')->nullable();
            $table->string('bp', 20)->nullable();
            $table->string('sugar', 30)->nullable();
            $table->decimal('weight', 5, 1)->nullable();
            $table->unsignedSmallInteger('height')->nullable();
            $table->date('vitals_date')->nullable();
            $table->text('note')->nullable();
            $table->decimal('visit_fee', 10, 2)->nullable();
            $table->date('registered_on')->nullable();
            $this->syncIndexes($table);
            $table->unique(['clinic_id', 'file_no']);
        });

        Schema::create('appointments', function (Blueprint $table) {
            $this->syncColumns($table);
            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('patients');
            $table->date('date');
            $table->string('time', 5);
            $table->string('type', 30);
            $table->string('status', 30);
            $table->decimal('fee', 10, 2)->default(0);
            $table->boolean('paid')->default(false);
            $table->string('method', 20)->nullable();
            $table->string('arrived', 5)->nullable();
            $this->syncIndexes($table);
            $table->index(['clinic_id', 'date']);
        });

        Schema::create('medical_records', function (Blueprint $table) {
            $this->syncColumns($table);
            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('patients');
            $table->date('date');
            $table->text('complaint')->nullable();
            $table->text('diagnosis');
            $table->text('rx')->nullable();
            $table->text('tests')->nullable();
            $table->string('next_visit')->nullable();
            $this->syncIndexes($table);
        });

        Schema::create('treatments', function (Blueprint $table) {
            $this->syncColumns($table);
            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('patients');
            $table->string('drug');
            $table->string('dose')->nullable();
            $table->string('duration')->nullable();
            $table->date('start_date')->nullable();
            $table->text('note')->nullable();
            $table->boolean('active')->default(true);
            $this->syncIndexes($table);
        });

        Schema::create('patient_files', function (Blueprint $table) {
            $this->syncColumns($table);
            $table->unsignedBigInteger('patient_id');
            $table->foreign('patient_id')->references('id')->on('patients');
            $table->unsignedBigInteger('record_id')->nullable();
            $table->string('name');
            $table->string('mime', 100);
            $table->unsignedBigInteger('size')->default(0);
            $table->string('kind', 30)->nullable();
            $table->text('note')->nullable();
            $table->date('date')->nullable();
            $table->string('path')->nullable();
            $this->syncIndexes($table);
        });
    }

    public function down(): void
    {
        foreach (['patient_files', 'treatments', 'medical_records', 'appointments', 'patients'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('name');
            $table->decimal('price_monthly', 10, 2)->default(0);
            // null = unlimited
            $table->unsignedInteger('max_users')->nullable();
            $table->unsignedInteger('max_patients')->nullable();
            $table->unsignedInteger('max_storage_mb')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('clinics', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('specialty')->nullable();
            $table->string('doctor_name')->nullable();
            $table->string('phone')->nullable();
            $table->string('address')->nullable();
            $table->string('open_time', 5)->default('10:00');
            $table->string('close_time', 5)->default('22:00');
            $table->json('fees')->nullable();
            $table->foreignId('plan_id')->constrained('plans');
            $table->string('status')->default('trial'); // trial | active | suspended
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('subscription_ends_at')->nullable();
            // Monotonic per-clinic counter; every synced row stores the value it was written at.
            $table->unsignedBigInteger('sync_version')->default(0);
            $table->unsignedBigInteger('settings_version')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('clinics');
        Schema::dropIfExists('plans');
    }
};

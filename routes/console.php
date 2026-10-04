<?php

use App\Models\Clinic;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('clinic:seed-demo {phone}', function (string $phone) {
    $clinics = Clinic::where('phone', $phone)->get();
    if ($clinics->isEmpty()) {
        $this->error("No clinic with phone {$phone}");

        return 1;
    }
    foreach ($clinics as $clinic) {
        DB::transaction(fn () => DemoDataSeeder::seed($clinic));
        $this->info("Seeded demo data into clinic #{$clinic->id} ({$clinic->name})");
    }
})->purpose('Seed demo patients/appointments/records into the clinic(s) with this phone');

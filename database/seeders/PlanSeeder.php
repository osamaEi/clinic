<?php

namespace Database\Seeders;

use App\Models\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            ['slug' => 'basic', 'name' => 'أساسي', 'price_monthly' => 299, 'max_users' => 2, 'max_patients' => 1500, 'max_storage_mb' => 1024, 'sort' => 1],
            ['slug' => 'pro', 'name' => 'احترافي', 'price_monthly' => 599, 'max_users' => 6, 'max_patients' => null, 'max_storage_mb' => 10240, 'sort' => 2],
            ['slug' => 'center', 'name' => 'مركز طبي', 'price_monthly' => 1299, 'max_users' => null, 'max_patients' => null, 'max_storage_mb' => 51200, 'sort' => 3],
        ];

        foreach ($plans as $plan) {
            Plan::updateOrCreate(['slug' => $plan['slug']], $plan);
        }
    }
}

<?php

namespace App\Support;

use App\Models\Clinic;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ClinicRegistration
{
    /**
     * @param  array{clinic_name: string, name: string, email: string, password: string, specialty?: ?string, phone?: ?string, plan?: ?string}  $data
     */
    public function register(array $data): User
    {
        $plan = Plan::where('slug', $data['plan'] ?? 'basic')->where('is_active', true)->firstOrFail();

        return DB::transaction(function () use ($data, $plan): User {
            $clinic = Clinic::create([
                'name' => $data['clinic_name'],
                'specialty' => $data['specialty'] ?? null,
                'phone' => $data['phone'] ?? null,
                'doctor_name' => $data['name'],
                'fees' => Clinic::DEFAULT_FEES,
                'plan_id' => $plan->id,
                'status' => 'trial',
                'trial_ends_at' => now()->addDays(Clinic::TRIAL_DAYS),
            ]);

            return User::create([
                'clinic_id' => $clinic->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => 'doctor',
                'is_owner' => true,
            ]);
        });
    }
}

<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Model events must stay on: the Syncable trait stamps each row's sync version on save.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(PlanSeeder::class);

        User::updateOrCreate(['email' => 'admin@clinic.test'], [
            'name' => 'مدير المنصة',
            'password' => 'password',
            'is_super_admin' => true,
            'role' => 'doctor',
        ]);

        if (Clinic::where('name', 'عيادة الوفاء')->exists()) {
            return;
        }

        $clinic = Clinic::create([
            'name' => 'عيادة الوفاء',
            'specialty' => 'باطنة وجهاز هضمي',
            'doctor_name' => 'د. أحمد سمير',
            'phone' => '0100 123 4567',
            'address' => '٢٧ شارع جامعة الدول العربية، المهندسين',
            'fees' => Clinic::DEFAULT_FEES,
            'plan_id' => Plan::where('slug', 'pro')->value('id'),
            'status' => 'active',
            'subscription_ends_at' => now()->addYear(),
        ]);

        foreach ([
            ['د. أحمد سمير', 'doctor@demo.test', 'doctor', true],
            ['أ. منى خالد', 'nurse@demo.test', 'nurse', false],
            ['أ. هبة سعيد', 'reception@demo.test', 'secretary', false],
        ] as [$name, $email, $role, $owner]) {
            User::create(['clinic_id' => $clinic->id, 'name' => $name, 'email' => $email,
                'password' => 'password', 'role' => $role, 'is_owner' => $owner]);
        }

        DemoDataSeeder::seed($clinic, fixedIds: true);
    }
}

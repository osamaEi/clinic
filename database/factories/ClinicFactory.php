<?php

namespace Database\Factories;

use App\Models\Clinic;
use App\Models\Plan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Clinic>
 */
class ClinicFactory extends Factory
{
    /**
     * Define the model's default state: a clinic in its free trial on the basic plan.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'عيادة '.fake()->unique()->lastName(),
            'specialty' => 'باطنة',
            'fees' => Clinic::DEFAULT_FEES,
            'plan_id' => fn (): int => Plan::where('slug', 'basic')->value('id'),
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(Clinic::TRIAL_DAYS),
        ];
    }

    public function onPlan(string $slug): static
    {
        return $this->state(fn (): array => ['plan_id' => Plan::where('slug', $slug)->value('id')]);
    }

    public function trialExpired(): static
    {
        return $this->state(fn (): array => ['trial_ends_at' => now()->subDay()]);
    }
}

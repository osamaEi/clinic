<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\User;
use Database\Seeders\PlanSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

abstract class ApiTestCase extends TestCase
{
    use RefreshDatabase;

    protected int $seq = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(PlanSeeder::class);
    }

    protected function makeClinic(string $plan = 'basic'): Clinic
    {
        return Clinic::factory()->onPlan($plan)->create();
    }

    protected function member(Clinic $clinic, string $role = 'doctor', bool $owner = true): User
    {
        return User::factory()->memberOf($clinic, $role)->when($owner, fn ($f) => $f->owner())->create();
    }

    protected function actingAsMember(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    /** Client-style id (ms timestamp * 1000 + n), as the PWA generates them. */
    protected function cid(): int
    {
        return 1_700_000_000_000_000 + (++$this->seq);
    }

    protected function change(string $entity, int $id, array $data = [], string $op = 'upsert', ?int $at = null): array
    {
        return ['entity' => $entity, 'op' => $op, 'id' => $id, 'updatedAt' => $at ?? 1000 + $this->seq, 'data' => $data];
    }

    protected function push(array ...$changes)
    {
        return $this->postJson('/api/sync', ['changes' => $changes]);
    }

    protected function patientData(array $over = []): array
    {
        return $over + ['name' => 'مريض تجربة', 'phone' => '0100', 'age' => '30', 'gender' => 'ذكر', 'weight' => ''];
    }
}

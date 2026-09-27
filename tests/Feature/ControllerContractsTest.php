<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\PatientFile;
use App\Models\Plan;
use App\Models\User;
use App\Support\Subscriptions\SubscriptionAction;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\TestWith;

class ControllerContractsTest extends ApiTestCase
{
    public function test_admin_login_creates_a_session_and_logout_ends_it(): void
    {
        $admin = User::factory()->superAdmin()->create();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.clinics'));
        $this->assertAuthenticatedAs($admin, 'web');

        $this->post('/admin/logout')->assertRedirect(route('admin.login'));
        $this->assertGuest('web');
    }

    public function test_admin_login_rejects_clinic_members(): void
    {
        $user = $this->member($this->makeClinic());

        $this->post('/admin/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors(['email' => 'بيانات الدخول غلط.']);

        $this->assertGuest('web');
    }

    public function test_inactive_user_cannot_log_in_or_receive_a_token(): void
    {
        $user = User::factory()->memberOf($this->makeClinic())->create(['is_active' => false]);

        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email' => 'الحساب ده متوقف. كلّم صاحب العيادة.']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_logout_revokes_only_the_current_api_token(): void
    {
        $user = $this->member($this->makeClinic());
        $current = $user->createToken('current');
        $other = $user->createToken('other');

        $this->withToken($current->plainTextToken)->postJson('/api/logout')->assertNoContent();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $current->accessToken->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->id]);
    }

    public function test_registration_on_an_inactive_plan_does_not_create_an_account(): void
    {
        Plan::where('slug', 'pro')->update(['is_active' => false]);

        $this->postJson('/api/register', [
            'clinic_name' => 'Example Clinic', 'name' => 'Doctor',
            'email' => 'doctor@example.test', 'password' => 'password', 'plan' => 'pro',
        ])->assertNotFound();

        $this->assertDatabaseCount('clinics', 0);
        $this->assertDatabaseCount('users', 0);
        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_admin_search_keeps_dashboard_totals_independent_of_the_filter(): void
    {
        $this->freezeTime();
        $active = Clinic::factory()->create([
            'status' => 'active', 'subscription_ends_at' => now()->addMonth(),
        ]);
        $owner = $this->member($active);
        $this->makeClinic();
        Clinic::factory()->trialExpired()->create();
        Clinic::factory()->create(['status' => 'suspended']);
        $admin = User::factory()->superAdmin()->create();

        $response = $this->actingAs($admin)->get('/admin?q='.urlencode($owner->email));

        $response->assertOk()->assertViewHas('q', $owner->email);
        $this->assertSame([$active->id], $response->viewData('clinics')->pluck('id')->all());
        $this->assertSame([
            'total' => 4, 'trial' => 1, 'active' => 1, 'expired' => 2, 'mrr' => 299.0,
        ], $response->viewData('stats'));
    }

    public function test_renewal_extends_remaining_paid_time(): void
    {
        $this->travelTo(now()->setDate(2026, 1, 10)->startOfDay());
        $clinic = Clinic::factory()->create([
            'status' => 'active', 'subscription_ends_at' => '2026-02-10',
        ]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->patch("/admin/clinics/{$clinic->id}", ['action' => 'extend', 'months' => 2])
            ->assertRedirect()
            ->assertSessionHas('status', "{$clinic->name}: الاشتراك اتجدد لحد 2026-04-10");

        $this->assertSame('2026-04-10', $clinic->fresh()->subscription_ends_at->toDateString());
    }

    public function test_admin_can_suspend_a_clinic(): void
    {
        $clinic = $this->makeClinic();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->patch("/admin/clinics/{$clinic->id}", ['action' => 'suspend'])
            ->assertRedirect()->assertSessionHas('status', "{$clinic->name}: العيادة اتوقفت (قراءة فقط).");

        $this->assertSame('suspended', $clinic->fresh()->status);
    }

    #[TestWith([true, 'active'])]
    #[TestWith([false, 'trial'])]
    public function test_resume_uses_the_remaining_paid_period(bool $paid, string $expectedStatus): void
    {
        $this->freezeTime();
        $clinic = Clinic::factory()->create([
            'status' => 'suspended',
            'subscription_ends_at' => $paid ? now()->addMonth() : now()->subDay(),
        ]);
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->patch("/admin/clinics/{$clinic->id}", ['action' => 'resume'])
            ->assertRedirect()->assertSessionHas('status', "{$clinic->name}: العيادة رجعت تشتغل.");

        $this->assertSame($expectedStatus, $clinic->fresh()->status);
    }

    public function test_admin_can_change_a_clinic_plan(): void
    {
        $clinic = $this->makeClinic();
        $plan = Plan::where('slug', 'pro')->firstOrFail();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->patch("/admin/clinics/{$clinic->id}", ['action' => 'plan', 'plan_id' => $plan->id])
            ->assertRedirect()->assertSessionHas('status', "{$clinic->name}: الباقة اتغيّرت.");

        $this->assertSame($plan->id, $clinic->fresh()->plan_id);
    }

    #[TestWith([[]])]
    #[TestWith([['plan_id' => null]])]
    #[TestWith([['plan_id' => 99999]])]
    public function test_plan_change_requires_an_existing_plan(array $payload): void
    {
        $clinic = $this->makeClinic();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->patch("/admin/clinics/{$clinic->id}", ['action' => 'plan'] + $payload)
            ->assertSessionHasErrors('plan_id');

        $this->assertSame($clinic->plan_id, $clinic->fresh()->plan_id);
    }

    public function test_unknown_subscription_action_does_not_modify_the_clinic(): void
    {
        $clinic = $this->makeClinic();
        $admin = User::factory()->superAdmin()->create();

        $this->actingAs($admin)->patch("/admin/clinics/{$clinic->id}", ['action' => 'delete'])
            ->assertSessionHasErrors('action');

        $this->assertModelExists($clinic);
        $this->assertSame('trial', $clinic->fresh()->status);
    }

    public function test_a_registered_subscription_action_is_accepted_without_controller_changes(): void
    {
        $clinic = $this->makeClinic();
        $admin = User::factory()->superAdmin()->create();
        $this->app->instance('test.subscription.action', new class implements SubscriptionAction
        {
            public function name(): string
            {
                return 'reset_trial';
            }

            public function apply(Clinic $clinic, array $data): string
            {
                $clinic->trial_ends_at = '2027-01-15';

                return 'Trial reset.';
            }
        });
        $this->app->tag('test.subscription.action', 'clinic.subscription.actions');

        $this->actingAs($admin)->patch("/admin/clinics/{$clinic->id}", ['action' => 'reset_trial'])
            ->assertRedirect()->assertSessionHas('status', "{$clinic->name}: Trial reset.");

        $this->assertSame('2027-01-15', $clinic->fresh()->trial_ends_at->toDateString());
    }

    #[TestWith(['patch'])]
    #[TestWith(['delete'])]
    public function test_cross_clinic_staff_changes_return_404(string $method): void
    {
        $owner = $this->member($this->makeClinic());
        $other = $this->member($this->makeClinic(), 'nurse', false);
        $this->actingAsMember($owner);

        $this->json($method, "/api/users/{$other->id}", ['name' => 'Changed'])->assertNotFound();

        $this->assertModelExists($other);
        $this->assertSame($other->name, $other->fresh()->name);
    }

    #[TestWith(['patch', ['role' => 'nurse']])]
    #[TestWith(['patch', ['isActive' => false]])]
    #[TestWith(['delete', []])]
    public function test_owner_account_is_protected_from_staff_changes(string $method, array $payload): void
    {
        $owner = $this->member($this->makeClinic());
        $this->actingAsMember($owner);

        $this->json($method, "/api/users/{$owner->id}", $payload)->assertUnprocessable();

        $this->assertModelExists($owner);
        $this->assertTrue($owner->fresh()->is_active);
        $this->assertSame('doctor', $owner->fresh()->role);
    }

    public function test_reactivating_staff_requires_an_available_seat(): void
    {
        $clinic = $this->makeClinic();
        $owner = $this->member($clinic);
        $this->member($clinic, 'nurse', false);
        $inactive = User::factory()->memberOf($clinic)->create(['is_active' => false]);
        $this->actingAsMember($owner);

        $this->patchJson("/api/users/{$inactive->id}", ['isActive' => true])->assertUnprocessable();

        $this->assertFalse($inactive->fresh()->is_active);
    }

    public function test_deactivating_staff_revokes_their_tokens(): void
    {
        $clinic = $this->makeClinic();
        $owner = $this->member($clinic);
        $staff = $this->member($clinic, 'nurse', false);
        $token = $staff->createToken('staff')->accessToken;
        $this->actingAsMember($owner);

        $this->patchJson("/api/users/{$staff->id}", ['isActive' => false])
            ->assertOk()->assertJsonPath('isActive', false);

        $this->assertFalse($staff->fresh()->is_active);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_deleting_staff_removes_the_account_and_tokens(): void
    {
        $clinic = $this->makeClinic();
        $owner = $this->member($clinic);
        $staff = $this->member($clinic, 'nurse', false);
        $token = $staff->createToken('staff')->accessToken;
        $this->actingAsMember($owner);

        $this->deleteJson("/api/users/{$staff->id}")->assertNoContent();

        $this->assertModelMissing($staff);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    #[TestWith(['put', '/api/clinic'])]
    #[TestWith(['put', '/api/clinic/rx'])]
    #[TestWith(['post', '/api/clinic/rx-template'])]
    #[TestWith(['delete', '/api/clinic/rx-template'])]
    #[TestWith(['post', '/api/files'])]
    #[TestWith(['post', '/api/sync'])]
    public function test_expired_subscription_is_checked_before_payload_validation(string $method, string $path): void
    {
        $clinic = Clinic::factory()->trialExpired()->create();
        $this->actingAsMember($this->member($clinic));

        $this->json($method, $path, [])->assertPaymentRequired();

        $this->assertSame($clinic->name, $clinic->fresh()->name);
        $this->assertDatabaseCount('patient_files', 0);
    }

    public function test_file_quota_failure_keeps_its_error_code_and_does_not_store_a_file(): void
    {
        Storage::fake(PatientFile::DISK);
        Plan::where('slug', 'basic')->update(['max_storage_mb' => 0]);
        $this->actingAsMember($this->member($this->makeClinic()));
        $patientId = $this->cid();
        $this->push($this->change('patients', $patientId, $this->patientData()))->assertOk();

        $this->postJson('/api/files', [
            'id' => $this->cid(), 'pid' => $patientId, 'updatedAt' => 5000,
            'file' => UploadedFile::fake()->image('xray.jpg'),
        ])->assertUnprocessable()->assertExactJson([
            'message' => 'مساحة التخزين في باقتك خلصت.', 'error' => 'plan_limit_storage',
        ]);

        $this->assertDatabaseCount('patient_files', 0);
        $this->assertSame([], Storage::disk(PatientFile::DISK)->allFiles());
    }

    public function test_reuploading_a_deleted_file_does_not_restore_it(): void
    {
        Storage::fake(PatientFile::DISK);
        $this->actingAsMember($this->member($this->makeClinic()));
        $patientId = $this->cid();
        $this->push($this->change('patients', $patientId, $this->patientData()))->assertOk();
        $fileId = $this->cid();
        $payload = ['id' => $fileId, 'pid' => $patientId, 'updatedAt' => 5000];
        $this->postJson('/api/files', $payload + ['file' => UploadedFile::fake()->image('xray.jpg')])->assertCreated();
        $file = PatientFile::findOrFail($fileId);
        $file->delete();
        $storedPaths = Storage::disk(PatientFile::DISK)->allFiles();

        $this->postJson('/api/files', $payload + ['file' => UploadedFile::fake()->image('retry.jpg')])
            ->assertOk()->assertExactJson(['row' => null]);

        $this->assertSoftDeleted($file);
        $this->assertSame($storedPaths, Storage::disk(PatientFile::DISK)->allFiles());
    }

    public function test_cross_clinic_file_id_collision_returns_409(): void
    {
        Storage::fake(PatientFile::DISK);
        $firstOwner = $this->member($this->makeClinic());
        $secondOwner = $this->member($this->makeClinic());
        $this->actingAsMember($firstOwner);
        $patientId = $this->cid();
        $this->push($this->change('patients', $patientId, $this->patientData()))->assertOk();
        $fileId = $this->cid();
        $payload = ['id' => $fileId, 'pid' => $patientId, 'updatedAt' => 5000];
        $this->postJson('/api/files', $payload + ['file' => UploadedFile::fake()->image('xray.jpg')])->assertCreated();
        $this->actingAsMember($secondOwner);

        $this->postJson('/api/files', $payload + ['file' => UploadedFile::fake()->image('collision.jpg')])
            ->assertConflict()->assertExactJson(['message' => 'id_conflict']);

        $this->assertDatabaseCount('patient_files', 1);
        $this->assertDatabaseHas('patient_files', ['id' => $fileId, 'clinic_id' => $firstOwner->clinic_id]);
    }
}

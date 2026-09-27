<?php

namespace Tests\Feature;

use App\Models\Clinic;
use App\Models\PatientFile;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

class SaasTest extends ApiTestCase
{
    public function test_register_creates_trial_clinic_with_owner_and_token(): void
    {
        $res = $this->postJson('/api/register', [
            'clinic_name' => 'عيادة النور', 'name' => 'د. سارة', 'email' => 'sara@test.local', 'password' => 'secret123',
        ])->assertCreated();

        $res->assertJsonPath('user.isOwner', true)
            ->assertJsonPath('clinic.state', 'trial')
            ->assertJsonPath('clinic.canWrite', true)
            ->assertJsonPath('clinic.daysLeft', 14)
            ->assertJsonPath('clinic.plan.slug', 'basic');
        $this->assertNotEmpty($res->json('token'));

        $this->withToken($res->json('token'))->getJson('/api/me')->assertOk()->assertJsonPath('clinic.name', 'عيادة النور');
    }

    public function test_clinic_endpoints_require_a_token(): void
    {
        $this->getJson('/api/sync?since=0')->assertUnauthorized();
        $this->postJson('/api/sync', ['changes' => []])->assertUnauthorized();
    }

    public function test_login_rejects_bad_password_and_super_admin(): void
    {
        $clinic = $this->makeClinic();
        $user = $this->member($clinic);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'nope'])->assertStatus(422);
        $this->postJson('/api/login', ['email' => $user->email, 'password' => 'password'])->assertOk()->assertJsonStructure(['token']);

        $admin = User::factory()->superAdmin()->create();
        $this->postJson('/api/login', ['email' => $admin->email, 'password' => 'password'])->assertStatus(422);
    }

    public function test_expired_clinic_is_read_only(): void
    {
        $clinic = Clinic::factory()->trialExpired()->create();
        $this->actingAsMember($this->member($clinic));

        $this->getJson('/api/me')->assertJsonPath('clinic.state', 'expired')->assertJsonPath('clinic.canWrite', false);
        $this->getJson('/api/sync?since=0')->assertOk();
        $this->push($this->change('patients', $this->cid(), $this->patientData()))->assertStatus(402);
    }

    public function test_patient_limit_is_enforced(): void
    {
        Plan::where('slug', 'basic')->update(['max_patients' => 1]);
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic));

        $this->push($this->change('patients', $this->cid(), $this->patientData()), $this->change('patients', $this->cid(), $this->patientData()))
            ->assertJsonPath('results.0.status', 'ok')
            ->assertJsonPath('results.1.error', 'plan_limit_patients');
    }

    public function test_owner_manages_staff_within_seat_limit(): void
    {
        $clinic = $this->makeClinic(); // basic: 2 users
        $owner = $this->member($clinic);
        $this->actingAsMember($owner);

        $nurse = $this->postJson('/api/users', ['name' => 'منى', 'email' => 'mona@test.local', 'password' => 'secret123', 'role' => 'nurse'])
            ->assertCreated()->json();
        $this->postJson('/api/users', ['name' => 'x', 'email' => 'x@test.local', 'password' => 'secret123', 'role' => 'secretary'])
            ->assertStatus(422);

        $this->patchJson("/api/users/{$nurse['id']}", ['isActive' => false])->assertOk()->assertJsonPath('isActive', false);
        $this->postJson('/api/users', ['name' => 'x', 'email' => 'x@test.local', 'password' => 'secret123', 'role' => 'secretary'])
            ->assertCreated();

        $this->actingAsMember(User::where('email', 'x@test.local')->first());
        $this->getJson('/api/users')->assertForbidden();
    }

    public function test_settings_update_bumps_settings_version(): void
    {
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic));
        $cursor = $this->getJson('/api/sync?since=0')->json('cursor');

        $this->putJson('/api/clinic', [
            'clinic' => ['name' => 'اسم جديد', 'open' => '09:00', 'close' => '21:00'],
            'fees' => ['كشف' => 400, 'متابعة' => 100],
        ])->assertOk();

        $this->getJson("/api/sync?since={$cursor}")->assertJsonPath('settings.clinic.name', 'اسم جديد')->assertJsonPath('settings.fees.كشف', 400);

        $this->actingAsMember($this->member($clinic, 'nurse', false));
        $this->putJson('/api/clinic', ['clinic' => ['name' => 'x', 'open' => '09:00', 'close' => '21:00'], 'fees' => ['كشف' => 1]])->assertForbidden();
    }

    public function test_file_upload_is_idempotent_and_served_by_signed_url(): void
    {
        Storage::fake(PatientFile::DISK);
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic));
        $pid = $this->cid();
        $this->push($this->change('patients', $pid, $this->patientData()));
        $fid = $this->cid();

        $payload = ['id' => $fid, 'pid' => $pid, 'kind' => 'أشعة', 'updatedAt' => 5000];
        $row = $this->post('/api/files', $payload + ['file' => UploadedFile::fake()->image('xray.jpg')], ['Accept' => 'application/json'])
            ->assertCreated()->json('row');
        $this->post('/api/files', $payload + ['file' => UploadedFile::fake()->image('xray.jpg')], ['Accept' => 'application/json'])
            ->assertOk()->assertJsonPath('row.id', $fid);
        $this->assertSame(1, PatientFile::count());

        $this->get($row['data'])->assertOk();
        $this->get("/files/{$fid}")->assertForbidden();

        $this->getJson('/api/sync?since=0')->assertJsonPath('changes.files.0.kind', 'أشعة');
        $this->push($this->change('files', $fid, ['kind' => 'تحليل'], at: 6000))->assertJsonPath('results.0.row.kind', 'تحليل');
    }

    public function test_super_admin_can_extend_subscription(): void
    {
        $clinic = Clinic::factory()->trialExpired()->create();
        $admin = User::factory()->superAdmin()->create();

        $this->get('/admin')->assertRedirect(route('admin.login'));
        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee($clinic->name);
        $this->actingAs($admin)->patch("/admin/clinics/{$clinic->id}", ['action' => 'extend', 'months' => 3])->assertRedirect();

        $clinic->refresh();
        $this->assertSame('active', $clinic->subscriptionState());
        $this->assertTrue($clinic->subscription_ends_at->greaterThan(now()->addMonths(2)));
    }

    public function test_non_admin_cannot_open_admin_panel(): void
    {
        $user = $this->member($this->makeClinic());
        $this->actingAs($user)->get('/admin')->assertRedirect(route('admin.login'));
    }
}

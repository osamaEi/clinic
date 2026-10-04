<?php

namespace Tests\Feature;

use App\Models\Patient;

class SyncTest extends ApiTestCase
{
    public function test_push_then_pull_round_trips_rows_in_client_shape(): void
    {
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic));
        $pid = $this->cid();
        $aid = $this->cid();

        // Child before parent in the payload: the server reorders by entity.
        $res = $this->push(
            $this->change('appts', $aid, ['pid' => $pid, 'date' => '2026-09-26', 'time' => '10:30', 'type' => 'كشف',
                'status' => 'محجوز', 'fee' => 300, 'paid' => false, 'method' => '']),
            $this->change('patients', $pid, $this->patientData()),
        )->assertOk();

        $res->assertJsonPath('results.0.entity', 'patients')
            ->assertJsonPath('results.0.status', 'ok')
            ->assertJsonPath('results.0.row.fileNo', 1)
            ->assertJsonPath('results.0.row.weight', '')
            ->assertJsonPath('results.1.status', 'ok');

        $pull = $this->getJson('/api/sync?since=0')->assertOk();
        $pull->assertJsonPath('changes.patients.0.id', $pid)
            ->assertJsonPath('changes.patients.0.age', 30)
            ->assertJsonPath('changes.appts.0.pid', $pid)
            ->assertJsonPath('changes.appts.0.paid', false)
            ->assertJsonPath('settings.fees.كشف', 300);

        $cursor = $pull->json('cursor');
        $this->assertGreaterThan(0, $cursor);
        $this->getJson("/api/sync?since={$cursor}")->assertJsonCount(0, 'changes.patients')->assertJsonPath('settings', null);
    }

    public function test_clinics_are_isolated(): void
    {
        $a = $this->makeClinic();
        $b = $this->makeClinic();
        $pid = $this->cid();

        $this->actingAsMember($this->member($a));
        $this->push($this->change('patients', $pid, $this->patientData()))->assertJsonPath('results.0.status', 'ok');

        $this->actingAsMember($this->member($b));
        $this->getJson('/api/sync?since=0')->assertJsonCount(0, 'changes.patients');

        // Clinic B can neither overwrite nor reference clinic A's patient.
        $this->push($this->change('patients', $pid, $this->patientData(['name' => 'hijack']), at: 99999999))
            ->assertJsonPath('results.0.status', 'rejected')
            ->assertJsonPath('results.0.error', 'id_conflict');
        $this->push($this->change('appts', $this->cid(), ['pid' => $pid, 'date' => '2026-09-26', 'time' => '10:00',
            'type' => 'كشف', 'status' => 'محجوز']))
            ->assertJsonPath('results.0.error', 'unknown_patient');

        $this->assertSame('مريض تجربة', Patient::withoutGlobalScopes()->find($pid)->name);
    }

    public function test_last_write_wins_and_stale_writes_return_server_row(): void
    {
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic));
        $pid = $this->cid();

        $this->push($this->change('patients', $pid, $this->patientData(['name' => 'v2']), at: 2000));
        $this->push($this->change('patients', $pid, $this->patientData(['name' => 'v1-offline']), at: 1500))
            ->assertJsonPath('results.0.status', 'stale')
            ->assertJsonPath('results.0.row.name', 'v2');
        $this->push($this->change('patients', $pid, $this->patientData(['name' => 'v3']), at: 2500))
            ->assertJsonPath('results.0.status', 'ok');

        $this->assertSame('v3', Patient::find($pid)->name);
    }

    public function test_deletes_are_pulled_as_tombstones(): void
    {
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic));
        $pid = $this->cid();
        $this->push($this->change('patients', $pid, $this->patientData(), at: 1000));
        $cursor = $this->getJson('/api/sync?since=0')->json('cursor');

        $this->push($this->change('patients', $pid, [], 'delete', 2000))->assertJsonPath('results.0.status', 'ok');

        $this->getJson("/api/sync?since={$cursor}")
            ->assertJsonPath('deleted.patients.0', $pid)
            ->assertJsonCount(0, 'changes.patients');
    }

    public function test_file_numbers_are_sequential_per_clinic(): void
    {
        $a = $this->makeClinic();
        $b = $this->makeClinic();
        $this->actingAsMember($this->member($a));
        $this->push($this->change('patients', $this->cid(), $this->patientData()), $this->change('patients', $this->cid(), $this->patientData()))
            ->assertJsonPath('results.1.row.fileNo', 2);
        $this->actingAsMember($this->member($b));
        $this->push($this->change('patients', $this->cid(), $this->patientData()))->assertJsonPath('results.0.row.fileNo', 1);
    }

    public function test_nurse_cannot_write_medical_records(): void
    {
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic));
        $pid = $this->cid();
        $this->push($this->change('patients', $pid, $this->patientData()));

        $this->actingAsMember($this->member($clinic, 'nurse', false));
        $this->push($this->change('records', $this->cid(), ['pid' => $pid, 'date' => '2026-09-26', 'diagnosis' => 'x']))
            ->assertJsonPath('results.0.status', 'rejected')
            ->assertJsonPath('results.0.error', 'forbidden');
    }

    public function test_doctor_keeps_a_medicine_list_that_other_roles_only_read(): void
    {
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic));
        $id = $this->cid();

        $this->push($this->change('drugs', $id, ['name' => 'أوميبرازول ٢٠مج', 'freq' => 'مرة يومياً', 'when' => 'قبل الأكل', 'dur' => '١٤ يوم', 'extra' => '']))
            ->assertJsonPath('results.0.status', 'ok')
            ->assertJsonPath('results.0.row.when', 'قبل الأكل');

        $this->actingAsMember($this->member($clinic, 'nurse', false));
        $this->getJson('/api/sync?since=0')->assertJsonPath('changes.drugs.0.dur', '١٤ يوم');
        $this->push($this->change('drugs', $id, ['name' => 'تغيير'], at: 99999999))
            ->assertJsonPath('results.0.error', 'forbidden')
            ->assertJsonPath('results.0.row.name', 'أوميبرازول ٢٠مج');
    }

    public function test_doctor_keeps_a_tests_list_with_known_kinds(): void
    {
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic));

        $this->push(
            $this->change('labs', $this->cid(), ['name' => 'صورة دم CBC', 'kind' => 'تحليل']),
            $this->change('labs', $this->cid(), ['name' => 'رنين', 'kind' => 'غير معروف']),
        )->assertJsonPath('results.0.status', 'ok')
            ->assertJsonPath('results.1.status', 'rejected');

        $this->getJson('/api/sync?since=0')->assertJsonCount(1, 'changes.labs')->assertJsonPath('changes.labs.0.kind', 'تحليل');
    }

    public function test_staff_record_expenses_but_only_the_doctor_sets_budget_limits(): void
    {
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic, 'secretary', false));
        $this->push(
            $this->change('expenses', $this->cid(), ['cat' => 'إيجار', 'amount' => 6000, 'date' => '2026-10-01T00:00:00', 'method' => 'تحويل', 'note' => '']),
            $this->change('expenses', $this->cid(), ['cat' => 'إيجار', 'amount' => -5, 'date' => '2026-10-01']),
            $this->change('budgets', $this->cid(), ['cat' => 'إيجار', 'amount' => 6000]),
        )->assertJsonPath('results.0.status', 'ok')
            ->assertJsonPath('results.0.row.date', '2026-10-01')
            ->assertJsonPath('results.0.row.amount', 6000)
            ->assertJsonPath('results.1.status', 'rejected')
            ->assertJsonPath('results.2.error', 'forbidden');

        $this->actingAsMember($this->member($clinic, 'nurse', false));
        $this->push($this->change('expenses', $this->cid(), ['cat' => 'صيانة', 'amount' => 100, 'date' => '2026-10-02']))
            ->assertJsonPath('results.0.error', 'forbidden');

        $this->actingAsMember($this->member($clinic));
        $this->push($this->change('budgets', $this->cid(), ['cat' => 'إيجار', 'amount' => 6500.5]))
            ->assertJsonPath('results.0.status', 'ok');
        $this->getJson('/api/sync?since=0')
            ->assertJsonCount(1, 'changes.expenses')
            ->assertJsonPath('changes.expenses.0.cat', 'إيجار')
            ->assertJsonPath('changes.budgets.0.amount', 6500.5);
    }

    public function test_invalid_rows_are_rejected_individually(): void
    {
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic));
        $this->push(
            $this->change('patients', $this->cid(), ['name' => '']),
            $this->change('patients', $this->cid(), $this->patientData()),
        )->assertJsonPath('results.0.status', 'rejected')->assertJsonPath('results.1.status', 'ok');
    }

    public function test_cursor_ahead_of_server_forces_full_resync(): void
    {
        $clinic = $this->makeClinic();
        $this->actingAsMember($this->member($clinic));
        $this->push($this->change('patients', $this->cid(), $this->patientData()));

        $this->getJson('/api/sync?since=999999')->assertJsonPath('reset', true)->assertJsonCount(1, 'changes.patients');
    }
}

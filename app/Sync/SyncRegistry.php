<?php

namespace App\Sync;

use App\Models\Appointment;
use App\Models\Budget;
use App\Models\Drug;
use App\Models\Expense;
use App\Models\LabTest;
use App\Models\MedicalRecord;
use App\Models\Patient;
use App\Models\PatientFile;
use App\Models\Treatment;

/**
 * Maps PWA collections (DB.patients, DB.appts, ...) to models.
 * `fields` maps client key => column; `rules` validate the client payload.
 * Order matters: parents are applied before children within one push.
 */
final class SyncRegistry
{
    public static function entities(): array
    {
        return [
            'patients' => [
                'model' => Patient::class,
                'roles' => ['doctor', 'nurse', 'secretary'],
                'fields' => [
                    'name' => 'name', 'phone' => 'phone', 'age' => 'age', 'gender' => 'gender',
                    'blood' => 'blood', 'chronic' => 'chronic', 'allergy' => 'allergy', 'bp' => 'bp',
                    'sugar' => 'sugar', 'weight' => 'weight', 'height' => 'height',
                    'vitalsDate' => 'vitals_date', 'note' => 'note', 'visitFee' => 'visit_fee',
                    'created' => 'registered_on',
                ],
                'rules' => [
                    'name' => 'required|string|max:255',
                    'phone' => 'nullable|string|max:40',
                    'age' => 'nullable|integer|min:0|max:150',
                    'gender' => 'nullable|string|max:10',
                    'blood' => 'nullable|string|max:20',
                    'chronic' => 'nullable|string|max:2000',
                    'allergy' => 'nullable|string|max:2000',
                    'bp' => 'nullable|string|max:20',
                    'sugar' => 'nullable|string|max:30',
                    'weight' => 'nullable|numeric|min:0|max:999',
                    'height' => 'nullable|integer|min:0|max:300',
                    'vitalsDate' => 'nullable|date',
                    'note' => 'nullable|string|max:5000',
                    'visitFee' => 'nullable|numeric|min:0',
                    'created' => 'nullable|date',
                ],
            ],
            'appts' => [
                'model' => Appointment::class,
                'roles' => ['doctor', 'nurse', 'secretary'],
                'fields' => [
                    'pid' => 'patient_id', 'date' => 'date', 'time' => 'time', 'type' => 'type',
                    'status' => 'status', 'fee' => 'fee', 'paid' => 'paid', 'method' => 'method', 'arrived' => 'arrived',
                ],
                'rules' => [
                    'pid' => 'required|integer',
                    'date' => 'required|date',
                    'time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
                    'type' => 'required|string|max:30',
                    'status' => 'required|in:محجوز,في الانتظار,عند الدكتور,تم الكشف,ملغي',
                    'fee' => 'nullable|numeric|min:0',
                    'paid' => 'boolean',
                    'method' => 'nullable|string|max:20',
                    'arrived' => ['nullable', 'regex:/^\d{2}:\d{2}$/'],
                ],
            ],
            'records' => [
                'model' => MedicalRecord::class,
                'roles' => ['doctor'],
                'fields' => [
                    'pid' => 'patient_id', 'date' => 'date', 'complaint' => 'complaint', 'diagnosis' => 'diagnosis',
                    'rx' => 'rx', 'tests' => 'tests', 'next' => 'next_visit',
                ],
                'rules' => [
                    'pid' => 'required|integer',
                    'date' => 'required|date',
                    'complaint' => 'nullable|string|max:5000',
                    'diagnosis' => 'required|string|max:2000',
                    'rx' => 'nullable|string|max:10000',
                    'tests' => 'nullable|string|max:2000',
                    'next' => 'nullable|string|max:255',
                ],
            ],
            'treatments' => [
                'model' => Treatment::class,
                'roles' => ['doctor'],
                'fields' => [
                    'pid' => 'patient_id', 'drug' => 'drug', 'dose' => 'dose', 'duration' => 'duration',
                    'start' => 'start_date', 'note' => 'note', 'active' => 'active',
                ],
                'rules' => [
                    'pid' => 'required|integer',
                    'drug' => 'required|string|max:255',
                    'dose' => 'nullable|string|max:255',
                    'duration' => 'nullable|string|max:255',
                    'start' => 'nullable|date',
                    'note' => 'nullable|string|max:2000',
                    'active' => 'boolean',
                ],
            ],
            // The doctor's medicine list: default dosing to pick from while writing a prescription.
            'drugs' => [
                'model' => Drug::class,
                'roles' => ['doctor'],
                'fields' => ['name' => 'name', 'freq' => 'freq', 'when' => 'timing', 'dur' => 'duration', 'extra' => 'note'],
                'rules' => [
                    'name' => 'required|string|max:255',
                    'freq' => 'nullable|string|max:60',
                    'when' => 'nullable|string|max:60',
                    'dur' => 'nullable|string|max:60',
                    'extra' => 'nullable|string|max:255',
                ],
            ],
            // Tests / imaging / procedures the doctor requests, picked from in the exam screen.
            'labs' => [
                'model' => LabTest::class,
                'roles' => ['doctor'],
                'fields' => ['name' => 'name', 'kind' => 'kind', 'note' => 'note'],
                'rules' => [
                    'name' => 'required|string|max:255',
                    'kind' => 'nullable|in:تحليل,أشعة,إجراء',
                    'note' => 'nullable|string|max:255',
                ],
            ],
            // What the clinic spends; income is the paid appointments.
            'expenses' => [
                'model' => Expense::class,
                'roles' => ['doctor', 'secretary'],
                'fields' => ['cat' => 'category', 'amount' => 'amount', 'date' => 'date', 'method' => 'method', 'note' => 'note'],
                'rules' => [
                    'cat' => 'required|string|max:60',
                    'amount' => 'required|numeric|min:0|max:9999999999',
                    'date' => 'required|date',
                    'method' => 'nullable|string|max:20',
                    'note' => 'nullable|string|max:255',
                ],
            ],
            // Monthly spending limit per expense category, set by the doctor.
            'budgets' => [
                'model' => Budget::class,
                'roles' => ['doctor'],
                'fields' => ['cat' => 'category', 'amount' => 'amount'],
                'rules' => [
                    'cat' => 'required|string|max:60',
                    'amount' => 'required|numeric|min:0|max:9999999999',
                ],
            ],
            // File content is uploaded through /api/files; sync only carries metadata edits and deletes.
            'files' => [
                'model' => PatientFile::class,
                'roles' => ['doctor', 'nurse', 'secretary'],
                'fields' => ['kind' => 'kind', 'note' => 'note'],
                'rules' => [
                    'kind' => 'nullable|string|max:30',
                    'note' => 'nullable|string|max:2000',
                ],
            ],
        ];
    }

    public static function names(): array
    {
        return array_keys(self::entities());
    }
}

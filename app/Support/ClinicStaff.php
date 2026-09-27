<?php

namespace App\Support;

use App\Models\Clinic;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class ClinicStaff
{
    /** @return Collection<int, User> */
    public function members(Clinic $clinic): Collection
    {
        return $clinic->users()->orderBy('id')->get();
    }

    public function find(Clinic $clinic, int $id): User
    {
        return $clinic->users()->findOrFail($id);
    }

    /** @param array{name: string, email: string, password: string, role: string} $data */
    public function create(Clinic $clinic, array $data): User
    {
        $this->ensureSeat($clinic);

        return $clinic->users()->create($data + ['is_owner' => false]);
    }

    /** @param array{name?: string, role?: string, isActive?: bool|int|string, password?: string} $data */
    public function update(Clinic $clinic, User $user, array $data): User
    {
        abort_if($user->is_owner && (($data['isActive'] ?? true) === false || isset($data['role'])), 422, 'مينفعش توقف أو تغيّر دور صاحب العيادة.');
        if (($data['isActive'] ?? false) && ! $user->is_active) {
            $this->ensureSeat($clinic);
        }

        $user->fill(array_filter([
            'name' => $data['name'] ?? null,
            'role' => $data['role'] ?? null,
            'password' => $data['password'] ?? null,
        ], fn (?string $value): bool => $value !== null));
        if (array_key_exists('isActive', $data)) {
            $user->is_active = $data['isActive'];
            if (! $data['isActive']) {
                $user->tokens()->delete();
            }
        }
        $user->save();

        return $user;
    }

    public function delete(User $user): void
    {
        abort_if($user->is_owner, 422, 'مينفعش تمسح حساب صاحب العيادة.');
        $user->tokens()->delete();
        $user->delete();
    }

    private function ensureSeat(Clinic $clinic): void
    {
        $maximumUsers = $clinic->plan->max_users;
        abort_if($maximumUsers !== null && $clinic->users()->where('is_active', true)->count() >= $maximumUsers, 422,
            "باقتك بتسمح بـ {$maximumUsers} مستخدمين بس. رقّي الباقة عشان تضيف أكتر.");
    }
}

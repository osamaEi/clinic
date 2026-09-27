<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Hashing\Hasher;
use Illuminate\Validation\ValidationException;

class ClinicAuthentication
{
    public function __construct(private Hasher $hasher) {}

    public function authenticate(string $email, string $password): User
    {
        $user = User::where('email', $email)->first();
        if (! $user || ! $this->hasher->check($password, $user->password)) {
            throw ValidationException::withMessages(['email' => 'البريد أو كلمة السر غلط.']);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'الحساب ده متوقف. كلّم صاحب العيادة.']);
        }
        if (! $user->clinic_id) {
            throw ValidationException::withMessages(['email' => 'حساب الإدارة بيدخل من لوحة /admin.']);
        }

        return $user;
    }
}

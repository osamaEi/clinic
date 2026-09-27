<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Plan;
use App\Models\User;
use App\Support\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /** Self-service signup: creates the clinic (on a trial) and its owner doctor. */
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'clinic_name' => 'required|string|max:255',
            'specialty' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:40',
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'plan' => 'nullable|exists:plans,slug',
        ]);

        $plan = Plan::where('slug', $data['plan'] ?? 'basic')->where('is_active', true)->firstOrFail();

        $user = DB::transaction(function () use ($data, $plan) {
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

        return response()->json($this->tokenResponse($user, $request), 201);
    }

    public function login(Request $request): array
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = User::where('email', $data['email'])->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => 'البريد أو كلمة السر غلط.']);
        }
        if (! $user->is_active) {
            throw ValidationException::withMessages(['email' => 'الحساب ده متوقف. كلّم صاحب العيادة.']);
        }
        if (! $user->clinic_id) {
            throw ValidationException::withMessages(['email' => 'حساب الإدارة بيدخل من لوحة /admin.']);
        }

        return $this->tokenResponse($user, $request);
    }

    public function me(Request $request): array
    {
        return Account::payload($request->user());
    }

    public function logout(Request $request): Response
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    private function tokenResponse(User $user, Request $request): array
    {
        $device = substr((string) $request->userAgent(), 0, 100) ?: 'pwa';

        return ['token' => $user->createToken($device)->plainTextToken] + Account::payload($user);
    }
}

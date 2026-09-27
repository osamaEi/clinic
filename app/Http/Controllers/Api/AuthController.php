<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Account;
use App\Support\ClinicAuthentication;
use App\Support\ClinicRegistration;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AuthController extends Controller
{
    /** Self-service signup: creates the clinic (on a trial) and its owner doctor. */
    public function register(Request $request, ClinicRegistration $registration): JsonResponse
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

        $user = $registration->register($data);

        return response()->json($this->tokenResponse($user, $request), 201);
    }

    public function login(Request $request, ClinicAuthentication $authentication): array
    {
        $data = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        $user = $authentication->authenticate($data['email'], $data['password']);

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

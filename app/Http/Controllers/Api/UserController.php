<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/** Clinic staff accounts — managed by the clinic owner, capped by the plan. */
class UserController extends Controller
{
    public function index(Request $request): Collection
    {
        $this->authorizeOwner($request);

        return $request->user()->clinic->users()->orderBy('id')->get()->map->toClient();
    }

    public function store(Request $request): JsonResponse
    {
        $clinic = $this->authorizeOwner($request);
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role' => ['required', Rule::in(User::ROLES)],
        ]);
        $this->ensureSeat($clinic);

        $user = $clinic->users()->create($data + ['is_owner' => false]);

        return response()->json($user->toClient(), 201);
    }

    public function update(Request $request, int $id): array
    {
        $clinic = $this->authorizeOwner($request);
        $user = $clinic->users()->findOrFail($id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'isActive' => 'sometimes|boolean',
            'password' => 'sometimes|string|min:8',
        ]);
        abort_if($user->is_owner && (($data['isActive'] ?? true) === false || isset($data['role'])), 422, 'مينفعش توقف أو تغيّر دور صاحب العيادة.');
        if (($data['isActive'] ?? false) && ! $user->is_active) {
            $this->ensureSeat($clinic);
        }

        $user->fill(array_filter([
            'name' => $data['name'] ?? null,
            'role' => $data['role'] ?? null,
            'password' => $data['password'] ?? null,
        ], fn ($v) => $v !== null));
        if (array_key_exists('isActive', $data)) {
            $user->is_active = $data['isActive'];
            if (! $data['isActive']) {
                $user->tokens()->delete();
            }
        }
        $user->save();

        return $user->toClient();
    }

    public function destroy(Request $request, int $id): Response
    {
        $clinic = $this->authorizeOwner($request);
        $user = $clinic->users()->findOrFail($id);
        abort_if($user->is_owner, 422, 'مينفعش تمسح حساب صاحب العيادة.');
        $user->tokens()->delete();
        $user->delete();

        return response()->noContent();
    }

    private function authorizeOwner(Request $request): Clinic
    {
        abort_unless($request->user()->is_owner, 403, 'صاحب العيادة بس هو اللي يدير الحسابات.');

        return $request->user()->clinic()->with('plan')->first();
    }

    private function ensureSeat(Clinic $clinic): void
    {
        $max = $clinic->plan->max_users;
        abort_if($max !== null && $clinic->users()->where('is_active', true)->count() >= $max, 422,
            "باقتك بتسمح بـ {$max} مستخدمين بس. رقّي الباقة عشان تضيف أكتر.");
    }
}

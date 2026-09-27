<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\User;
use App\Support\ClinicStaff;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Validation\Rule;

/** Clinic staff accounts — managed by the clinic owner, capped by the plan. */
class UserController extends Controller
{
    public function __construct(private ClinicStaff $staff) {}

    public function index(Request $request): Collection
    {
        $clinic = $this->authorizeOwner($request);

        return $this->staff->members($clinic)->map->toClient();
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
        $user = $this->staff->create($clinic, $data);

        return response()->json($user->toClient(), 201);
    }

    public function update(Request $request, int $id): array
    {
        $clinic = $this->authorizeOwner($request);
        $user = $this->staff->find($clinic, $id);
        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'role' => ['sometimes', Rule::in(User::ROLES)],
            'isActive' => 'sometimes|boolean',
            'password' => 'sometimes|string|min:8',
        ]);

        return $this->staff->update($clinic, $user, $data)->toClient();
    }

    public function destroy(Request $request, int $id): Response
    {
        $clinic = $this->authorizeOwner($request);
        $user = $this->staff->find($clinic, $id);
        $this->staff->delete($user);

        return response()->noContent();
    }

    private function authorizeOwner(Request $request): Clinic
    {
        abort_unless($request->user()->is_owner, 403, 'صاحب العيادة بس هو اللي يدير الحسابات.');

        return $request->user()->clinic()->with('plan')->first();
    }
}

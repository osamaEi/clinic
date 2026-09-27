<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Sync\SyncRegistry;
use App\Sync\SyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SyncController extends Controller
{
    public function __construct(private SyncService $sync) {}

    public function pull(Request $request): array
    {
        $since = max(0, (int) $request->query('since', 0));

        return $this->sync->pull($request->user()->clinic()->first(), $since);
    }

    public function push(Request $request): JsonResponse|array
    {
        $clinic = $request->user()->clinic()->with('plan')->first();
        if (! $clinic->canWrite()) {
            return response()->json([
                'message' => 'الاشتراك منتهي — البيانات متاحة للعرض فقط لحد التجديد.',
                'state' => $clinic->subscriptionState(),
            ], 402);
        }

        $data = $request->validate([
            'changes' => 'required|array|max:500',
            'changes.*.entity' => ['required', Rule::in(SyncRegistry::names())],
            'changes.*.op' => 'required|in:upsert,delete',
            'changes.*.id' => 'required|integer|min:1',
            'changes.*.updatedAt' => 'required|integer|min:0',
            'changes.*.data' => 'nullable|array',
        ]);

        $results = $this->sync->push($clinic, $request->user(), $data['changes']);

        return ['results' => $results];
    }
}

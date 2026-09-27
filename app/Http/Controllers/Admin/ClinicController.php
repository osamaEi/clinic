<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Models\Plan;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ClinicController extends Controller
{
    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q'));
        $clinics = Clinic::with(['plan', 'users' => fn ($u) => $u->where('is_owner', true)])
            ->withCount(['users', 'patients' => fn ($p) => $p->withoutGlobalScopes()->whereNull('deleted_at')])
            ->when($q, fn ($query) => $query->where('name', 'like', "%{$q}%")
                ->orWhereHas('users', fn ($u) => $u->where('email', 'like', "%{$q}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $all = Clinic::all();
        $stats = [
            'total' => $all->count(),
            'trial' => $all->filter(fn ($c) => $c->subscriptionState() === 'trial')->count(),
            'active' => $all->filter(fn ($c) => $c->subscriptionState() === 'active')->count(),
            'expired' => $all->filter(fn ($c) => in_array($c->subscriptionState(), ['expired', 'suspended']))->count(),
            'mrr' => Clinic::with('plan')->get()->filter(fn ($c) => $c->subscriptionState() === 'active')->sum(fn ($c) => $c->plan->price_monthly),
        ];

        return view('admin.clinics', [
            'clinics' => $clinics,
            'plans' => Plan::orderBy('sort')->get(),
            'stats' => $stats,
            'q' => $q,
        ]);
    }

    public function update(Request $request, Clinic $clinic): RedirectResponse
    {
        $data = $request->validate([
            'action' => 'required|in:extend,suspend,resume,plan',
            'months' => 'nullable|integer|min:1|max:36',
            'plan_id' => 'nullable|exists:plans,id',
        ]);

        switch ($data['action']) {
            case 'extend':
                // Paid renewal: stacks on top of any remaining paid time.
                $from = $clinic->subscription_ends_at?->isFuture() ? $clinic->subscription_ends_at : now();
                $clinic->subscription_ends_at = $from->copy()->addMonths($data['months'] ?? 1);
                $clinic->status = 'active';
                $msg = 'الاشتراك اتجدد لحد '.$clinic->subscription_ends_at->format('Y-m-d');
                break;
            case 'suspend':
                $clinic->status = 'suspended';
                $msg = 'العيادة اتوقفت (قراءة فقط).';
                break;
            case 'resume':
                $clinic->status = $clinic->subscription_ends_at?->isFuture() ? 'active' : 'trial';
                $msg = 'العيادة رجعت تشتغل.';
                break;
            case 'plan':
                $clinic->plan_id = $data['plan_id'];
                $msg = 'الباقة اتغيّرت.';
                break;
        }
        $clinic->save();

        return back()->with('status', "{$clinic->name}: {$msg}");
    }
}

<?php

namespace App\Support;

use App\Models\Clinic;
use App\Models\Plan;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\Relation;

class ClinicDirectory
{
    /**
     * @return array{clinics: LengthAwarePaginator, plans: Collection<int, Plan>, stats: array{total: int, trial: int, active: int, expired: int, mrr: int|float}, q: string}
     */
    public function listing(string $search): array
    {
        $clinics = Clinic::with(['plan', 'users' => fn (Builder|Relation $users): Builder|Relation => $users->where('is_owner', true)])
            ->withCount(['users', 'patients' => fn (Builder $patients): Builder => $patients->withoutGlobalScopes()->whereNull('deleted_at')])
            ->when($search, fn (Builder $query): Builder => $query->where('name', 'like', "%{$search}%")
                ->orWhereHas('users', fn (Builder|Relation $users): Builder|Relation => $users->where('email', 'like', "%{$search}%")))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        $allClinics = Clinic::with('plan')->get();
        $stats = [
            'total' => $allClinics->count(),
            'trial' => $allClinics->filter(fn (Clinic $clinic): bool => $clinic->subscriptionState() === 'trial')->count(),
            'active' => $allClinics->filter(fn (Clinic $clinic): bool => $clinic->subscriptionState() === 'active')->count(),
            'expired' => $allClinics->filter(fn (Clinic $clinic): bool => in_array($clinic->subscriptionState(), ['expired', 'suspended']))->count(),
            'mrr' => $allClinics->filter(fn (Clinic $clinic): bool => $clinic->subscriptionState() === 'active')->sum(fn (Clinic $clinic): string => $clinic->plan->price_monthly),
        ];

        return [
            'clinics' => $clinics,
            'plans' => Plan::orderBy('sort')->get(),
            'stats' => $stats,
            'q' => $search,
        ];
    }
}

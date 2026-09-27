<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Clinic;
use App\Support\ClinicDirectory;
use App\Support\Subscriptions\SubscriptionActions;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClinicController extends Controller
{
    public function index(Request $request, ClinicDirectory $directory): View
    {
        return view('admin.clinics', $directory->listing(trim((string) $request->query('q'))));
    }

    public function update(Request $request, Clinic $clinic, SubscriptionActions $actions): RedirectResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in($actions->names())],
            'months' => 'nullable|integer|min:1|max:36',
            'plan_id' => 'required_if:action,plan|nullable|exists:plans,id',
        ]);

        $message = $actions->apply($clinic, $data);

        return back()->with('status', "{$clinic->name}: {$message}");
    }
}

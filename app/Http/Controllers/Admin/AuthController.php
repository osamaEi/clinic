<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function __construct(private StatefulGuard $guard) {}

    public function show(): View
    {
        return view('admin.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if (! $this->guard->attempt($credentials + ['is_super_admin' => true])) {
            return back()->withErrors(['email' => 'بيانات الدخول غلط.'])->onlyInput('email');
        }
        $request->session()->regenerate();

        return redirect()->intended(route('admin.clinics'));
    }

    public function logout(Request $request): RedirectResponse
    {
        $this->guard->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    }
}

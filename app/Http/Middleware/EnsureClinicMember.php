<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClinicMember
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user || ! $user->clinic_id || ! $user->is_active) {
            return response()->json(['message' => 'الحساب ده مالوش صلاحية على بيانات عيادة.'], 403);
        }

        return $next($request);
    }
}

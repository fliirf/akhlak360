<?php

namespace App\Http\Middleware;

use App\Http\Requests\Auth\SsoLoginRequest;
use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmployeeIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $employee = $user?->employee;

        if (! $employee || $employee->employment_status === 'active') {
            return $next($request);
        }

        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'access_revoked',
            'module' => 'authentication',
            'description' => "Protected access rejected for inactive employee {$employee->employee_number}.",
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('sso.login')
            ->withErrors(['identity' => SsoLoginRequest::GENERIC_ERROR]);
    }
}

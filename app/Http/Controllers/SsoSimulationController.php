<?php

namespace App\Http\Controllers;

use App\Http\Requests\Auth\SsoLoginRequest;
use App\Services\SsoAuthenticationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class SsoSimulationController extends Controller
{
    public function __construct(private readonly SsoAuthenticationService $sso) {}

    public function show(): View
    {
        return view('auth.sso-simulation');
    }

    public function store(SsoLoginRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();
        $validated = $request->validated();

        try {
            $user = $this->sso->authenticate(
                trim($validated['identity']),
                $validated['simulation_code'],
                $request
            );
        } catch (RuntimeException) {
            $request->hitRateLimiter();

            throw ValidationException::withMessages([
                'identity' => SsoLoginRequest::GENERIC_ERROR,
            ]);
        }

        $request->clearRateLimiter();
        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect($this->sso->dashboardPathForRole($user->role));
    }
}

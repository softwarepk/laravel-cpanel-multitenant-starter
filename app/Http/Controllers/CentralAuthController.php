<?php

namespace App\Http\Controllers;

use App\Models\CentralAdmin;
use App\Services\CentralAuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CentralAuthController extends Controller
{
    public function create(): View|RedirectResponse
    {
        if (Auth::guard('central')->check()) return redirect()->route('central.home');
        return view('central.auth.login');
    }

    public function store(Request $request, CentralAuditLogger $audit): RedirectResponse
    {
        $request->merge(['email' => strtolower(trim((string) $request->input('email')))]);
        $validated = $request->validate(['email' => ['required', 'email', 'max:255'], 'password' => ['required', 'string']]);
        $key = 'central-login|'.Str::lower($validated['email']).'|'.$request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) throw ValidationException::withMessages(['email' => 'Too many login attempts. Please try again in '.RateLimiter::availableIn($key).' seconds.']);
        if (! Auth::guard('central')->attempt(['email' => $validated['email'], 'password' => $validated['password']])) {
            RateLimiter::hit($key, 60);
            throw ValidationException::withMessages(['email' => 'The provided credentials are incorrect.']);
        }
        RateLimiter::clear($key);
        $request->session()->regenerate();
        $admin = Auth::guard('central')->user();
        if ($admin instanceof CentralAdmin) $audit->log('central_admin.login', 'Central administrator logged in.', admin: $admin, request: $request);
        return redirect()->intended(route('central.home'));
    }

    public function destroy(Request $request, CentralAuditLogger $audit): RedirectResponse
    {
        $admin = Auth::guard('central')->user();
        if ($admin instanceof CentralAdmin) $audit->log('central_admin.logout', 'Central administrator logged out.', admin: $admin, request: $request);
        Auth::guard('central')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('central.login');
    }
}

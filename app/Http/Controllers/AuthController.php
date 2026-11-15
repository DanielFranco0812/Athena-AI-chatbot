<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function showRegister(Request $request)
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.register', [
            'plans' => Plan::orderBy('sort_order')->get(),
            'selectedPlan' => (int) $request->query('plan', Plan::where('is_featured', true)->value('id') ?? 0),
        ]);
    }

    public function register(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:160', 'unique:users'],
            'phone' => ['nullable', 'regex:/^[0-9+\-\s()]{7,20}$/'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'plan_id' => ['nullable', 'exists:plans,id'],
        ], [
            'phone.regex' => 'Enter a mobile number like 0917 123 4567.',
            'email.unique' => 'An account with this email already exists. Log in instead.',
        ]);

        $plan = isset($data['plan_id']) ? Plan::find($data['plan_id']) : null;
        $start = now()->toDateString();

        $user = User::create([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
            'password' => $data['password'],
            'plan_id' => $plan?->id,
            'plan_started' => $plan ? $start : null,
            'plan_expires' => $plan?->endDateFrom($start),
        ]);

        Auth::login($user);
        $request->session()->regenerate();

        return redirect()->route('dashboard')->with('success', $plan
            ? "Your account is ready. Your {$plan->name} plan starts today. Pay {$plan->priceLabel()} at the front desk on your first visit."
            : 'Your account is ready. Choose a plan to start booking classes.');
    }

    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('dashboard');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        $key = 'login:' . strtolower($credentials['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 8)) {
            throw ValidationException::withMessages(['email' => 'Too many attempts. Wait ' . RateLimiter::availableIn($key) . ' seconds and try again.']);
        }

        if (Auth::attempt(['email' => strtolower($credentials['email']), 'password' => $credentials['password']], $request->boolean('remember'))) {
            RateLimiter::clear($key);
            $request->session()->regenerate();
            $user = Auth::user();
            return redirect()->intended($user->isAdmin() ? route('admin.dashboard') : route('dashboard'))
                ->with('success', 'Welcome back, ' . $user->firstName() . '.');
        }

        RateLimiter::hit($key, 300);
        throw ValidationException::withMessages(['email' => 'That email and password don\'t match an account.']);
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('home')->with('success', 'You are logged out.');
    }
}

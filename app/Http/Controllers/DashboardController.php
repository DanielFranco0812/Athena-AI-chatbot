<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DashboardController extends Controller
{
    public function index(Request $request, BookingService $booking)
    {
        $user = $request->user()->load('plan');
        return view('member.dashboard', [
            'user' => $user,
            'upcoming' => $booking->bookingsFor($user, true),
            'past' => $booking->bookingsFor($user, false, 6),
            'plans' => Plan::orderBy('sort_order')->get(),
        ]);
    }

    public function changePlan(Request $request)
    {
        $data = $request->validate(['plan_id' => ['required', 'exists:plans,id']]);
        $plan = Plan::find($data['plan_id']);
        $start = now()->toDateString();
        $request->user()->update(['plan_id' => $plan->id, 'plan_started' => $start, 'plan_expires' => $plan->endDateFrom($start)]);
        return redirect(route('dashboard') . '#plan')->with('success',
            "You are now on the {$plan->name} plan, valid until " . date('F j, Y', strtotime($plan->endDateFrom($start))) . ". Pay {$plan->priceLabel()} at the front desk.");
    }

    public function updateProfile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['nullable', 'regex:/^[0-9+\-\s()]{7,20}$/'],
        ], ['phone.regex' => 'Enter a mobile number like 0917 123 4567.']);
        $request->user()->update($data);
        return redirect(route('dashboard') . '#profile')->with('success', 'Profile saved.');
    }

    public function updatePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:8'],
        ], ['current_password.current_password' => 'Your current password is incorrect.']);
        $request->user()->update(['password' => Hash::make($data['password'])]);
        return redirect(route('dashboard') . '#profile')->with('success', 'Password changed.');
    }
}

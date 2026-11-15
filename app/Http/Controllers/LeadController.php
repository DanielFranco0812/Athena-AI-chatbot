<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\Lead;
use Illuminate\Http\Request;

/**
 * Website inquiry form: every submission is logged as an inquiry and a lead.
 */
class LeadController extends Controller
{
    public function storeInquiry(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:160', 'required_without:phone'],
            'phone' => ['nullable', 'string', 'max:40', 'required_without:email'],
            'interest' => ['nullable', 'string', 'max:120'],
            'message' => ['required', 'string', 'min:5', 'max:2000'],
        ], [
            'email.required_without' => 'Give us an email or a mobile number so we can reply.',
            'phone.required_without' => 'Give us an email or a mobile number so we can reply.',
        ]);

        Inquiry::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? '',
            'message' => $data['message'],
            'source' => 'website',
            'status' => 'new',
        ]);

        Lead::create([
            'name' => $data['name'],
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'interest' => $data['interest'] ?? null,
            'message' => $data['message'],
            'summary' => 'Contact form: ' . mb_substr($data['message'], 0, 160),
            'source' => 'website',
            'status' => 'new',
        ]);

        return redirect()->route('contact')->with('success', 'Thanks, ' . explode(' ', $data['name'])[0] . '. The front desk will get back to you within the day.');
    }
}

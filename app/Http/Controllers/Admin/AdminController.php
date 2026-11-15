<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Conversation;
use App\Models\Faq;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\Request;

/**
 * Front desk area: leads, conversations, class rosters and the
 * business knowledge base (FAQs, prices, store hours) Athena AI answers from.
 */
class AdminController extends Controller
{
    public function dashboard()
    {
        $today = now()->toDateString();
        $week = now()->addDays(6)->toDateString();

        $roster = Booking::with('user', 'schedule.gymClass', 'schedule.coach', 'schedule.coCoach')
            ->where('status', 'booked')->whereBetween('session_date', [$today, $week])
            ->get()->sortBy(fn ($b) => $b->session_date->format('Y-m-d') . $b->schedule->start_time)->values();

        return view('admin.dashboard', [
            'stats' => [
                'New leads' => Lead::where('status', 'new')->count(),
                'Chats this week' => Conversation::where('last_message_at', '>=', now()->subDays(7))->count(),
                'Active members' => User::where('role', 'member')->whereDate('plan_expires', '>=', $today)->count(),
                'Booked today' => Booking::where('status', 'booked')->whereDate('session_date', $today)->count(),
            ],
            'leads' => Lead::latest()->take(6)->get(),
            'roster' => $roster,
        ]);
    }

    /* ---------- leads ---------- */

    public function leads(Request $request)
    {
        $status = $request->query('status');
        $q = Lead::latest();
        if ($status && isset(Lead::STATUSES[$status])) {
            $q->where('status', $status);
        }
        return view('admin.leads', ['leads' => $q->paginate(25)->withQueryString(), 'status' => $status]);
    }

    public function updateLead(Request $request, Lead $lead)
    {
        $data = $request->validate(['status' => ['required', 'in:' . implode(',', array_keys(Lead::STATUSES))]]);
        $lead->update($data);
        return back()->with('success', "{$lead->name} marked as " . strtolower(Lead::STATUSES[$data['status']]) . '.');
    }

    /* ---------- conversations ---------- */

    public function conversations()
    {
        $conversations = Conversation::with('user')->withCount('messages')
            ->whereHas('messages')->orderByDesc('last_message_at')->paginate(25);
        $previews = Message::whereIn('conversation_id', $conversations->pluck('id'))->where('role', 'user')
            ->orderBy('id')->get()->groupBy('conversation_id')->map(fn ($m) => $m->first()->content);
        return view('admin.conversations', compact('conversations', 'previews'));
    }

    public function conversation(Conversation $conversation)
    {
        return view('admin.conversation', [
            'conversation' => $conversation->load('user', 'leads'),
            'messages' => $conversation->messages()->orderBy('id')->get(),
        ]);
    }

    /* ---------- knowledge base ---------- */

    public function knowledge()
    {
        return view('admin.knowledge', [
            'faqs' => Faq::orderBy('category')->orderBy('id')->get(),
            'plans' => Plan::orderBy('sort_order')->get(),
            'settings' => Setting::all_values(),
        ]);
    }

    public function storeFaq(Request $request)
    {
        Faq::create($this->faqData($request));
        return redirect(route('admin.knowledge') . '#faqs')->with('success', 'FAQ added. Athena AI will use it right away.');
    }

    public function updateFaq(Request $request, Faq $faq)
    {
        $faq->update($this->faqData($request));
        return redirect(route('admin.knowledge') . '#faqs')->with('success', 'FAQ saved.');
    }

    public function destroyFaq(Faq $faq)
    {
        $faq->delete();
        return redirect(route('admin.knowledge') . '#faqs')->with('success', 'FAQ deleted.');
    }

    private function faqData(Request $request): array
    {
        $data = $request->validate([
            'question' => ['required', 'string', 'max:255'],
            'answer' => ['required', 'string', 'max:2000'],
            'category' => ['required', 'string', 'max:40'],
        ]);
        $data['is_active'] = $request->boolean('is_active');
        return $data;
    }

    public function updatePlan(Request $request, Plan $plan)
    {
        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'class_limit' => ['nullable', 'integer', 'min:0', 'max:999'],
            'description' => ['required', 'string', 'max:255'],
            'perks' => ['required', 'string', 'max:2000'],
        ]);
        $data['class_limit'] = $request->filled('class_limit') ? (int) $data['class_limit'] : null;
        $plan->update($data);
        return redirect(route('admin.knowledge') . '#prices')->with('success', "{$plan->name} plan saved.");
    }

    public function updateSettings(Request $request)
    {
        $rules = array_fill_keys(array_keys(Setting::FIELDS), ['nullable', 'string', 'max:500']);
        $rules['gym_name'] = ['required', 'string', 'max:120'];
        $rules['facebook_url'] = ['nullable', 'url', 'max:255'];
        Setting::put(array_map(fn ($v) => $v ?? '', $request->validate($rules)));
        return redirect(route('admin.knowledge') . '#hours')->with('success', 'Gym details and hours saved.');
    }

    /* ---------- members ---------- */

    public function members()
    {
        return view('admin.members', [
            'members' => User::with('plan')->where('role', 'member')->latest()->paginate(50),
        ]);
    }
}

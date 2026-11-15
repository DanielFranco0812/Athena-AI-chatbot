<?php

namespace App\Services;

use App\Models\ClassSchedule;
use App\Models\Coach;
use App\Models\Conversation;
use App\Models\Faq;
use App\Models\GymClass;
use App\Models\Lead;
use App\Models\Message;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;

/**
 * Athena AI: the gym's assistant for the website chat widget and Facebook Messenger.
 * Answers from the knowledge base (settings, plans, FAQs), checks live class
 * availability, books and cancels classes for logged-in members, and captures leads.
 */
class AthenaService
{
    private const HISTORY = 6;
    private const MAX_ROUNDS = 5;

    public function __construct(private LlmClient $llm, private BookingService $booking)
    {
    }

    /**
     * @return array{reply: string, booking_changed: bool, lead_saved: bool, offline: bool, error: ?string}
     */
    public function reply(Conversation $conversation, ?User $user, string $text): array
    {
        $history = $conversation->messages()->latest('id')->take(self::HISTORY)->get()->reverse();

        $this->store($conversation, 'user', $text);

        $messages = [['role' => 'system', 'content' => $this->systemPrompt($user, $conversation->channel)]];
        foreach ($history as $m) {
            $messages[] = ['role' => $m->role, 'content' => mb_substr($m->content, 0, 700)];
        }
        $messages[] = ['role' => 'user', 'content' => $text];

        $state = ['booking_changed' => false, 'lead_saved' => false];
        $reply = null;
        $error = null;

        for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
            $res = $this->llm->chat($messages, $this->tools($conversation->channel));
            if (! $res['ok']) {
                $error = $res['error'];
                break;
            }
            $msg = $res['message'];
            $calls = $msg['tool_calls'] ?? [];
            if (! $calls) {
                $reply = trim((string) ($msg['content'] ?? ''));
                break;
            }
            $messages[] = ['role' => 'assistant', 'content' => $msg['content'] ?? null, 'tool_calls' => $calls];
            foreach ($calls as $call) {
                $args = json_decode($call['function']['arguments'] ?? '{}', true) ?: [];
                try {
                    $result = $this->runTool($call['function']['name'] ?? '', $args, $user, $conversation, $state);
                } catch (\Throwable $e) {
                    report($e);
                    $result = ['error' => 'The tool failed. Apologise and suggest calling the front desk.'];
                }
                $messages[] = ['role' => 'tool', 'tool_call_id' => $call['id'], 'content' => json_encode($result, JSON_UNESCAPED_UNICODE)];
            }
        }

        $offline = $reply === null || $reply === '';
        if ($offline) {
            $reply = $this->offlineReply($text);
        }
        $this->store($conversation, 'assistant', $reply);

        return $state + ['reply' => $reply, 'offline' => $offline, 'error' => $error];
    }

    private function store(Conversation $c, string $role, string $content): void
    {
        Message::create(['conversation_id' => $c->id, 'role' => $role, 'content' => $content]);
        $c->update(['last_message_at' => now()]);
    }

    /* ------------------------------------------------------------------ */

    public function systemPrompt(?User $user, string $channel): string
    {
        $s = Setting::all_values() + array_fill_keys(array_keys(Setting::FIELDS), '');
        $gym = $s['gym_name'] ?? 'Infliction Gym';
        $site = rtrim(config('app.url'), '/');
        $L = [];
        $L[] = "You are Athena AI, the friendly AI assistant of $gym in Magalang, Pampanga, Philippines. Tagline: \"Strategic Conversations, Automated.\"";
        $L[] = 'Now: ' . now()->format('l, F j, Y, g:i A') . ' (Philippine time). Today is ' . now()->toDateString() . '.';
        $L[] = 'Channel: ' . ($channel === 'messenger' ? 'Facebook Messenger (plain text only, no markdown)' : 'website chat widget');
        $L[] = '';
        $L[] = 'STYLE: You are a friendly front-desk regular who loves training, not a FAQ bot. Chat like a real person: warm, casual, encouraging, light humor. React to what they say first, use their first name once known, remember details from earlier in the chat, and ask ONE short follow-up question when it helps (goal, experience, schedule, budget). ALWAYS answer what they actually asked first. You know fitness like a good coach: when asked for a workout, exercises, sets/reps, form, warm-ups or basic nutrition, give it directly and concretely (a short routine is fine, no need to ask permission). Mention a class or coach at most once every few replies, only when it clearly matches what they asked, as one short optional line; many replies should have no class pitch at all. Offer booking only when they ask about a class. Facts about classes come ONLY from their descriptions below: never claim a class uses equipment, allows swaps or covers topics the description does not say. If no class matches (for example a dumbbell-specific class), say so plainly and point to the gym floor or personal training instead. Keep replies 2-4 short sentences (a workout list can be longer); use "- " lists only for 3+ items; no tables or headings; an emoji now and then is fine. Reply in their language (English, Tagalog, Taglish, Kapampangan).';
        $L[] = 'RULES: Only state gym facts from this prompt or tools; never invent classes, times, prices, coaches or promos (if unsure, give the phone number). Use TODAY/TOMORROW below or tools for class times; use check_availability for spots left. NEVER book or cancel on your own: call book_class/cancel_booking only when the member explicitly asks or says yes after you asked "Want me to book it?". If they ask to book without a day, pick the next session and say which. Claim success only if the tool returned ok=true. Lead capture: only when someone shows real interest (joining, trial, personal training, wants a call), offer ONCE that the front desk can reach out; if they agree, get name and mobile/email and call save_lead; never repeat the offer. Fitness and nutrition tips are fine; for pain, injury or medical issues suggest a doctor and a coach. Never show internal IDs. Dates like "Wednesday, Nov 11", times like "6:00 PM". To point to a page, write a short markdown link such as [Register](URL) or [Log in](URL); never paste a bare URL.';
        $L[] = '';
        $L[] = 'USER';
        if ($user) {
            $user->loadMissing('plan');
            $plan = $user->plan
                ? "{$user->plan->name} plan, " . ($user->planActive() ? 'active' : 'EXPIRED') . " until {$user->plan_expires->toDateString()}, "
                    . ($user->plan->class_limit === null ? 'unlimited classes' : "{$user->classesUsed()} of {$user->plan->class_limit} classes used")
                : 'no plan yet (must choose one in My account before booking)';
            $L[] = "Logged-in member {$user->name}. $plan.";
        } elseif ($channel === 'messenger') {
            $L[] = "Messenger visitor. Booking is only possible on the website ($site/register). You can still check availability and save them as a lead.";
        } else {
            $L[] = "Visitor not logged in. To book they must log in ($site/login) or register ($site/register).";
        }
        $L[] = '';
        $L[] = 'GYM';
        $L[] = "Address: {$s['address']} ({$s['landmark']}). Phone {$s['phone']}, email {$s['email']}, Facebook {$s['facebook_url']}. Website $site.";
        $L[] = "Hours: Mon-Sat {$s['hours_weekdays']}; Sunday: {$s['hours_sunday']}; holidays: {$s['hours_holidays']}. {$s['freestyle_note']} Group classes run Monday to Saturday only.";
        $L[] = "Amenities: {$s['amenities']}. Parking: {$s['parking']}. Payment: {$s['payment_methods']}.";
        $L[] = "Personal training: {$s['pt_price']}. It is NOT included in any plan or pass. NEVER quote or estimate a personal training price: explain that the coach sets the rate after a consultation where they assess the client, and offer to have the front desk arrange a consultation (lead capture). No joining fee, no lock-in. Classes book 14 days ahead; cancel anytime before start.";
        $L[] = "HOW THE WEBSITE WORKS: Create an account at $site/register (choose a plan; pay at the front desk on the first visit). Log in at $site/login. Book on the Schedule page ($site/schedule) with the Book button, or just ask you. Bookings, plan renewal and profile are in My account ($site/dashboard). Questions can also go through $site/contact.";
        if (! empty($s['promo'])) {
            $L[] = "Current promo: {$s['promo']}";
        }
        if (empty($s['promo']) && ! empty($s['promo_teaser'])) {
            $L[] = "PROMO TEASER: {$s['promo_teaser']} The details are a SURPRISE: if asked about promos or discounts, build excitement and tell them to watch the Facebook page, but never guess or invent prices, discounts, dates or terms.";
        }
        $L[] = 'PLANS: ' . Plan::orderBy('sort_order')->get()->map(fn ($p) => "{$p->name} {$p->priceLabel()}/{$p->period} ("
            . ($p->class_limit === null ? 'unlimited classes' : "{$p->class_limit} class" . ($p->class_limit > 1 ? 'es' : '') . ($p->period === 'month' ? '/month' : '')) . ')')->implode('; ');
        $L[] = 'COACHES: ' . Coach::coaches()->orderBy('id')->get()->map(fn ($c) => "Coach {$c->name}" . ($c->nickname ? " \"{$c->nickname}\"" : '') . ": {$c->specialty}"
            . ($c->specialties ? '. Specialties: ' . collect($c->specialtyList())->pluck(0)->implode(', ') : '')
            . ($c->certifications ? '. Certifications: ' . implode('; ', $c->certificationList()) : ''))->implode(' | ');
        $L[] = 'TEAM: ' . Coach::whereIn('role', ['owner', 'staff'])->orderByRaw("role = 'owner' desc")->orderBy('id')->get()->map(fn ($c) => "{$c->name}" . ($c->nickname ? " \"{$c->nickname}\"" : '') . " ({$c->specialty}): " . implode(' ', array_map(fn ($r) => $r[0], $c->specialtyList())))->implode(' | ') . ' (owner and staff, not coaches).';
        $L[] = 'CLASSES: ' . GymClass::orderBy('id')->get()->map(fn ($c) => "{$c->name} [{$c->category}, " . [1 => 'easy', 2 => 'moderate', 3 => 'hard'][$c->intensity] . "]: {$c->description}")->implode(' | ');
        $days = $this->booking->sessionsBetween(now()->toDateString(), 2);
        foreach ($days as $date => $list) {
            $label = $date === now()->toDateString() ? 'TODAY' : 'TOMORROW';
            $L[] = "$label (" . date('D M j', strtotime($date)) . '): ' . ($list
                ? collect($list)->map(fn ($x) => date('g:i A', strtotime($x['start_time'])) . " {$x['class']} ({$x['coach']})" . ($x['ended'] ? ' [done]' : ''))->implode('; ')
                : 'no group classes, gym floor open');
        }
        $faqs = Faq::where('is_active', true)->orderBy('id')->get();
        if ($faqs->isNotEmpty()) {
            $L[] = 'FAQ:';
            foreach ($faqs as $f) {
                $L[] = "Q: {$f->question} A: {$f->answer}";
            }
        }
        return implode("\n", $L);
    }

    public function tools(string $channel): array
    {
        $fn = fn ($name, $desc, $props = [], $req = []) => ['type' => 'function', 'function' => [
            'name' => $name, 'description' => $desc,
            'parameters' => ['type' => 'object', 'properties' => (object) $props, 'required' => $req],
        ]];
        $date = ['type' => 'string', 'description' => 'YYYY-MM-DD'];

        $tools = [
            $fn('get_class_schedule', 'Weekly timetable, optionally one weekday.',
                ['weekday' => ['type' => 'string', 'description' => 'e.g. Monday']]),
            $fn('check_availability', 'Classes on a date with live spots left.', ['date' => $date], ['date']),
            $fn('find_sessions', 'Next sessions (14 days) by class, coach or category.',
                ['query' => ['type' => 'string']], ['query']),
            $fn('save_lead', 'Save a prospect for front desk follow-up.', [
                'name' => ['type' => 'string'],
                'phone' => ['type' => 'string'],
                'email' => ['type' => 'string'],
                'interest' => ['type' => 'string'],
                'summary' => ['type' => 'string', 'description' => 'one sentence'],
            ], ['name', 'interest', 'summary']),
        ];
        if ($channel === 'web') {
            $tools[] = $fn('book_class', 'Book the member into a session (only after they ask or agree).', [
                'schedule_id' => ['type' => 'integer'],
                'date' => $date,
            ], ['schedule_id', 'date']);
            $tools[] = $fn('cancel_booking', 'Cancel a member booking (only when asked).',
                ['booking_id' => ['type' => 'integer']], ['booking_id']);
            $tools[] = $fn('list_my_bookings', 'Member\'s upcoming bookings.');
        }
        return $tools;
    }

    private function slim(array $s): array
    {
        return [
            'schedule_id' => $s['id'], 'date' => $s['date'], 'weekday' => date('l', strtotime($s['date'])),
            'time' => $s['time_range'], 'class' => $s['class'], 'coach' => $s['coach'], 'room' => $s['room'],
            'spots_left' => $s['spots_left'],
            'status' => $s['started'] ? 'started/finished' : ($s['my_booking_id'] ? 'already booked by this member' : ($s['spots_left'] ? 'open' : 'full')),
        ];
    }

    public function runTool(string $name, array $args, ?User $user, Conversation $conversation, array &$state): array
    {
        $site = rtrim(config('app.url'), '/');
        $needLogin = ['error' => "Not logged in. Ask them to log in at $site/login or register at $site/register, then try again."];

        switch ($name) {
            case 'get_class_schedule':
                $day = ucfirst(strtolower(trim((string) ($args['weekday'] ?? ''))));
                $dow = array_search($day, ClassSchedule::DAYS, true);
                $q = ClassSchedule::with('gymClass', 'coach', 'coCoach')->orderBy('day_of_week')->orderBy('start_time');
                if ($dow) {
                    $q->where('day_of_week', $dow);
                }
                return ['schedule' => $q->get()->map(fn ($s) => [
                    'schedule_id' => $s->id, 'weekday' => $s->dayName(), 'time' => $s->timeRange(),
                    'class' => $s->gymClass->name, 'coach' => $s->coachLabel(), 'room' => $s->room,
                ])->all()];

            case 'check_availability':
                $date = (string) ($args['date'] ?? '');
                if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                    return ['error' => 'Use YYYY-MM-DD.'];
                }
                $list = $this->booking->sessionsBetween($date, 1, $user?->id)[$date] ?? [];
                return ['date' => $date, 'weekday' => date('l', strtotime($date)),
                    'sessions' => array_map([$this, 'slim'], $list),
                    'note' => $list ? null : 'No group classes that day; the gym floor is open.'];

            case 'find_sessions':
                $q = mb_strtolower(trim(preg_replace('/\b(coach|class|classes|session)\b/i', '', (string) ($args['query'] ?? ''))));
                $words = array_filter(explode(' ', $q), fn ($w) => mb_strlen($w) > 3);
                $found = [];
                foreach ($this->booking->sessionsBetween(now()->toDateString(), BookingService::WINDOW_DAYS + 1, $user?->id) as $list) {
                    foreach ($list as $s) {
                        if ($s['started']) {
                            continue;
                        }
                        $hay = mb_strtolower("{$s['class']} {$s['coach']} {$s['coach_nickname']} {$s['category']}");
                        if ($q === '' || str_contains($hay, $q) || array_filter($words, fn ($w) => str_contains($hay, $w))) {
                            $found[] = $this->slim($s);
                            if (count($found) >= 8) {
                                break 2;
                            }
                        }
                    }
                }
                return ['sessions' => $found, 'note' => $found ? null : 'Nothing matched. Check the CLASSES list.'];

            case 'save_lead':
                if (empty($args['phone']) && empty($args['email']) && $conversation->channel !== 'messenger') {
                    return ['error' => 'Ask for a mobile number or email first.'];
                }
                $lead = Lead::updateOrCreate(
                    ['conversation_id' => $conversation->id],
                    [
                        'name' => mb_substr((string) $args['name'], 0, 120),
                        'phone' => isset($args['phone']) ? mb_substr((string) $args['phone'], 0, 40) : null,
                        'email' => isset($args['email']) ? mb_substr((string) $args['email'], 0, 160) : null,
                        'interest' => mb_substr((string) ($args['interest'] ?? ''), 0, 190),
                        'summary' => mb_substr((string) ($args['summary'] ?? ''), 0, 1000),
                        'source' => $conversation->channel === 'messenger' ? 'messenger' : 'chat_widget',
                        'status' => 'new',
                    ]
                );
                $state['lead_saved'] = true;
                return ['ok' => true, 'lead_id' => $lead->id, 'message' => 'Saved. Tell them the front desk will reach out soon.'];

            case 'book_class':
                if (! $user) {
                    return $needLogin;
                }
                $r = $this->booking->book($user, (int) ($args['schedule_id'] ?? 0), (string) ($args['date'] ?? ''));
                $state['booking_changed'] = $state['booking_changed'] || $r['ok'];
                return $r;

            case 'cancel_booking':
                if (! $user) {
                    return $needLogin;
                }
                $r = $this->booking->cancel($user, (int) ($args['booking_id'] ?? 0));
                $state['booking_changed'] = $state['booking_changed'] || $r['ok'];
                return $r;

            case 'list_my_bookings':
                if (! $user) {
                    return $needLogin;
                }
                return ['bookings' => $this->booking->bookingsFor($user)->map(fn ($b) => [
                    'booking_id' => $b->id, 'date' => $b->session_date->format('Y-m-d'), 'weekday' => $b->session_date->format('l'),
                    'time' => date('g:i A', strtotime($b->schedule->start_time)), 'class' => $b->schedule->gymClass->name,
                    'coach' => $b->schedule->coachLabel(), 'room' => $b->schedule->room,
                ])->all()];
        }
        return ['error' => "Unknown tool $name"];
    }

    /** Used only when every AI provider is unreachable: best-matching FAQ plus contact details. */
    public function offlineReply(string $text): string
    {
        $words = array_filter(preg_split('/\W+/u', mb_strtolower($text)), fn ($w) => mb_strlen($w) > 3);
        $best = null;
        $bestScore = 0;
        foreach (Faq::where('is_active', true)->get() as $f) {
            $hay = mb_strtolower($f->question . ' ' . $f->answer);
            $score = count(array_filter($words, fn ($w) => str_contains($hay, $w)));
            if ($score > $bestScore) {
                [$best, $bestScore] = [$f, $score];
            }
        }
        $phone = Setting::get('phone', '');
        $msg = "Athena AI is offline for a moment, so I can't answer that in full right now.";
        if ($best && $bestScore >= 2) {
            $msg .= "\n\nThis might help: {$best->answer}";
        }
        return $msg . "\n\nYou can also call the front desk at $phone.";
    }
}

<?php

namespace App\Http\Controllers;

use App\Models\ClassSchedule;
use App\Models\Coach;
use App\Models\Faq;
use App\Models\Plan;
use App\Services\BookingService;
use Illuminate\Http\Request;

class PageController extends Controller
{
    public function home(Request $request, BookingService $booking)
    {
        $user = $request->user();
        $days = $booking->sessionsBetween(now()->toDateString(), 2, $user?->id);
        [$today, $tomorrow] = array_keys($days);
        $boardDate = collect($days[$today])->contains(fn ($s) => ! $s['ended']) ? $today : $tomorrow;
        $board = $days[$boardDate];
        $nextId = collect($board)->first(fn ($s) => ! $s['started'])['id'] ?? null;

        // next class across today/tomorrow for the live countdown
        $next = null;
        foreach ($days as $date => $list) {
            foreach ($list as $s) {
                if (! $s['started']) {
                    $next = ['class' => $s['class'], 'coach' => $s['coach'], 'room' => $s['room'],
                        'starts' => \Illuminate\Support\Carbon::parse("$date {$s['start_time']}", config('app.timezone'))->toIso8601String()];
                    break 2;
                }
            }
        }

        $dow = (int) now()->format('N');
        $h = (int) now()->format('G');
        $openNow = $dow !== 7 && $h >= 6 && $h < 23; // Mon-Sat 6 AM - 11 PM, closed Sundays

        return view('pages.home', [
            'board' => $board,
            'boardDate' => $boardDate,
            'isToday' => $boardDate === $today,
            'nextId' => $nextId,
            'openNow' => $openNow,
            'coaches' => Coach::coaches()->orderBy('id')->get(),
            'plans' => Plan::orderBy('sort_order')->get(),
            'classCount' => ClassSchedule::count(),
            'next' => $next,
        ]);
    }

    public function schedule(Request $request, BookingService $booking)
    {
        $week = (int) $request->query('week', 1) === 2 ? 2 : 1;
        $from = $week === 1 ? now()->toDateString() : now()->addDays(7)->toDateString();
        return view('pages.schedule', [
            'week' => $week,
            'days' => $booking->sessionsBetween($from, 7, $request->user()?->id),
        ]);
    }

    public function coaches()
    {
        $teaches = [];
        foreach (ClassSchedule::with('gymClass')->orderBy('day_of_week')->orderBy('start_time')->get() as $s) {
            $when = substr($s->dayName(), 0, 3) . ' ' . date('g:i A', strtotime($s->start_time));
            foreach (array_filter([$s->coach_id, $s->co_coach_id]) as $cid) {
                $teaches[$cid][$s->gymClass->name][] = $when;
            }
        }
        return view('pages.coaches', ['coaches' => Coach::orderBy('id')->get(), 'teaches' => $teaches]);
    }

    public function membership()
    {
        return view('pages.membership', ['plans' => Plan::orderBy('sort_order')->get()]);
    }

    public function contact()
    {
        return view('pages.contact', [
            'faqs' => Faq::where('is_active', true)->orderBy('category')->orderBy('id')->get(),
            'plans' => Plan::orderBy('sort_order')->get(),
        ]);
    }
}

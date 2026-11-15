<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\ClassSchedule;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Class schedule and booking rules, shared by the website and Athena AI.
 */
class BookingService
{
    public const WINDOW_DAYS = 13; // today + 13 days = two weeks

    /**
     * Sessions for each date in a range with live spot counts.
     *
     * @return array<string, array<int, array>> date => sessions
     */
    public function sessionsBetween(string $from, int $days, ?int $userId = null): array
    {
        $weekly = ClassSchedule::with(['gymClass', 'coach', 'coCoach'])->orderBy('day_of_week')->orderBy('start_time')->get()->groupBy('day_of_week');
        $to = date('Y-m-d', strtotime("$from +" . ($days - 1) . ' days'));

        $counts = Booking::where('status', 'booked')->whereBetween('session_date', [$from, $to])
            ->selectRaw('class_schedule_id, session_date, COUNT(*) as n')
            ->groupBy('class_schedule_id', 'session_date')->get()
            ->mapWithKeys(fn ($r) => [$r->class_schedule_id . '|' . substr((string) $r->getRawOriginal('session_date'), 0, 10) => (int) $r->n]);

        $mine = $userId
            ? Booking::where('user_id', $userId)->where('status', 'booked')->whereBetween('session_date', [$from, $to])->get()
                ->mapWithKeys(fn ($b) => [$b->class_schedule_id . '|' . $b->session_date->format('Y-m-d') => $b->id])
            : collect();

        $now = time();
        $out = [];
        for ($i = 0; $i < $days; $i++) {
            $date = date('Y-m-d', strtotime("$from +$i days"));
            $dow = (int) date('N', strtotime($date));
            $out[$date] = [];
            foreach ($weekly[$dow] ?? [] as $s) {
                $key = $s->id . '|' . $date;
                $booked = $counts[$key] ?? 0;
                $out[$date][] = [
                    'id' => $s->id,
                    'date' => $date,
                    'class' => $s->gymClass->name,
                    'category' => $s->gymClass->category,
                    'color' => $s->gymClass->color(),
                    'coach' => $s->coachLabel(),
                    'coach_nickname' => trim($s->coach->nickname . ' ' . $s->coCoach?->nickname),
                    'room' => $s->room,
                    'start_time' => $s->start_time,
                    'end_time' => $s->end_time,
                    'time_range' => $s->timeRange(),
                    'capacity' => $s->capacity,
                    'booked' => $booked,
                    'spots_left' => max(0, $s->capacity - $booked),
                    'started' => strtotime("$date {$s->start_time}") <= $now,
                    'ended' => strtotime("$date {$s->end_time}") <= $now,
                    'my_booking_id' => $mine[$key] ?? null,
                ];
            }
        }
        return $out;
    }

    /** @return array{ok: bool, message: string, booking_id: ?int} */
    public function book(User $user, int $scheduleId, string $date): array
    {
        $fail = fn (string $m) => ['ok' => false, 'message' => $m, 'booking_id' => null];

        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || ! strtotime($date)) {
            return $fail('That date is not valid. Use the format YYYY-MM-DD.');
        }
        $slot = ClassSchedule::with('gymClass')->find($scheduleId);
        if (! $slot) {
            return $fail('That class is not on the schedule.');
        }
        $name = $slot->gymClass->name;
        if ((int) date('N', strtotime($date)) !== $slot->day_of_week) {
            return $fail("$name runs on {$slot->dayName()}s, not on " . date('l', strtotime($date)) . '.');
        }
        $today = now()->toDateString();
        $last = now()->addDays(self::WINDOW_DAYS)->toDateString();
        if ($date < $today) {
            return $fail('That date has already passed.');
        }
        if ($date > $last) {
            return $fail('Bookings open two weeks ahead. The latest date you can book right now is ' . date('F j', strtotime($last)) . '.');
        }
        if (strtotime("$date {$slot->start_time}") <= time()) {
            return $fail('That class has already started.');
        }
        $user->loadMissing('plan');
        if (! $user->plan) {
            return $fail('You need a membership plan to book classes. Choose one in My account.');
        }
        if (! $user->planActive($date)) {
            return $fail("Your {$user->plan->name} plan does not cover " . date('F j', strtotime($date))
                . ' (valid until ' . $user->plan_expires->format('F j') . '). Renew it in My account to book.');
        }

        return DB::transaction(function () use ($user, $slot, $date, $name, $fail) {
            // lock the class slot so two people cannot take the last spot at once
            ClassSchedule::whereKey($slot->id)->lockForUpdate()->first();

            $existing = Booking::where(['user_id' => $user->id, 'class_schedule_id' => $slot->id, 'session_date' => $date])->first();
            if ($existing && $existing->status === 'booked') {
                return ['ok' => true, 'message' => "You are already booked for $name on " . date('l, F j', strtotime($date)) . '.', 'booking_id' => $existing->id];
            }
            if ($user->plan->class_limit !== null && $user->classesUsed() >= $user->plan->class_limit) {
                return $fail("You have used all {$user->plan->class_limit} classes in your {$user->plan->name} plan. Upgrade to Premium for unlimited classes.");
            }
            $taken = Booking::where(['class_schedule_id' => $slot->id, 'session_date' => $date, 'status' => 'booked'])->count();
            if ($taken >= $slot->capacity) {
                return $fail("$name on " . date('F j', strtotime($date)) . ' is full.');
            }
            $clash = Booking::where(['user_id' => $user->id, 'session_date' => $date, 'status' => 'booked'])
                ->whereHas('schedule', fn ($q) => $q->where('start_time', '<', $slot->end_time)->where('end_time', '>', $slot->start_time))
                ->with('schedule.gymClass')->first();
            if ($clash) {
                return $fail('That overlaps with ' . $clash->schedule->gymClass->name . ', which you already booked that day.');
            }

            if ($existing) {
                $existing->update(['status' => 'booked']);
                $booking = $existing;
            } else {
                $booking = Booking::create(['user_id' => $user->id, 'class_schedule_id' => $slot->id, 'session_date' => $date]);
            }

            return [
                'ok' => true,
                'message' => "Booked: $name, " . date('l, F j', strtotime($date)) . ' at ' . date('g:i A', strtotime($slot->start_time)) . " in {$slot->room}.",
                'booking_id' => $booking->id,
            ];
        });
    }

    /** @return array{ok: bool, message: string} */
    public function cancel(User $user, int $bookingId): array
    {
        $b = Booking::with('schedule.gymClass')->where('user_id', $user->id)->find($bookingId);
        if (! $b || $b->status !== 'booked') {
            return ['ok' => false, 'message' => 'That booking was not found.'];
        }
        if ($b->startsAt() <= time()) {
            return ['ok' => false, 'message' => 'That class has already started, so it can no longer be cancelled.'];
        }
        $b->update(['status' => 'cancelled']);
        return ['ok' => true, 'message' => 'Cancelled: ' . $b->schedule->gymClass->name . ' on ' . $b->session_date->format('l, F j') . '.'];
    }

    /** Upcoming (not yet finished) or past bookings for a member. */
    public function bookingsFor(User $user, bool $upcoming = true, int $limit = 50)
    {
        $now = time();
        return Booking::with('schedule.gymClass', 'schedule.coach', 'schedule.coCoach')
            ->where('user_id', $user->id)->where('status', 'booked')
            ->whereDate('session_date', $upcoming ? '>=' : '<=', now()->toDateString())
            ->get()
            ->filter(function ($b) use ($upcoming, $now) {
                $end = strtotime($b->session_date->format('Y-m-d') . ' ' . $b->schedule->end_time);
                return $upcoming ? $end > $now : $end <= $now;
            })
            ->sortBy(fn ($b) => $b->session_date->format('Y-m-d') . $b->schedule->start_time, SORT_REGULAR, ! $upcoming)
            ->take($limit)
            ->values();
    }
}

<?php

namespace App\Http\Controllers;

use App\Services\BookingService;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    private function back(Request $request)
    {
        $to = (string) $request->input('back', '');
        return str_starts_with($to, '/') && ! str_starts_with($to, '//') ? redirect($to) : redirect()->route('schedule');
    }

    public function store(Request $request, BookingService $booking)
    {
        $data = $request->validate(['schedule_id' => ['required', 'integer'], 'date' => ['required', 'date_format:Y-m-d']]);
        $r = $booking->book($request->user(), (int) $data['schedule_id'], $data['date']);
        return $this->back($request)->with($r['ok'] ? 'success' : 'error', $r['message']);
    }

    public function destroy(Request $request, int $booking, BookingService $service)
    {
        $r = $service->cancel($request->user(), $booking);
        return $this->back($request)->with($r['ok'] ? 'success' : 'error', $r['message']);
    }
}

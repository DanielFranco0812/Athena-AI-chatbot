<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\AthenaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

/**
 * Website chat widget endpoints for Athena AI.
 */
class ChatController extends Controller
{
    private function conversation(Request $request): Conversation
    {
        $key = $request->session()->get('chat_key');
        if (! $key) {
            $key = Str::random(32);
            $request->session()->put('chat_key', $key);
        }
        $c = Conversation::firstOrCreate(['channel' => 'web', 'external_id' => $key]);
        if ($request->user() && ! $c->user_id) {
            $c->update(['user_id' => $request->user()->id]);
        }
        return $c;
    }

    public function history(Request $request)
    {
        $c = $this->conversation($request);
        return response()->json([
            'csrf' => csrf_token(),
            'messages' => $c->messages()->latest('id')->take(40)->get(['role', 'content'])->reverse()->values(),
        ]);
    }

    public function send(Request $request, AthenaService $athena)
    {
        $data = $request->validate(['message' => ['required', 'string', 'max:1000']]);

        $limiterKey = 'chat:' . $request->session()->getId();
        if (RateLimiter::tooManyAttempts($limiterKey, 15)) {
            return response()->json(['reply' => 'You are sending messages very quickly. Give it a minute and try again.']);
        }
        RateLimiter::hit($limiterKey, 60);

        @set_time_limit(120); // free-tier rate limits may make us wait a few seconds
        $result = $athena->reply($this->conversation($request), $request->user(), trim($data['message']));

        return response()->json([
            'reply' => $result['reply'],
            'booking_changed' => $result['booking_changed'],
            'lead_saved' => $result['lead_saved'],
            'offline' => $result['offline'],
            'debug' => config('app.debug') ? $result['error'] : null,
        ]);
    }

    public function reset(Request $request)
    {
        $request->session()->put('chat_key', Str::random(32));
        return response()->json(['ok' => true]);
    }
}

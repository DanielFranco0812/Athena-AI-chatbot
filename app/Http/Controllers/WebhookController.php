<?php

namespace App\Http\Controllers;

use App\Models\Conversation;
use App\Services\AthenaService;
use App\Services\MessengerService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Facebook Messenger webhook: GET verifies the subscription, POST receives messages
 * and replies through Athena AI.
 */
class WebhookController extends Controller
{
    public function verify(Request $request)
    {
        // PHP turns "hub.mode" into "hub_mode"
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        if ($mode === 'subscribe' && $token && hash_equals((string) config('services.meta.verify_token'), (string) $token)) {
            return response((string) $challenge, 200)->header('Content-Type', 'text/plain');
        }
        return response('Forbidden', 403);
    }

    public function receive(Request $request, AthenaService $athena, MessengerService $messenger)
    {
        if (! $messenger->signatureValid($request)) {
            Log::warning('[Messenger] Invalid signature');
            return response('Invalid signature', 403);
        }
        if ($request->input('object') !== 'page') {
            return response()->json(['status' => 'ignored']);
        }

        @set_time_limit(120);
        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['messaging'] ?? []) as $event) {
                $psid = $event['sender']['id'] ?? null;
                $text = $event['message']['text'] ?? ($event['postback']['title'] ?? null);
                if (! $psid || ! $text || ! empty($event['message']['is_echo'])) {
                    continue;
                }
                $conversation = Conversation::firstOrCreate(['channel' => 'messenger', 'external_id' => $psid]);
                $messenger->typing($psid);
                $result = $athena->reply($conversation, null, mb_substr($text, 0, 1000));
                $messenger->send($psid, $result['reply']);
            }
        }

        // Meta expects a 200 quickly, or it retries
        return response()->json(['status' => 'received']);
    }
}

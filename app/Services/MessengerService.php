<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Facebook Messenger Send API + webhook signature check.
 */
class MessengerService
{
    public function isConfigured(): bool
    {
        return (bool) config('services.meta.page_access_token');
    }

    /** Verify the X-Hub-Signature-256 header when an app secret is configured. */
    public function signatureValid(Request $request): bool
    {
        $secret = config('services.meta.app_secret');
        if (! $secret) {
            return true; // not configured (local testing)
        }
        $header = (string) $request->header('X-Hub-Signature-256', '');
        $expected = 'sha256=' . hash_hmac('sha256', $request->getContent(), $secret);
        return hash_equals($expected, $header);
    }

    public function send(string $psid, string $text): bool
    {
        if (! $this->isConfigured()) {
            Log::info('[Messenger] Page token missing; reply not sent.', ['psid' => $psid, 'text' => $text]);
            return false;
        }
        $url = 'https://graph.facebook.com/' . config('services.meta.graph_version') . '/me/messages';
        $ok = true;
        // Messenger allows 2000 characters per message
        foreach ($this->chunks($this->plain($text), 1900) as $part) {
            $response = Http::withOptions(['verify' => is_file(resource_path('certs/cacert.pem')) ? resource_path('certs/cacert.pem') : true])
                ->timeout(20)
                ->post($url . '?access_token=' . urlencode(config('services.meta.page_access_token')), [
                    'recipient' => ['id' => $psid],
                    'messaging_type' => 'RESPONSE',
                    'message' => ['text' => $part],
                ]);
            if ($response->failed()) {
                Log::warning('[Messenger] Send failed', ['status' => $response->status(), 'body' => $response->json()]);
                $ok = false;
            }
        }
        return $ok;
    }

    public function typing(string $psid): void
    {
        if (! $this->isConfigured()) {
            return;
        }
        try {
            Http::withOptions(['verify' => is_file(resource_path('certs/cacert.pem')) ? resource_path('certs/cacert.pem') : true])
                ->timeout(5)
                ->post('https://graph.facebook.com/' . config('services.meta.graph_version') . '/me/messages?access_token=' . urlencode(config('services.meta.page_access_token')), [
                    'recipient' => ['id' => $psid],
                    'sender_action' => 'typing_on',
                ]);
        } catch (\Throwable) {
            // typing indicator is optional
        }
    }

    /** Strip the light markdown Athena uses on the website. */
    public function plain(string $text): string
    {
        $text = preg_replace('/\*\*(.+?)\*\*/s', '$1', $text);
        $text = preg_replace('/\[([^\]]+)\]\((https?:[^)\s]+)\)/', '$1: $2', $text); // Messenger shows plain text
        $text = preg_replace('/^\s*[-*]\s+/m', '• ', $text);
        return trim($text);
    }

    private function chunks(string $text, int $size): array
    {
        if (mb_strlen($text) <= $size) {
            return [$text];
        }
        $out = [];
        while (mb_strlen($text) > $size) {
            $cut = mb_strrpos(mb_substr($text, 0, $size), "\n") ?: $size;
            $out[] = trim(mb_substr($text, 0, $cut));
            $text = mb_substr($text, $cut);
        }
        $out[] = trim($text);
        return array_filter($out);
    }
}

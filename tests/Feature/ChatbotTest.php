<?php

namespace Tests\Feature;

use App\Models\Conversation;
use App\Models\Lead;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Facebook Messenger webhook for Athena AI.
 */
class ChatbotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.groq.key' => 'test-key',
            'services.groq.models' => ['openai/gpt-oss-120b'],
            'services.meta.verify_token' => 'verify-me',
            'services.meta.page_access_token' => 'page-token',
            'services.meta.app_secret' => null,
        ]);
        $this->seed();
    }

    private function payload(string $text, string $psid = '998877'): array
    {
        return ['object' => 'page', 'entry' => [['id' => 'PAGE', 'messaging' => [
            ['sender' => ['id' => $psid], 'recipient' => ['id' => 'PAGE'], 'message' => ['mid' => 'm1', 'text' => $text]],
        ]]]];
    }

    public function test_webhook_verification(): void
    {
        $this->get('/api/webhook/facebook?hub.mode=subscribe&hub.verify_token=verify-me&hub.challenge=42')->assertOk()->assertSee('42');
        $this->get('/api/webhook/facebook?hub.mode=subscribe&hub.verify_token=wrong&hub.challenge=42')->assertForbidden();
    }

    public function test_messenger_message_gets_an_athena_reply(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::response($this->groqText('Hi! **Premium** is ₱2,000 a month.')),
            'graph.facebook.com/*' => Http::response(['message_id' => 'x']),
        ]);

        $this->postJson('/api/webhook/facebook', $this->payload('How much is Premium?'))->assertOk();

        $this->assertDatabaseHas('conversations', ['channel' => 'messenger', 'external_id' => '998877']);
        $this->assertSame(2, Conversation::first()->messages()->count());
        Http::assertSent(fn ($r) => str_contains($r->url(), 'graph.facebook.com') && isset($r['message'])
            && $r['recipient']['id'] === '998877' && $r['message']['text'] === 'Hi! Premium is ₱2,000 a month.');
    }

    public function test_messenger_lead_capture(): void
    {
        Http::fake([
            'api.groq.com/*' => Http::sequence()
                ->push($this->groqTool('save_lead', ['name' => 'Ana', 'phone' => '0918 111 2222', 'interest' => 'Boxing', 'summary' => 'Wants boxing classes']))
                ->push($this->groqText('Thanks Ana!')),
            'graph.facebook.com/*' => Http::response(['message_id' => 'x']),
        ]);
        $this->postJson('/api/webhook/facebook', $this->payload('Ana here, 0918 111 2222, interested in boxing'))->assertOk();
        $this->assertSame('messenger', Lead::first()->source);
    }

    public function test_invalid_signature_is_rejected(): void
    {
        config(['services.meta.app_secret' => 'secret']);
        $this->postJson('/api/webhook/facebook', $this->payload('hi'), ['X-Hub-Signature-256' => 'sha256=bad'])->assertForbidden();
    }

    public function test_echo_messages_are_ignored(): void
    {
        Http::fake();
        $p = $this->payload('echo');
        $p['entry'][0]['messaging'][0]['message']['is_echo'] = true;
        $this->postJson('/api/webhook/facebook', $p)->assertOk();
        Http::assertNothingSent();
    }
}

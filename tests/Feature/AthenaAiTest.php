<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ClassSchedule;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AthenaAiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-16 05:00:00', 'Asia/Manila'));
        config(['services.groq.key' => 'test-key', 'services.groq.models' => ['openai/gpt-oss-120b', 'openai/gpt-oss-20b'], 'services.huggingface.token' => null]);
        $this->seed();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_athena_answers_and_saves_history(): void
    {
        Http::fake(['api.groq.com/*' => Http::response($this->groqText('We open at 5:00 AM, Monday to Saturday.'))]);

        $this->postJson('/chat', ['message' => 'What time do you open?'])
            ->assertOk()->assertJson(['reply' => 'We open at 5:00 AM, Monday to Saturday.', 'offline' => false]);

        $this->getJson('/chat')->assertJsonCount(2, 'messages');
        Http::assertSent(function ($request) {
            $system = $request['messages'][0]['content'];
            return str_contains($system, 'Athena AI') && str_contains($system, 'Magalang') && $request['model'] === 'openai/gpt-oss-120b';
        });
    }

    public function test_athena_books_a_class_for_a_logged_in_member(): void
    {
        $plan = Plan::where('name', 'Premium')->first();
        $user = User::factory()->create(['plan_id' => $plan->id, 'plan_started' => '2026-11-16', 'plan_expires' => '2026-12-15']);
        $slot = ClassSchedule::where('day_of_week', 1)->where('start_time', 'like', '18:00%')->first(); // Functional Circuit

        Http::fakeSequence('api.groq.com/*')
            ->push($this->groqTool('book_class', ['schedule_id' => $slot->id, 'date' => '2026-11-16']))
            ->push($this->groqText('Done! You are booked for Functional Circuit today at 6:00 PM.'));

        $this->actingAs($user)->postJson('/chat', ['message' => 'Book me into the circuit class tonight'])
            ->assertOk()->assertJson(['booking_changed' => true]);

        $this->assertDatabaseHas('bookings', ['user_id' => $user->id, 'class_schedule_id' => $slot->id, 'status' => 'booked']);
    }

    public function test_guest_cannot_book_through_athena(): void
    {
        Http::fakeSequence('api.groq.com/*')
            ->push($this->groqTool('book_class', ['schedule_id' => 1, 'date' => '2026-11-16']))
            ->push($this->groqText('Please log in first.'));

        $this->postJson('/chat', ['message' => 'Book me in'])->assertJson(['booking_changed' => false]);
        $this->assertSame(0, Booking::count());
    }

    public function test_athena_captures_a_lead(): void
    {
        Http::fakeSequence('api.groq.com/*')
            ->push($this->groqTool('save_lead', ['name' => 'Maria Santos', 'phone' => '0917 222 3333', 'interest' => 'Premium plan', 'summary' => 'Wants to join Premium next week.']))
            ->push($this->groqText('Thanks Maria! The front desk will call you.'));

        $this->postJson('/chat', ['message' => 'I am Maria, 0917 222 3333, I want Premium'])
            ->assertJson(['lead_saved' => true]);
        $this->assertDatabaseHas('leads', ['name' => 'Maria Santos', 'source' => 'chat_widget', 'interest' => 'Premium plan', 'status' => 'new']);
    }

    public function test_lead_without_contact_details_is_not_saved(): void
    {
        Http::fakeSequence('api.groq.com/*')
            ->push($this->groqTool('save_lead', ['name' => 'Maria', 'interest' => 'Premium', 'summary' => 'x']))
            ->push($this->groqText('Could I get your mobile number?'));

        $this->postJson('/chat', ['message' => 'I want Premium'])->assertJson(['lead_saved' => false]);
        $this->assertSame(0, Lead::count());
    }

    public function test_falls_back_to_second_model_when_first_is_rate_limited(): void
    {
        Http::fakeSequence('api.groq.com/*')
            ->push(['error' => ['message' => 'Rate limit reached']], 429)
            ->push($this->groqText('Hello from the backup model.'));

        $this->postJson('/chat', ['message' => 'Hi'])->assertJson(['reply' => 'Hello from the backup model.']);
        Http::assertSentCount(2);
    }

    public function test_waits_and_retries_when_groq_says_try_again(): void
    {
        Http::fakeSequence('api.groq.com/*')
            ->push(['error' => ['message' => 'Rate limit reached. Please try again in 0.1s.']], 429)
            ->push($this->groqText('Back after a short wait.'));

        $this->postJson('/chat', ['message' => 'Hi'])->assertJson(['reply' => 'Back after a short wait.']);
        Http::assertSent(fn ($r) => $r['model'] === 'openai/gpt-oss-120b');
        Http::assertNotSent(fn ($r) => $r['model'] === 'openai/gpt-oss-20b');
    }

    public function test_prompt_includes_todays_classes(): void
    {
        $prompt = app(\App\Services\AthenaService::class)->systemPrompt(null, 'web');
        $this->assertStringContainsString('TODAY (Mon Nov 16)', $prompt);
        $this->assertStringContainsString('6:00 PM Functional Circuit (Coach Mark & Coach Alvin)', $prompt);
        $this->assertStringContainsString('The Machine', $prompt);
        $this->assertStringContainsString('Facility Guardian', $prompt);
        $this->assertStringContainsString('SURPRISE', $prompt);
        $this->assertStringContainsString('NEVER quote or estimate a personal training price', $prompt);
        $this->assertStringNotContainsString('free personal training', strtolower($prompt));
    }

    public function test_offline_reply_when_ai_is_down(): void
    {
        Http::fake(['api.groq.com/*' => Http::response(['error' => ['message' => 'down']], 503)]);

        $this->postJson('/chat', ['message' => 'Do you have showers and lockers?'])
            ->assertJson(['offline' => true])
            ->assertJsonPath('reply', fn ($r) => str_contains($r, 'offline') && str_contains($r, 'lockers'));
    }

    public function test_chat_requires_a_message(): void
    {
        $this->postJson('/chat', ['message' => ''])->assertStatus(422);
    }
}

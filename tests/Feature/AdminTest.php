<?php

namespace Tests\Feature;

use App\Models\Faq;
use App\Models\Lead;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
        $this->admin = User::where('email', 'admin@infliction.ph')->first();
    }

    public function test_members_cannot_open_the_front_desk(): void
    {
        $this->get('/admin/knowledge')->assertRedirect('/login');
        $this->actingAs(User::factory()->create())->get('/admin')->assertRedirect('/dashboard');
    }

    public function test_admin_pages_load(): void
    {
        foreach (['/admin', '/admin/leads', '/admin/conversations', '/admin/knowledge', '/admin/members'] as $url) {
            $this->actingAs($this->admin)->get($url)->assertOk();
        }
    }

    public function test_admin_manages_faqs(): void
    {
        $this->actingAs($this->admin)->post('/admin/knowledge/faqs', ['question' => 'Do you sell protein?', 'answer' => 'Yes, at the front desk.', 'category' => 'shop', 'is_active' => 1]);
        $faq = Faq::where('question', 'Do you sell protein?')->firstOrFail();
        $this->actingAs($this->admin)->put("/admin/knowledge/faqs/{$faq->id}", ['question' => 'Do you sell protein?', 'answer' => 'Yes.', 'category' => 'shop']);
        $this->assertFalse($faq->fresh()->is_active);
        $this->actingAs($this->admin)->delete("/admin/knowledge/faqs/{$faq->id}");
        $this->assertModelMissing($faq);
    }

    public function test_admin_updates_prices_and_hours_used_by_athena(): void
    {
        $plan = Plan::where('name', 'Premium')->first();
        $this->actingAs($this->admin)->put("/admin/knowledge/plans/{$plan->id}", [
            'price' => 2200, 'class_limit' => '', 'description' => 'All in.', 'perks' => "Unlimited classes\nTowel",
        ])->assertSessionHas('success');
        $this->assertEquals(2200, $plan->fresh()->price);
        $this->assertNull($plan->fresh()->class_limit);

        $settings = Setting::all_values();
        $settings['hours_sunday'] = '8:00 AM – 6:00 PM';
        $this->actingAs($this->admin)->put('/admin/knowledge/settings', $settings)->assertSessionHas('success');
        $this->assertSame('8:00 AM – 6:00 PM', Setting::get('hours_sunday'));

        $prompt = app(\App\Services\AthenaService::class)->systemPrompt(null, 'web');
        $this->assertStringContainsString('₱2,200', $prompt);
        $this->assertStringContainsString('8:00 AM – 6:00 PM', $prompt);
    }

    public function test_contact_form_creates_a_lead_and_admin_updates_status(): void
    {
        $this->post('/contact', ['name' => 'Lito Garcia', 'phone' => '0919 000 1111', 'interest' => 'Personal training', 'message' => 'Can I try a PT session?'])
            ->assertSessionHas('success');
        $lead = Lead::where('name', 'Lito Garcia')->firstOrFail();
        $this->assertSame('website', $lead->source);

        $this->actingAs($this->admin)->patch("/admin/leads/{$lead->id}", ['status' => 'contacted']);
        $this->assertSame('contacted', $lead->fresh()->status);
    }

    public function test_contact_form_needs_a_way_to_reply(): void
    {
        $this->post('/contact', ['name' => 'No Contact', 'message' => 'Hello there'])->assertSessionHasErrors(['email', 'phone']);
    }
}

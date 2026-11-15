<?php

namespace Tests\Feature;

use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    public function test_user_can_register_with_a_plan(): void
    {
        $basic = Plan::where('name', 'Basic')->first();
        $this->post('/register', [
            'name' => 'Juan Dela Cruz',
            'email' => 'juan@example.com',
            'phone' => '0917 123 4567',
            'password' => 'secret123',
            'password_confirmation' => 'secret123',
            'plan_id' => $basic->id,
        ])->assertRedirect('/dashboard');

        $user = User::where('email', 'juan@example.com')->first();
        $this->assertAuthenticatedAs($user);
        $this->assertSame($basic->id, $user->plan_id);
        $this->assertTrue($user->planActive());
    }

    public function test_registration_rejects_duplicate_email_and_short_password(): void
    {
        $this->post('/register', ['name' => 'X', 'email' => 'admin@infliction.ph', 'password' => '123', 'password_confirmation' => '123'])
            ->assertSessionHasErrors(['email', 'password', 'name']);
    }

    public function test_user_can_login_and_logout(): void
    {
        $user = User::factory()->create(['email' => 'login@example.com', 'password' => 'secret123']);
        $this->post('/login', ['email' => 'login@example.com', 'password' => 'secret123'])->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_wrong_password_is_rejected(): void
    {
        $this->post('/login', ['email' => 'admin@infliction.ph', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_admin_login_goes_to_front_desk(): void
    {
        $this->post('/login', ['email' => 'admin@infliction.ph', 'password' => 'Admin@123'])->assertRedirect('/admin');
    }
}

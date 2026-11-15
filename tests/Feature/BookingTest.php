<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\ClassSchedule;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BookingTest extends TestCase
{
    use RefreshDatabase;

    private User $member;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-16 05:00:00', 'Asia/Manila')); // a Monday morning
        $this->seed();
        $this->member = $this->memberOn('Basic');
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function memberOn(string $planName): User
    {
        $plan = Plan::where('name', $planName)->first();
        return User::factory()->create([
            'plan_id' => $plan->id, 'plan_started' => '2026-11-16', 'plan_expires' => $plan->endDateFrom('2026-11-16'),
        ]);
    }

    private function slot(int $day, string $start): ClassSchedule
    {
        return ClassSchedule::where('day_of_week', $day)->where('start_time', 'like', "$start%")->firstOrFail();
    }

    public function test_guest_is_sent_to_login(): void
    {
        $this->post('/bookings', ['schedule_id' => 1, 'date' => '2026-11-16'])->assertRedirect('/login');
    }

    public function test_member_can_book_and_cancel(): void
    {
        $slot = $this->slot(1, '08:00');
        $this->actingAs($this->member)->post('/bookings', ['schedule_id' => $slot->id, 'date' => '2026-11-16'])
            ->assertSessionHas('success');
        $booking = Booking::firstOrFail();
        $this->assertSame('booked', $booking->status);

        $this->actingAs($this->member)->delete("/bookings/{$booking->id}")->assertSessionHas('success');
        $this->assertSame('cancelled', $booking->fresh()->status);
    }

    public function test_wrong_weekday_is_rejected(): void
    {
        $slot = $this->slot(1, '08:00'); // Monday class
        $this->actingAs($this->member)->post('/bookings', ['schedule_id' => $slot->id, 'date' => '2026-11-17'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'runs on Mondays'));
        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_basic_plan_class_limit_is_enforced(): void
    {
        $dates = ['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19'];
        foreach ($dates as $d) {
            $slot = ClassSchedule::where('day_of_week', (int) date('N', strtotime($d)))->orderBy('start_time')->first();
            $this->actingAs($this->member)->post('/bookings', ['schedule_id' => $slot->id, 'date' => $d])->assertSessionHas('success');
        }
        $slot = $this->slot(5, '18:00');
        $this->actingAs($this->member)->post('/bookings', ['schedule_id' => $slot->id, 'date' => '2026-11-20'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'used all 4 classes'));
    }

    public function test_full_class_cannot_be_booked(): void
    {
        $slot = $this->slot(1, '18:00');
        $slot->update(['capacity' => 1]);
        $other = $this->memberOn('Premium');
        Booking::create(['user_id' => $other->id, 'class_schedule_id' => $slot->id, 'session_date' => '2026-11-16']);

        $this->actingAs($this->member)->post('/bookings', ['schedule_id' => $slot->id, 'date' => '2026-11-16'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'is full'));
    }

    public function test_member_without_plan_cannot_book(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/bookings', ['schedule_id' => $this->slot(1, '08:00')->id, 'date' => '2026-11-16'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'need a membership plan'));
    }

    public function test_sunday_has_no_classes(): void
    {
        $this->assertSame(0, ClassSchedule::where('day_of_week', 7)->count());
        $this->get('/schedule')->assertSee('Closed on Sundays');
    }

    public function test_booking_more_than_two_weeks_ahead_is_rejected(): void
    {
        $this->actingAs($this->member)->post('/bookings', ['schedule_id' => $this->slot(1, '08:00')->id, 'date' => '2026-12-07'])
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'two weeks ahead'));
    }
}

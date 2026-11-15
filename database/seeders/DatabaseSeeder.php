<?php

namespace Database\Seeders;

use App\Models\Faq;
use App\Models\Plan;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Setting::put([
            'gym_name' => 'Infliction Fitness Center',
            'address' => '128 Rizal Street, Barangay San Nicolas 1st, Magalang, Pampanga 2011',
            'landmark' => 'Two blocks from the Magalang town plaza, on the road toward PSAU',
            'phone' => '0917 555 0142',
            'email' => 'hello@infliction.ph',
            'facebook_url' => 'https://www.facebook.com/inflictionfitnesscenter',
            'hours_weekdays' => '5:00 AM – 11:00 PM',
            'hours_sunday' => '7:00 AM – 7:00 PM',
            'hours_holidays' => '8:00 AM – 5:00 PM',
            'pt_price' => 'Not included in any membership pass. Rates depend on the coach and are set after a consultation, since each coach first assesses you and what you need',
            'payment_methods' => 'Cash, GCash and Maya at the front desk',
            'amenities' => 'Air-conditioned gym floor with free weights (dumbbells 2–50 kg, barbells, kettlebells, benches), squat racks, cable machines, treadmills, bikes and rowers; boxing ring; spin room; yoga studio; lockers; showers; drinking water station',
            'parking' => 'Free parking for 10 cars plus motorcycle parking beside the building',
            'promo' => '',
        ]);

        $plans = [
            ['Day Pass', 150, 'day', 1, 'One full day on the gym floor, plus one class.', "Full gym floor access for the day\nOne group class that day\nLocker use", false],
            ['Basic', 1200, 'month', 4, 'Gym floor every day, four classes a month.', "Unlimited gym floor access\n4 group classes per month\nLocker use\nFree fitness assessment on your first visit", false],
            ['Premium', 2000, 'month', null, 'Everything, including unlimited classes.', "Unlimited gym floor access\nUnlimited group classes\nTowel service and locker", true],
            ['Student', 900, 'month', 8, 'For students with a valid school ID (PSAU and others).', "Unlimited gym floor access\n8 group classes per month\nLocker use\nShow your school ID at the front desk", false],
        ];
        foreach ($plans as $i => [$name, $price, $period, $limit, $desc, $perks, $featured]) {
            Plan::create(['name' => $name, 'price' => $price, 'period' => $period, 'class_limit' => $limit,
                'description' => $desc, 'perks' => $perks, 'is_featured' => $featured, 'sort_order' => $i + 1]);
        }

        $faqs = [
            ['membership', 'Is there a joining fee or lock-in contract?', 'No joining fee and no lock-in contract. You pay per day or per month and can stop any time.'],
            ['membership', 'Can I freeze my membership?', 'Yes. Monthly members can freeze once per year for up to 30 days for travel or illness. Ask at the front desk.'],
            ['membership', 'Do you have a student discount?', 'Yes, the Student plan is ₱900 a month with 8 classes. Bring a valid school ID on your first visit.'],
            ['visit', 'Can I walk in without an account?', 'Yes. Buy a Day Pass at the front desk. Creating an account online lets you book classes ahead.'],
            ['visit', 'Can I bring a guest?', 'Premium members can bring one guest per month for free. Other guests buy a Day Pass.'],
            ['visit', 'What is the minimum age?', 'Members must be 16 or older. Ages 13 to 15 can train with a parent present and a signed waiver.'],
            ['visit', 'What should I bring to my first class?', 'Water, a small towel, and training shoes.'],
            ['facilities', 'Do you have showers and lockers?', 'Yes, there are separate shower rooms and day lockers. Bring your own padlock.'],
            ['classes', 'What is your class cancellation policy?', 'You can cancel any time before the class starts and the class goes back to your allowance.'],
        ];
        foreach ($faqs as [$cat, $q, $a]) {
            Faq::create(['category' => $cat, 'question' => $q, 'answer' => $a, 'is_active' => true]);
        }

        // Real coaches, classes, timetable and hours
        $this->call(GymDataSeeder::class);

        User::updateOrCreate(['email' => 'admin@infliction.ph'], [
            'name' => 'Front Desk Admin',
            'phone' => '0917 555 0142',
            'password' => 'Admin@123',
            'role' => 'admin',
        ]);
    }
}

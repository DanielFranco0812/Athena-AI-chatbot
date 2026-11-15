<?php

namespace Database\Seeders;

use App\Models\ClassSchedule;
use App\Models\Coach;
use App\Models\Faq;
use App\Models\GymClass;
use App\Models\Setting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Real Infliction Fitness Center data: hours, coaches and the group exercise schedule.
 * Safe to run again: it replaces coaches, classes and the timetable
 * (existing class bookings are removed with the old timetable).
 */
class GymDataSeeder extends Seeder
{
    public function run(): void
    {
        Setting::put([
            'gym_name' => 'Infliction Fitness Center',
            'hours_weekdays' => '6:00 AM – 11:00 PM',
            'hours_sunday' => 'Closed',
            'hours_holidays' => 'Ask the front desk',
            'pt_price' => 'Not included in any membership pass. Rates depend on the coach and are set after a consultation, since each coach first assesses you and what you need',
            'freestyle_note' => 'Freestyle (open gym) training is available all day, even while group exercises are ongoing.',
            'facebook_url' => 'https://www.facebook.com/inflictionfitnesscenter',
            'promo' => '',
            'promo_teaser' => 'A Christmas surprise is coming! 🎄 Watch our Facebook page for the promo reveal.',
        ]);

        DB::transaction(function () {
            ClassSchedule::query()->delete();   // bookings cascade
            GymClass::query()->delete();
            Coach::query()->delete();

            $coach = [];
            foreach ($this->coaches() as $key => $c) {
                $coach[$key] = Coach::create($c);
            }

            $class = [];
            foreach ($this->classes() as $key => $c) {
                $class[$key] = GymClass::create($c);
            }

            // Group exercises, Monday (1) to Saturday (6). Gym closed on Sundays.
            // Times are start times from the official schedule; each class is assumed to run 1 hour.
            $schedule = [
                [1, '08:00', 'booty', 'elvie', null],
                [1, '18:00', 'circuit', 'mark', 'alvin'],
                [2, '18:00', 'crossfit', 'mark', 'alvin'],
                [3, '18:00', 'bootcamp', 'mark', 'alvin'],
                [4, '18:00', 'legs', 'mark', 'alvin'],
                [5, '18:00', 'bootcamp', 'mark', 'alvin'],
                [6, '18:00', 'circuit', 'bakbak', 'joseph'],
            ];
            foreach ($schedule as [$day, $start, $cls, $lead, $co]) {
                ClassSchedule::create([
                    'gym_class_id' => $class[$cls]->id,
                    'coach_id' => $coach[$lead]->id,
                    'co_coach_id' => $co ? $coach[$co]->id : null,
                    'day_of_week' => $day,
                    'start_time' => $start,
                    'end_time' => date('H:i', strtotime("$start +1 hour")),
                    'room' => 'Main Floor',
                    'capacity' => 20,
                ]);
            }
        });

        Faq::updateOrCreate(['question' => 'Are classes good for beginners?'], [
            'category' => 'classes',
            'answer' => 'Yes. Every group class can be scaled, and the coaches give easier options. Booty Workout and Legs & Glutes are a good start if you are new.',
            'is_active' => true,
        ]);
        Faq::updateOrCreate(['question' => 'Are you open on Sundays?'], [
            'category' => 'visit', 'answer' => 'No, Infliction Fitness Center is closed on Sundays. We are open Monday to Saturday, 6:00 AM to 11:00 PM.', 'is_active' => true,
        ]);
        Faq::updateOrCreate(['question' => 'Can I work out on my own while a group class is going on?'], [
            'category' => 'visit', 'answer' => 'Yes. Freestyle training is available all day, even while group exercises are ongoing.', 'is_active' => true,
        ]);
        Faq::updateOrCreate(['question' => 'How much is personal training?'], [
            'category' => 'membership', 'answer' => 'Personal training is not included in any membership pass. Each coach sets their own rate after a consultation, because they first need to assess you, your goals and what you need. Ask the front desk or any coach to book a consultation.', 'is_active' => true,
        ]);

        // Personal training is not part of any pass
        \App\Models\Plan::query()->get()->each(function ($plan) {
            $perks = collect(preg_split('/\r?\n/', (string) $plan->perks))->reject(fn ($l) => stripos($l, 'personal training') !== false)->implode("\n");
            if ($perks !== $plan->perks) {
                $plan->update(['perks' => $perks]);
            }
        });

        Cache::forget('gym_settings');
    }

    private function coaches(): array
    {
        return [
            'joseph' => [
                'name' => 'Joseph', 'nickname' => 'The Machine', 'color' => 'red', 'photo' => 'images/coaches/joseph.jpg',
                'specialty' => 'Endurance, strength & functional training', 'years_experience' => 10,
                'bio' => 'As "The Machine," Coach Joseph is a powerhouse when it comes to endurance and strength. With a wealth of certifications and programs under his belt, he is ready to push you to your limits and help you transform into a well-rounded athlete. Coach Joseph will turn your body into a machine, maximizing endurance, strength and agility.',
                'certifications' => "Bar + Bell Workshop – Olympic Weightlifting (2016)\nAnatomy for Fitness Professionals – FitMe (2017)\nBodybuilding Seminar – Zafira Fitness Gym (2017)",
                'specialties' => "Functional Training & Endurance: Build long-lasting strength.\nWeight Loss/Fat Loss & Bodybuilding: Sculpt and redefine your physique.\nHIIT & Circuit Training: Keep your body agile with high-intensity routines.\nFlexibility/Mobility & Kettlebell Training: Improve movement and strength.",
                'pt_hours' => 'Upon consultation',
            ],
            'bakbak' => [
                'name' => 'Bakbak', 'nickname' => 'The MMA Maestro', 'color' => 'blue', 'photo' => 'images/coaches/bakbak.jpg',
                'specialty' => 'MMA, boxing, Muay Thai & conditioning', 'years_experience' => 12,
                'bio' => 'As the "MMA Maestro," Coach Bakbak delivers a masterclass in combat sports and fitness. With years of experience in martial arts and strength training, he guides you through powerful workouts that build both skill and endurance. His dynamic mix of martial arts and functional fitness will challenge and elevate your performance.',
                'certifications' => "Fitness and Martial Arts Coach – Freestyle MMA (2014–2018)\nCertified Fitness Instructor – Fitness Innovations Manila (2018)\nFirst Aid & CPR Certified – Philippine Red Cross (2024)",
                'specialties' => "MMA, Boxing, Muay Thai, Jiu Jitsu: Master multiple combat sports.\nStrength, Conditioning, Bodybuilding: Sculpt and build.\nFunctional Training & Flexibility: Boost agility and mobility.",
                'pt_hours' => 'Upon consultation',
            ],
            'elvie' => [
                'name' => 'Elvie', 'nickname' => 'The Empower-HER', 'color' => 'yellow', 'photo' => 'images/coaches/elvie.jpg',
                'specialty' => "Women's fitness, fat loss & glute sculpting", 'years_experience' => 5,
                'bio' => 'As "The Empower-HER," Coach Elvie is dedicated to helping women transform their bodies and boost their confidence. With over 278k followers and growing, she is a fitness influencer and coach who knows how to get results. Her holistic approach and positive energy will empower you to embrace your strength and feel unstoppable.',
                'certifications' => "Fitness Instructor – New Skills Academy (2021)\nIntroduction to Sports Nutrition – New Skills Academy (2021)",
                'specialties' => "Weight/Fat Loss & Body Toning: Achieve a leaner, sculpted physique.\nPostpartum Fitness & Glute Sculpting: Strengthen and rebuild after pregnancy.\nPersonalized Programs & Group Classes: Tailored to your specific needs and goals.",
                'pt_hours' => 'Upon consultation',
            ],
            'mark' => [
                'name' => 'Mark', 'nickname' => 'Circuit Commander', 'color' => 'green', 'photo' => 'images/coaches/mark.jpg',
                'specialty' => 'Circuit, bootcamp & boxing training', 'years_experience' => 6,
                'bio' => 'As the "Circuit Commander," Coach Mark is your go-to for high-energy, full-body workouts that push your limits. His expertise in boxing, circuit and bootcamp training means you will be sweating and strengthening all at once, combining strength, mobility and endurance for a total fitness transformation.',
                'certifications' => "Fitness Instructor Course – Gold's Gym Fitness Institute (2021)\nKettlebell Training – Gold's Gym Fitness Institute (2020–2021)",
                'specialties' => "Weight Loss & Conditioning: Shed fat and boost endurance.\nStrengthening & Toning: Muscle-building that sculpts and defines.\nMetafit & Core Training: Improve functional fitness and mobility.\nBoxing & Circuit Training: High-intensity programs that keep you agile.",
                'pt_hours' => 'Upon consultation',
            ],
            'noel' => [
                'name' => 'Noel', 'nickname' => 'Mr. Fixer Upper', 'color' => 'blue', 'photo' => 'images/coaches/noel.jpg',
                'specialty' => 'Rehabilitation, mobility & strength', 'years_experience' => 6,
                'bio' => 'As "Mr. Fixer Upper," Coach Noel combines his background in Occupational Therapy with fitness expertise to help you move better, feel stronger and perform at your best. Specializing in rehabilitation and functional training, he is the go-to coach for rebuilding and improving mobility while reaching your fitness goals.',
                'certifications' => "BS Occupational Therapy – University of Perpetual Help (2020)\nPersonal Training – International Fitness Professional Association (2024)",
                'specialties' => "Fat Loss & Weight Loss: Targeted programs for effective results.\nStrength & Conditioning: Build muscle and endurance.\nRehabilitation & Functional Training: Move pain-free and stay strong.",
                'pt_hours' => 'Upon consultation',
            ],
            'ronald' => [
                'name' => 'Ronald A.', 'role' => 'owner', 'nickname' => 'The Captain', 'color' => 'red', 'photo' => 'images/coaches/ronald.jpg',
                'specialty' => 'Owner', 'years_experience' => 0,
                'bio' => 'As "The Captain" of Infliction Fitness, Kuya Ronald leads the gym with steady hands and sharp vision. From overseeing day-to-day operations to developing long-term strategies, he ensures everything runs smoothly. His leadership is the driving force behind the gym\'s growth, creating an environment where both staff and members can thrive. Kuya Ronald keeps the ship sailing smoothly, guiding Infliction Fitness to success every day!',
                'certifications' => null,
                'specialties' => "Managing overall gym operations and staff.\nCreating and implementing business strategies.\nEnsuring top-quality service and a smooth experience for all members.",
                'pt_hours' => '',
            ],
            'kris' => [
                'name' => 'Ate Kris', 'role' => 'staff', 'nickname' => 'Welcome Warrior', 'color' => 'yellow', 'photo' => 'images/coaches/kris.jpg',
                'specialty' => 'Front desk', 'years_experience' => 0,
                'bio' => 'As "Welcome Warrior," Ate Kris is the first face you will see when you step into Infliction Fitness. She manages the front desk with a warm smile and professionalism, ensuring every member and guest feels valued. From handling inquiries to managing check-ins, Ate Kris keeps the gym running smoothly and efficiently, and makes sure your gym experience starts on the right foot!',
                'certifications' => null,
                'specialties' => "Greeting members and visitors.\nProviding information on gym services and programs.\nManaging member check-ins and handling transactions.\nAddressing concerns and ensuring a positive experience.",
                'pt_hours' => '',
            ],
            'erica' => [
                'name' => 'Ate Erica', 'role' => 'staff', 'nickname' => 'Admin Dynamo', 'color' => 'blue', 'photo' => 'images/coaches/erica.jpg',
                'specialty' => 'Administration', 'years_experience' => 0,
                'bio' => 'As "Admin Dynamo," Ate Erica brings energy and precision to Infliction Fitness\' operations. From managing member records to keeping schedules in check, she ensures everything runs smoothly behind the scenes. Her multitasking skills and attention to detail support the entire team and make sure the gym is always at its best.',
                'certifications' => null,
                'specialties' => "Overseeing administrative tasks and managing member records.\nOrganizing schedules and supporting gym operations.\nEnsuring efficient day-to-day processes.",
                'pt_hours' => '',
            ],
            'ryan' => [
                'name' => 'Kuya Ryan', 'role' => 'staff', 'nickname' => 'Facility Guardian', 'color' => 'green', 'photo' => 'images/coaches/ryan.jpg',
                'specialty' => 'Facilities, maintenance & member sign-ups', 'years_experience' => 0,
                'bio' => 'As "Facility Guardian," Kuya Ryan keeps Infliction Fitness clean, organized and ready for action every day. His attention to detail and dedication make the gym a comfortable and welcoming space for all members. With Kuya Ryan on duty, you can focus on your fitness in a pristine environment!',
                'certifications' => null,
                'specialties' => "Keeping the entire gym spotless, from floors to equipment.\nHandling minor repairs and maintenance.\nAssisting with new member sign-ups and monitoring memberships.\nEnsuring health and safety standards are always met.",
                'pt_hours' => '',
            ],
            'alvin' => [
                'name' => 'Alvin', 'nickname' => null, 'color' => 'green', 'photo' => null,
                'specialty' => 'Group exercise coach', 'years_experience' => 0,
                'bio' => 'Coach Alvin co-leads the evening group exercises with Coach Mark: Functional Circuit, CrossFit, Bootcamp and Legs & Glutes.',
                'certifications' => null, 'specialties' => null,
                'pt_hours' => 'Upon consultation',
            ],
        ];
    }

    private function classes(): array
    {
        return [
            'booty' => ['name' => 'Booty Workout', 'category' => 'strength', 'intensity' => 2,
                'description' => 'Lower-body session focused on building and toning the glutes and hips.'],
            'circuit' => ['name' => 'Functional Circuit', 'category' => 'cardio', 'intensity' => 3,
                'description' => 'Full-body circuit of functional exercise stations for strength, endurance and agility.'],
            'crossfit' => ['name' => 'CrossFit', 'category' => 'strength', 'intensity' => 3,
                'description' => 'High-intensity functional movements combining strength work and conditioning.'],
            'bootcamp' => ['name' => 'Bootcamp', 'category' => 'cardio', 'intensity' => 3,
                'description' => 'Group conditioning workout mixing cardio drills, bodyweight and strength moves.'],
            'legs' => ['name' => 'Legs & Glutes', 'category' => 'strength', 'intensity' => 2,
                'description' => 'Lower-body strength day for the legs and glutes.'],
        ];
    }
}

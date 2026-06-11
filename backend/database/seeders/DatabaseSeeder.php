<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Level;
use App\Models\StudentRequest;
use App\Models\Subject;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Support\Facades\Hash;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::updateOrCreate(
            ['email' => 'coordinator@tutormatch.test'],
            [
                'name' => 'Demo Coordinator',
                'password' => Hash::make('password'),
                'role' => 'coordinator',
            ]
        );
        User::updateOrCreate(
            ['email' => 'admin@tutormatch.test'],
            [
                'name' => 'Demo Admin',
                'password' => Hash::make('password'),
                'role' => 'admin',
            ]
        );
        $demoTutorUser = User::updateOrCreate(
            ['email' => 'tutor@tutormatch.test'],
            [
                'name' => 'Demo Tutor',
                'password' => Hash::make('password'),
                'role' => 'tutor',
            ]
        );

        $subjects = collect(['Mathematics', 'English', 'Chemistry', 'Physics', 'Biology', 'GP', 'Chinese'])
            ->mapWithKeys(fn ($name) => [$name => Subject::firstOrCreate(['name' => $name])]);

        $levels = collect(['Primary 5', 'Primary 6', 'Sec 2', 'Sec 4 O-Level', 'JC 1', 'IB Year 5'])
            ->mapWithKeys(fn ($name) => [$name => Level::firstOrCreate(['name' => $name])]);

        $tutors = [
            [
                'name' => 'Daniel Lim',
                'tutor_type' => 'ex_moe',
                'teaching_mode' => 'hybrid',
                'location' => 'Bishan',
                'hourly_rate_min' => 55,
                'hourly_rate_max' => 70,
                'years_experience' => 9,
                'rating' => 4.8,
                'acceptance_rate' => 0.86,
                'success_score' => 0.90,
                'bio' => 'Fictional ex-MOE tutor focused on O-Level sciences.',
                'subjects' => [['Chemistry', 'Sec 4 O-Level', 5], ['Physics', 'Sec 4 O-Level', 4]],
                'slots' => [['saturday', 'morning'], ['weekday', 'evening']],
            ],
            [
                'name' => 'Aisha Rahman',
                'tutor_type' => 'full_time',
                'teaching_mode' => 'online',
                'location' => 'Tampines',
                'hourly_rate_min' => 40,
                'hourly_rate_max' => 55,
                'years_experience' => 6,
                'rating' => 4.7,
                'acceptance_rate' => 0.78,
                'success_score' => 0.82,
                'bio' => 'Fictional full-time tutor for English and GP.',
                'subjects' => [['English', 'Sec 2', 5], ['GP', 'JC 1', 4]],
                'slots' => [['sunday', 'afternoon'], ['weekday', 'evening']],
            ],
            [
                'name' => 'Marcus Tan',
                'tutor_type' => 'part_time',
                'teaching_mode' => 'home',
                'location' => 'Jurong East',
                'hourly_rate_min' => 30,
                'hourly_rate_max' => 45,
                'years_experience' => 3,
                'rating' => 4.4,
                'acceptance_rate' => 0.70,
                'success_score' => 0.72,
                'bio' => 'Fictional undergraduate tutor strong in lower secondary math.',
                'subjects' => [['Mathematics', 'Sec 2', 4], ['Mathematics', 'Primary 6', 4]],
                'slots' => [['saturday', 'morning'], ['sunday', 'morning']],
            ],
            [
                'name' => 'Grace Wong',
                'tutor_type' => 'current_moe',
                'teaching_mode' => 'hybrid',
                'location' => 'Serangoon',
                'hourly_rate_min' => 70,
                'hourly_rate_max' => 90,
                'years_experience' => 12,
                'rating' => 4.9,
                'acceptance_rate' => 0.62,
                'success_score' => 0.94,
                'bio' => 'Fictional current MOE teacher for upper primary Chinese.',
                'subjects' => [['Chinese', 'Primary 5', 5], ['Chinese', 'Primary 6', 5]],
                'slots' => [['saturday', 'morning'], ['weekday', 'evening']],
            ],
        ];

        foreach ($tutors as $payload) {
            $subjectsPayload = $payload['subjects'];
            $slots = $payload['slots'];
            unset($payload['subjects'], $payload['slots']);

            if ($payload['name'] === 'Daniel Lim') {
                $payload['user_id'] = $demoTutorUser->id;
            }

            $tutor = Tutor::updateOrCreate(['name' => $payload['name']], $payload);

            foreach ($subjectsPayload as [$subject, $level, $proficiency]) {
                $tutor->tutorSubjects()->updateOrCreate(
                    [
                        'subject_id' => $subjects[$subject]->id,
                        'level_id' => $levels[$level]->id,
                    ],
                    ['proficiency' => $proficiency]
                );
            }

            foreach ($slots as [$day, $block]) {
                $tutor->availabilities()->firstOrCreate(['day_of_week' => $day, 'time_block' => $block]);
            }
        }

        $request = StudentRequest::updateOrCreate(
            [
                'student_name' => 'Demo Student A',
                'subject_id' => $subjects['Chemistry']->id,
                'level_id' => $levels['Sec 4 O-Level']->id,
            ],
            [
                'parent_name' => 'Mrs Tan',
                'location' => 'Bishan',
                'teaching_mode' => 'home',
                'budget_min' => 45,
                'budget_max' => 65,
                'preferred_tutor_type' => 'ex_moe',
                'requested_day_of_week' => 'saturday',
                'requested_time_block' => 'morning',
                'urgency' => 'urgent',
                'status' => 'new',
                'schedule_notes' => 'Weekend mornings preferred',
                'notes' => 'Needs help with O-Level Chemistry exam prep.',
            ]
        );

        Assignment::updateOrCreate(
            ['student_request_id' => $request->id],
            [
                'title' => 'Sec 4 O-Level Chemistry in Bishan',
                'status' => 'open',
                'published_at' => now(),
            ]
        );
    }
}

<?php

namespace Tests\Unit;

use App\Models\Level;
use App\Models\StudentRequest;
use App\Models\Subject;
use App\Models\Tutor;
use App\Services\Matching\TutorMatchingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TutorMatchingServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_scores_a_strong_tutor_match_transparently(): void
    {
        $subject = Subject::create(['name' => 'Chemistry']);
        $level = Level::create(['name' => 'Sec 4 O-Level']);
        $request = StudentRequest::create([
            'student_name' => 'Demo Student',
            'parent_name' => 'Mrs Tan',
            'subject_id' => $subject->id,
            'level_id' => $level->id,
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
        ]);
        $tutor = Tutor::create([
            'name' => 'Daniel Lim',
            'tutor_type' => 'ex_moe',
            'teaching_mode' => 'hybrid',
            'location' => 'Bishan',
            'hourly_rate_min' => 55,
            'hourly_rate_max' => 70,
            'acceptance_rate' => 0.8,
            'success_score' => 0.8,
        ]);
        $tutor->tutorSubjects()->create(['subject_id' => $subject->id, 'level_id' => $level->id, 'proficiency' => 5]);
        $tutor->availabilities()->create(['day_of_week' => 'saturday', 'time_block' => 'morning']);

        $score = app(TutorMatchingService::class)->score($tutor, $request);

        $this->assertSame(99, $score->totalScore);
        $this->assertSame(30, $score->factors['subject']);
        $this->assertSame(20, $score->factors['level']);
        $this->assertSame(15, $score->factors['location_mode']);
        $this->assertStringContainsString('Daniel Lim scores 99/100', $score->explanation);
    }
}

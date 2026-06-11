<?php

namespace Tests\Unit;

use App\Models\Level;
use App\Models\MatchResult;
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

    public function test_generation_prefilters_incompatible_tutors_before_scoring(): void
    {
        [$request, $subject, $level] = $this->requestFixture();
        $goodTutor = $this->tutorFixture('Good Tutor', $subject, $level);
        $this->tutorFixture('Inactive Tutor', $subject, $level, ['is_active' => false]);
        $this->tutorFixture('Too Expensive Tutor', $subject, $level, [
            'hourly_rate_min' => 90,
            'hourly_rate_max' => 120,
        ]);

        $otherSubject = Subject::create(['name' => 'Physics']);
        $this->tutorFixture('Wrong Subject Tutor', $otherSubject, $level);

        $matches = app(TutorMatchingService::class)->generateForRequest($request);

        $this->assertCount(1, $matches);
        $this->assertSame($goodTutor->id, $matches->first()->tutor_id);
        $this->assertDatabaseCount('match_results', 1);
    }

    public function test_generation_prefilters_by_requested_availability(): void
    {
        [$request, $subject, $level] = $this->requestFixture();
        $availableTutor = $this->tutorFixture('Available Tutor', $subject, $level);
        $unavailableTutor = $this->tutorFixture('Unavailable Tutor', $subject, $level);
        $unavailableTutor->availabilities()->delete();
        $unavailableTutor->availabilities()->create(['day_of_week' => 'sunday', 'time_block' => 'afternoon']);

        $matches = app(TutorMatchingService::class)->generateForRequest($request);

        $this->assertSame([$availableTutor->id], $matches->pluck('tutor_id')->all());
    }

    public function test_online_requests_prefilter_online_and_hybrid_tutors(): void
    {
        [$request, $subject, $level] = $this->requestFixture();
        $request->update([
            'teaching_mode' => 'online',
            'location' => 'Remote',
        ]);
        $onlineTutor = $this->tutorFixture('Online Tutor', $subject, $level, [
            'teaching_mode' => 'online',
            'location' => 'Jurong East',
        ]);
        $hybridTutor = $this->tutorFixture('Hybrid Tutor', $subject, $level, [
            'teaching_mode' => 'hybrid',
            'location' => 'Tampines',
        ]);
        $this->tutorFixture('Home Tutor', $subject, $level, [
            'teaching_mode' => 'home',
            'location' => 'Remote',
        ]);

        $matches = app(TutorMatchingService::class)->generateForRequest($request);

        $this->assertEqualsCanonicalizing([$onlineTutor->id, $hybridTutor->id], $matches->pluck('tutor_id')->all());
    }

    public function test_generation_removes_stale_matches_when_tutor_no_longer_prefilters(): void
    {
        [$request, $subject, $level] = $this->requestFixture();
        $goodTutor = $this->tutorFixture('Good Tutor', $subject, $level);
        $staleTutor = $this->tutorFixture('Stale Tutor', $subject, $level);
        MatchResult::create([
            'student_request_id' => $request->id,
            'tutor_id' => $staleTutor->id,
            'total_score' => 99,
            'score_breakdown' => ['subject' => 30],
            'deterministic_explanation' => 'Old match',
            'generated_at' => now(),
        ]);
        $staleTutor->update(['is_active' => false]);

        $matches = app(TutorMatchingService::class)->generateForRequest($request);

        $this->assertSame([$goodTutor->id], $matches->pluck('tutor_id')->all());
        $this->assertDatabaseMissing('match_results', [
            'student_request_id' => $request->id,
            'tutor_id' => $staleTutor->id,
        ]);
    }

    public function test_ties_are_ordered_by_success_acceptance_then_tutor_id(): void
    {
        [$request, $subject, $level] = $this->requestFixture();
        $lowerHistory = $this->tutorFixture('Lower History', $subject, $level, [
            'acceptance_rate' => 0.85,
            'success_score' => 0.75,
        ]);
        $higherHistory = $this->tutorFixture('Higher History', $subject, $level, [
            'acceptance_rate' => 0.75,
            'success_score' => 0.85,
        ]);

        $matches = app(TutorMatchingService::class)->generateForRequest($request);

        $this->assertSame([$higherHistory->id, $lowerHistory->id], $matches->pluck('tutor_id')->all());
    }

    private function requestFixture(): array
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

        return [$request, $subject, $level];
    }

    private function tutorFixture(string $name, Subject $subject, Level $level, array $overrides = []): Tutor
    {
        $tutor = Tutor::create(array_merge([
            'name' => $name,
            'tutor_type' => 'ex_moe',
            'teaching_mode' => 'hybrid',
            'location' => 'Bishan',
            'hourly_rate_min' => 55,
            'hourly_rate_max' => 65,
            'acceptance_rate' => 0.8,
            'success_score' => 0.8,
            'is_active' => true,
        ], $overrides));
        $tutor->tutorSubjects()->create(['subject_id' => $subject->id, 'level_id' => $level->id, 'proficiency' => 5]);
        $tutor->availabilities()->create(['day_of_week' => 'saturday', 'time_block' => 'morning']);

        return $tutor;
    }
}

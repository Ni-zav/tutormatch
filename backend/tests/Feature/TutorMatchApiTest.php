<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Level;
use App\Models\StudentRequest;
use App\Models\Subject;
use App\Models\Tutor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TutorMatchApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok(): void
    {
        $this->getJson('/api/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_request_can_be_created_and_match_can_be_generated(): void
    {
        $subject = Subject::create(['name' => 'Chemistry']);
        $level = Level::create(['name' => 'Sec 4 O-Level']);
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

        $response = $this->postJson('/api/requests', [
            'student_name' => 'Demo Student A',
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
            'schedule_notes' => 'Weekend mornings preferred',
        ])->assertCreated();

        $requestId = $response->json('data.id');
        $this->assertDatabaseHas('assignments', ['student_request_id' => $requestId]);

        $this->postJson("/api/requests/{$requestId}/generate-matches")
            ->assertOk()
            ->assertJsonPath('data.0.total_score', 99)
            ->assertJsonPath('data.0.score_breakdown.subject', 30);
    }

    public function test_message_draft_uses_mock_ai_fallback(): void
    {
        $subject = Subject::create(['name' => 'Chemistry']);
        $level = Level::create(['name' => 'Sec 4 O-Level']);
        $studentRequest = StudentRequest::create([
            'student_name' => 'Demo Student A',
            'parent_name' => 'Mrs Tan',
            'subject_id' => $subject->id,
            'level_id' => $level->id,
            'location' => 'Bishan',
            'teaching_mode' => 'home',
            'budget_max' => 65,
            'urgency' => 'urgent',
            'schedule_notes' => 'Weekend mornings preferred',
        ]);
        Assignment::create([
            'student_request_id' => $studentRequest->id,
            'title' => 'Sec 4 O-Level Chemistry in Bishan',
            'status' => 'open',
        ]);

        $this->postJson('/api/message-drafts', [
            'student_request_id' => $studentRequest->id,
            'audience' => 'client',
            'channel' => 'whatsapp',
        ])
            ->assertCreated()
            ->assertJsonPath('data.generated_by', 'mock_ai');
    }
}

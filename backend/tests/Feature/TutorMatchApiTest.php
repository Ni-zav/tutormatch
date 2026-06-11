<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\Level;
use App\Models\StudentRequest;
use App\Models\Subject;
use App\Models\Tutor;
use App\Models\User;
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

    public function test_protected_routes_require_authentication(): void
    {
        $this->getJson('/api/dashboard/summary')
            ->assertUnauthorized();
    }

    public function test_coordinator_can_login_and_read_current_user(): void
    {
        User::create([
            'name' => 'Coordinator',
            'email' => 'coordinator@example.test',
            'password' => 'password',
            'role' => 'coordinator',
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'coordinator@example.test',
            'password' => 'password',
        ])
            ->assertOk()
            ->assertJsonPath('data.user.role', 'coordinator');

        $this->withToken($login->json('data.token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'coordinator@example.test');
    }

    public function test_tutor_role_cannot_access_coordinator_dashboard(): void
    {
        $token = $this->tokenFor(User::create([
            'name' => 'Tutor',
            'email' => 'tutor@example.test',
            'password' => 'password',
            'role' => 'tutor',
        ]));

        $this->withToken($token)
            ->getJson('/api/dashboard/summary')
            ->assertForbidden();
    }

    public function test_request_can_be_created_and_match_can_be_generated(): void
    {
        $token = $this->tokenForCoordinator();
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

        $response = $this->withToken($token)->postJson('/api/requests', [
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

        $this->withToken($token)->postJson("/api/requests/{$requestId}/generate-matches")
            ->assertOk()
            ->assertJsonPath('data.0.total_score', 99)
            ->assertJsonPath('data.0.score_breakdown.subject', 30);
    }

    public function test_message_draft_uses_mock_ai_fallback(): void
    {
        $token = $this->tokenForCoordinator();
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

        $this->withToken($token)->postJson('/api/message-drafts', [
            'student_request_id' => $studentRequest->id,
            'audience' => 'client',
            'channel' => 'whatsapp',
        ])
            ->assertCreated()
            ->assertJsonPath('data.generated_by', 'mock_ai');
    }

    private function tokenForCoordinator(): string
    {
        return $this->tokenFor(User::create([
            'name' => 'Coordinator',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => 'coordinator',
        ]));
    }

    private function tokenFor(User $user): string
    {
        $token = hash('sha256', $user->email.microtime(true));
        $user->forceFill([
            'api_token_hash' => hash('sha256', $token),
            'api_token_issued_at' => now(),
        ])->save();

        return $token;
    }
}

<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Level;
use App\Models\MatchResult;
use App\Models\StudentRequest;
use App\Models\Subject;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

    public function test_expired_api_tokens_are_rejected_and_revoked(): void
    {
        config(['auth.api_token_ttl_minutes' => 60]);
        $plainToken = 'expired-token';
        $user = User::create([
            'name' => 'Coordinator',
            'email' => 'expired-token@example.test',
            'password' => 'password',
            'role' => 'coordinator',
        ]);
        $user->forceFill([
            'api_token_hash' => hash('sha256', $plainToken),
            'api_token_issued_at' => now()->subMinutes(61),
        ])->save();

        $this->withToken($plainToken)
            ->getJson('/api/auth/me')
            ->assertUnauthorized()
            ->assertJsonPath('message', 'Token expired.');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'api_token_hash' => null,
            'api_token_issued_at' => null,
        ]);
    }

    public function test_coordinator_can_read_reference_data_for_request_form(): void
    {
        $token = $this->tokenForCoordinator();
        $subject = Subject::create(['name' => 'Chemistry']);
        $level = Level::create(['name' => 'Sec 4 O-Level']);

        $this->withToken($token)
            ->getJson('/api/subjects')
            ->assertOk()
            ->assertJsonPath('data.0.id', $subject->id)
            ->assertJsonPath('data.0.name', 'Chemistry');

        $this->withToken($token)
            ->getJson('/api/levels')
            ->assertOk()
            ->assertJsonPath('data.0.id', $level->id)
            ->assertJsonPath('data.0.name', 'Sec 4 O-Level');
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

        $this->assertDatabaseHas('audit_logs', [
            'action' => 'request.created',
            'auditable_type' => StudentRequest::class,
            'auditable_id' => $requestId,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'matches.generated',
            'auditable_type' => StudentRequest::class,
            'auditable_id' => $requestId,
        ]);
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
            ->assertJsonPath('data.generated_by', 'mock_ai')
            ->assertJsonPath('data.prompt_version', 'message-draft-v1')
            ->assertJsonPath('data.fallback_used', false)
            ->assertJsonPath('data.generation_metadata.provider', 'mock');
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'message_draft.created',
            'auditable_type' => \App\Models\MessageDraft::class,
        ]);
    }

    public function test_openai_message_draft_falls_back_to_mock_with_metadata(): void
    {
        config([
            'services.ai.provider' => 'openai',
            'services.ai.openai_api_key' => 'test-key',
            'services.ai.openai_model' => 'test-model',
            'services.ai.timeout_seconds' => 1,
        ]);
        Http::fake([
            'api.openai.com/*' => Http::response(['error' => ['message' => 'unavailable']], 500),
        ]);
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

        $this->withToken($token)->postJson('/api/message-drafts', [
            'student_request_id' => $studentRequest->id,
            'audience' => 'client',
            'channel' => 'whatsapp',
        ])
            ->assertCreated()
            ->assertJsonPath('data.generated_by', 'mock_ai')
            ->assertJsonPath('data.fallback_used', true)
            ->assertJsonPath('data.generation_metadata.provider', 'openai')
            ->assertJsonPath('data.generation_metadata.fallback_provider', 'mock');

        Http::assertSentCount(1);
    }

    public function test_coordinator_can_update_match_workflow_status(): void
    {
        $token = $this->tokenForCoordinator();
        [$studentRequest, $tutor] = $this->matchFixture();
        $match = MatchResult::create([
            'student_request_id' => $studentRequest->id,
            'tutor_id' => $tutor->id,
            'total_score' => 90,
            'score_breakdown' => ['subject' => 30],
            'deterministic_explanation' => 'Strong fit.',
            'generated_at' => now(),
        ]);

        $this->withToken($token)->patchJson("/api/matches/{$match->id}/workflow", [
            'status' => 'shortlisted',
            'outreach_status' => 'contacted',
            'coordinator_notes' => 'Parent wants a weekend trial.',
        ])
            ->assertOk()
            ->assertJsonPath('data.status', 'shortlisted')
            ->assertJsonPath('data.outreach_status', 'contacted');

        $this->assertDatabaseHas('match_results', [
            'id' => $match->id,
            'status' => 'shortlisted',
            'outreach_status' => 'contacted',
            'coordinator_notes' => 'Parent wants a weekend trial.',
        ]);
        $this->assertDatabaseHas('student_requests', [
            'id' => $studentRequest->id,
            'status' => 'shortlisted',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'match.workflow_updated',
            'auditable_type' => MatchResult::class,
            'auditable_id' => $match->id,
        ]);
    }

    public function test_tutor_role_cannot_update_match_workflow_status(): void
    {
        $token = $this->tokenFor(User::create([
            'name' => 'Tutor',
            'email' => 'workflow-tutor@example.test',
            'password' => 'password',
            'role' => 'tutor',
        ]));
        [$studentRequest, $tutor] = $this->matchFixture();
        $match = MatchResult::create([
            'student_request_id' => $studentRequest->id,
            'tutor_id' => $tutor->id,
            'total_score' => 90,
            'score_breakdown' => ['subject' => 30],
            'deterministic_explanation' => 'Strong fit.',
            'generated_at' => now(),
        ]);

        $this->withToken($token)->patchJson("/api/matches/{$match->id}/workflow", [
            'status' => 'confirmed',
        ])->assertForbidden();
    }

    public function test_tutor_can_list_open_assignments_and_see_own_application_status(): void
    {
        $user = User::create([
            'name' => 'Tutor',
            'email' => 'mobile-tutor@example.test',
            'password' => 'password',
            'role' => 'tutor',
        ]);
        $token = $this->tokenFor($user);
        [$studentRequest, $tutor] = $this->matchFixture(['user_id' => $user->id]);
        $assignment = Assignment::create([
            'student_request_id' => $studentRequest->id,
            'title' => 'Sec 4 O-Level Chemistry in Bishan',
            'status' => 'open',
            'published_at' => now(),
        ]);
        $assignment->applications()->create([
            'tutor_id' => $tutor->id,
            'status' => 'applied',
            'message' => 'Interested.',
            'applied_at' => now(),
        ]);

        $this->withToken($token)
            ->getJson('/api/assignments')
            ->assertOk()
            ->assertJsonPath('data.0.id', $assignment->id)
            ->assertJsonPath('data.0.application_status', 'applied')
            ->assertJsonPath('data.0.request.subject', 'Chemistry');
    }

    public function test_tutor_application_uses_authenticated_tutor_profile(): void
    {
        $user = User::create([
            'name' => 'Tutor',
            'email' => 'apply-tutor@example.test',
            'password' => 'password',
            'role' => 'tutor',
        ]);
        $token = $this->tokenFor($user);
        [$studentRequest, $tutor] = $this->matchFixture(['user_id' => $user->id]);
        $otherTutor = Tutor::create([
            'name' => 'Other Tutor',
            'tutor_type' => 'part_time',
            'teaching_mode' => 'online',
            'location' => 'Tampines',
            'hourly_rate_min' => 30,
            'hourly_rate_max' => 45,
        ]);
        $assignment = Assignment::create([
            'student_request_id' => $studentRequest->id,
            'title' => 'Sec 4 O-Level Chemistry in Bishan',
            'status' => 'open',
            'published_at' => now(),
        ]);

        $this->withToken($token)->postJson("/api/assignments/{$assignment->id}/applications", [
            'tutor_id' => $otherTutor->id,
            'message' => 'Available for a trial.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.tutor_id', $tutor->id);

        $this->assertDatabaseMissing('applications', [
            'assignment_id' => $assignment->id,
            'tutor_id' => $otherTutor->id,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application.applied',
            'auditable_type' => \App\Models\Application::class,
        ]);
    }

    public function test_tutor_can_withdraw_own_application(): void
    {
        $user = User::create([
            'name' => 'Tutor',
            'email' => 'withdraw-tutor@example.test',
            'password' => 'password',
            'role' => 'tutor',
        ]);
        $token = $this->tokenFor($user);
        [$studentRequest, $tutor] = $this->matchFixture(['user_id' => $user->id]);
        $assignment = Assignment::create([
            'student_request_id' => $studentRequest->id,
            'title' => 'Sec 4 O-Level Chemistry in Bishan',
            'status' => 'open',
            'published_at' => now(),
        ]);
        $assignment->applications()->create([
            'tutor_id' => $tutor->id,
            'status' => 'applied',
            'applied_at' => now(),
        ]);

        $this->withToken($token)
            ->deleteJson("/api/assignments/{$assignment->id}/applications")
            ->assertOk()
            ->assertJsonPath('data.status', 'withdrawn');

        $this->assertDatabaseHas('applications', [
            'assignment_id' => $assignment->id,
            'tutor_id' => $tutor->id,
            'status' => 'withdrawn',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application.withdrawn',
            'auditable_type' => \App\Models\Application::class,
        ]);

        $this->withToken($token)
            ->postJson("/api/assignments/{$assignment->id}/applications", [
                'message' => 'Available again.',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'applied');
    }

    public function test_tutor_can_update_own_profile_and_availability(): void
    {
        $user = User::create([
            'name' => 'Tutor',
            'email' => 'profile-tutor@example.test',
            'password' => 'password',
            'role' => 'tutor',
        ]);
        $token = $this->tokenFor($user);
        [, $tutor] = $this->matchFixture(['user_id' => $user->id]);

        $this->withToken($token)->getJson('/api/tutor/profile')
            ->assertOk()
            ->assertJsonPath('data.id', $tutor->id);

        $this->withToken($token)->patchJson('/api/tutor/profile', [
            'teaching_mode' => 'online',
            'location' => 'Remote',
            'hourly_rate_min' => 60,
            'hourly_rate_max' => 80,
            'bio' => 'Updated profile for online Chemistry support.',
            'is_active' => true,
            'availabilities' => [
                ['day_of_week' => 'weekday', 'time_block' => 'evening'],
                ['day_of_week' => 'saturday', 'time_block' => 'morning'],
            ],
        ])
            ->assertOk()
            ->assertJsonPath('data.teaching_mode', 'online')
            ->assertJsonPath('data.location', 'Remote')
            ->assertJsonPath('data.availabilities.0.day_of_week', 'weekday');

        $this->assertDatabaseHas('tutors', [
            'id' => $tutor->id,
            'teaching_mode' => 'online',
            'location' => 'Remote',
            'hourly_rate_min' => 60,
            'hourly_rate_max' => 80,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'tutor.profile_updated',
            'auditable_type' => Tutor::class,
            'auditable_id' => $tutor->id,
        ]);
    }

    public function test_coordinator_cannot_use_tutor_self_service_profile_route(): void
    {
        $token = $this->tokenForCoordinator();

        $this->withToken($token)
            ->getJson('/api/tutor/profile')
            ->assertForbidden();
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

    public function test_login_and_logout_are_audited(): void
    {
        User::create([
            'name' => 'Coordinator',
            'email' => 'audit-coordinator@example.test',
            'password' => 'password',
            'role' => 'coordinator',
        ]);

        $login = $this->postJson('/api/auth/login', [
            'email' => 'audit-coordinator@example.test',
            'password' => 'password',
        ])->assertOk();

        $this->withToken($login->json('data.token'))
            ->postJson('/api/auth/logout')
            ->assertOk();

        $this->assertSame(
            ['auth.login', 'auth.logout'],
            AuditLog::query()->orderBy('id')->pluck('action')->all()
        );
    }

    private function matchFixture(array $tutorOverrides = []): array
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
        $tutor = Tutor::create(array_merge([
            'name' => 'Daniel Lim',
            'tutor_type' => 'ex_moe',
            'teaching_mode' => 'hybrid',
            'location' => 'Bishan',
            'hourly_rate_min' => 55,
            'hourly_rate_max' => 70,
            'acceptance_rate' => 0.8,
            'success_score' => 0.8,
        ], $tutorOverrides));

        return [$studentRequest, $tutor];
    }
}

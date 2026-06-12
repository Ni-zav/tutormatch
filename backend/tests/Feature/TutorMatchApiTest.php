<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AuditLog;
use App\Models\Level;
use App\Models\MatchResult;
use App\Models\MessageDraft;
use App\Models\StudentRequest;
use App\Models\Subject;
use App\Models\Tutor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
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
            ->assertJsonPath('data.user.role', 'coordinator')
            ->assertJsonPath('data.user.token_last_used_at', null)
            ->assertJsonStructure(['data' => ['user' => ['token_issued_at', 'token_expires_at']]]);

        $this->withToken($login->json('data.token'))
            ->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'coordinator@example.test')
            ->assertJsonStructure(['data' => ['token_last_used_at', 'token_expires_at']]);

        $this->assertNotNull(User::where('email', 'coordinator@example.test')->first()?->api_token_last_used_at);
    }

    public function test_failed_login_attempts_are_audited_without_raw_email(): void
    {
        User::create([
            'name' => 'Coordinator',
            'email' => 'failed-login@example.test',
            'password' => 'password',
            'role' => 'coordinator',
        ]);

        $this->postJson('/api/auth/login', [
            'email' => 'failed-login@example.test',
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        $log = AuditLog::where('action', 'auth.login_failed')->first();

        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
        $this->assertTrue($log->metadata['user_exists']);
        $this->assertSame(hash('sha256', 'failed-login@example.test'), $log->metadata['email_hash']);
        $this->assertStringNotContainsString('failed-login@example.test', json_encode($log->metadata, JSON_THROW_ON_ERROR));
        $this->assertStringNotContainsString('wrong-password', json_encode($log->metadata, JSON_THROW_ON_ERROR));
    }

    public function test_unknown_email_failed_login_is_audited_without_raw_email(): void
    {
        $this->postJson('/api/auth/login', [
            'email' => 'unknown-login@example.test',
            'password' => 'wrong-password',
        ])->assertUnprocessable();

        $log = AuditLog::where('action', 'auth.login_failed')->first();

        $this->assertNotNull($log);
        $this->assertNull($log->user_id);
        $this->assertFalse($log->metadata['user_exists']);
        $this->assertSame(hash('sha256', 'unknown-login@example.test'), $log->metadata['email_hash']);
        $this->assertStringNotContainsString('unknown-login@example.test', json_encode($log->metadata, JSON_THROW_ON_ERROR));
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

    public function test_coordinator_can_review_audit_logs(): void
    {
        $user = User::create([
            'name' => 'Coordinator',
            'email' => 'audit-review@example.test',
            'password' => 'password',
            'role' => 'coordinator',
        ]);
        $token = $this->tokenFor($user);
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'request.created',
            'auditable_type' => StudentRequest::class,
            'auditable_id' => 123,
            'metadata' => ['status' => 'new'],
            'ip_address' => '127.0.0.1',
        ]);
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'auth.login',
            'auditable_type' => User::class,
            'auditable_id' => $user->id,
        ]);

        $this->withToken($token)
            ->getJson('/api/audit-logs?action=request.created')
            ->assertOk()
            ->assertJsonPath('data.0.action', 'request.created')
            ->assertJsonPath('data.0.actor.email', 'audit-review@example.test')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.metadata.status', 'new');
    }

    public function test_coordinator_can_export_audit_logs_as_csv(): void
    {
        $user = User::create([
            'name' => 'Coordinator',
            'email' => 'audit-export@example.test',
            'password' => 'password',
            'role' => 'coordinator',
        ]);
        $token = $this->tokenFor($user);
        AuditLog::create([
            'user_id' => $user->id,
            'action' => 'request.created',
            'auditable_type' => StudentRequest::class,
            'auditable_id' => 123,
            'ip_address' => '127.0.0.1',
        ]);

        $this->withToken($token)
            ->get('/api/audit-logs?format=csv')
            ->assertOk()
            ->assertHeader('content-type', 'text/csv')
            ->assertSee('request.created')
            ->assertSee('audit-export@example.test');
    }

    public function test_tutor_cannot_review_audit_logs(): void
    {
        $token = $this->tokenFor(User::create([
            'name' => 'Tutor',
            'email' => 'audit-tutor@example.test',
            'password' => 'password',
            'role' => 'tutor',
        ]));

        $this->withToken($token)
            ->getJson('/api/audit-logs')
            ->assertForbidden();
    }

    public function test_retention_prune_command_supports_dry_run(): void
    {
        $oldLog = AuditLog::create([
            'action' => 'request.created',
        ]);
        $this->ageRecord($oldLog, 400);
        $oldDraft = MessageDraft::create([
            'audience' => 'client',
            'channel' => 'whatsapp',
            'body' => 'Old draft',
            'generated_by' => 'mock_ai',
        ]);
        $this->ageRecord($oldDraft, 200);
        $oldRequest = StudentRequest::create([
            'student_name' => 'Old Student',
            'subject_id' => Subject::create(['name' => 'Physics'])->id,
            'level_id' => Level::create(['name' => 'JC 1'])->id,
            'location' => 'Bishan',
            'teaching_mode' => 'home',
            'budget_max' => 70,
            'status' => 'closed',
            'schedule_notes' => 'Old closed request',
        ]);
        $this->ageRecord($oldRequest, 800);
        $inactiveTutor = Tutor::create([
            'name' => 'Inactive Tutor',
            'tutor_type' => 'part_time',
            'teaching_mode' => 'online',
            'location' => 'Tampines',
            'hourly_rate_min' => 40,
            'hourly_rate_max' => 60,
            'is_active' => false,
        ]);
        $this->ageRecord($inactiveTutor, 800);

        Artisan::call('tutormatch:prune-retention', [
            '--audit-days' => 365,
            '--draft-days' => 180,
            '--request-days' => 730,
            '--inactive-tutor-days' => 730,
            '--dry-run' => true,
        ]);

        $this->assertDatabaseHas('audit_logs', ['id' => $oldLog->id]);
        $this->assertDatabaseHas('message_drafts', ['id' => $oldDraft->id]);
        $this->assertDatabaseHas('student_requests', ['id' => $oldRequest->id]);
        $this->assertDatabaseHas('tutors', ['id' => $inactiveTutor->id, 'name' => 'Inactive Tutor']);
        $this->assertStringContainsString('Would delete 1 audit logs', Artisan::output());
        $this->assertStringContainsString('Would delete 1 message drafts', Artisan::output());
        $this->assertStringContainsString('Would delete 1 finalized student requests', Artisan::output());
        $this->assertStringContainsString('Would anonymize 1 inactive tutor profiles', Artisan::output());
    }

    public function test_retention_prune_command_deletes_only_expired_records(): void
    {
        $oldLog = AuditLog::create([
            'action' => 'request.created',
        ]);
        $this->ageRecord($oldLog, 400);
        $newLog = AuditLog::create([
            'action' => 'auth.login',
        ]);
        $this->ageRecord($newLog, 30);
        $oldDraft = MessageDraft::create([
            'audience' => 'client',
            'channel' => 'whatsapp',
            'body' => 'Old draft',
            'generated_by' => 'mock_ai',
        ]);
        $this->ageRecord($oldDraft, 200);
        $newDraft = MessageDraft::create([
            'audience' => 'client',
            'channel' => 'whatsapp',
            'body' => 'Current draft',
            'generated_by' => 'mock_ai',
        ]);
        $this->ageRecord($newDraft, 20);
        $subject = Subject::create(['name' => 'Physics']);
        $level = Level::create(['name' => 'JC 1']);
        $oldClosedRequest = StudentRequest::create([
            'student_name' => 'Old Closed Student',
            'subject_id' => $subject->id,
            'level_id' => $level->id,
            'location' => 'Bishan',
            'teaching_mode' => 'home',
            'budget_max' => 70,
            'status' => 'closed',
            'schedule_notes' => 'Old closed request',
        ]);
        $this->ageRecord($oldClosedRequest, 800);
        $oldOpenRequest = StudentRequest::create([
            'student_name' => 'Old Open Student',
            'subject_id' => $subject->id,
            'level_id' => $level->id,
            'location' => 'Bishan',
            'teaching_mode' => 'home',
            'budget_max' => 70,
            'status' => 'new',
            'schedule_notes' => 'Old but still open',
        ]);
        $this->ageRecord($oldOpenRequest, 800);
        $inactiveTutor = Tutor::create([
            'name' => 'Inactive Tutor',
            'tutor_type' => 'part_time',
            'teaching_mode' => 'online',
            'location' => 'Tampines',
            'hourly_rate_min' => 40,
            'hourly_rate_max' => 60,
            'bio' => 'Contains personal profile notes.',
            'is_active' => false,
        ]);
        $inactiveTutor->availabilities()->create(['day_of_week' => 'weekday', 'time_block' => 'evening']);
        $this->ageRecord($inactiveTutor, 800);
        $activeTutor = Tutor::create([
            'name' => 'Active Tutor',
            'tutor_type' => 'part_time',
            'teaching_mode' => 'online',
            'location' => 'Tampines',
            'hourly_rate_min' => 40,
            'hourly_rate_max' => 60,
            'is_active' => true,
        ]);
        $this->ageRecord($activeTutor, 800);

        Artisan::call('tutormatch:prune-retention', [
            '--audit-days' => 365,
            '--draft-days' => 180,
            '--request-days' => 730,
            '--inactive-tutor-days' => 730,
        ]);

        $this->assertDatabaseMissing('audit_logs', ['id' => $oldLog->id]);
        $this->assertDatabaseHas('audit_logs', ['id' => $newLog->id]);
        $this->assertDatabaseMissing('message_drafts', ['id' => $oldDraft->id]);
        $this->assertDatabaseHas('message_drafts', ['id' => $newDraft->id]);
        $this->assertDatabaseMissing('student_requests', ['id' => $oldClosedRequest->id]);
        $this->assertDatabaseHas('student_requests', ['id' => $oldOpenRequest->id]);
        $this->assertDatabaseHas('tutors', [
            'id' => $inactiveTutor->id,
            'name' => "Inactive tutor #{$inactiveTutor->id}",
            'user_id' => null,
            'location' => 'Redacted',
            'bio' => null,
        ]);
        $this->assertDatabaseMissing('tutor_availabilities', ['tutor_id' => $inactiveTutor->id]);
        $this->assertDatabaseHas('tutors', ['id' => $activeTutor->id, 'name' => 'Active Tutor']);
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
            'api_token_last_used_at' => null,
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

    public function test_dashboard_summary_includes_operational_queue_counts(): void
    {
        $token = $this->tokenForCoordinator();
        [$studentRequest, $tutor] = $this->matchFixture();
        $studentRequest->update([
            'status' => 'needs_follow_up',
            'urgency' => 'urgent',
        ]);
        $noMatchRequest = StudentRequest::create([
            'student_name' => 'No Match Student',
            'subject_id' => $studentRequest->subject_id,
            'level_id' => $studentRequest->level_id,
            'location' => 'Bishan',
            'teaching_mode' => 'home',
            'budget_max' => 65,
            'urgency' => 'normal',
            'schedule_notes' => 'Flexible',
            'status' => 'no_matches',
        ]);
        $assignment = Assignment::create([
            'student_request_id' => $studentRequest->id,
            'title' => 'Sec 4 O-Level Chemistry in Bishan',
            'status' => 'open',
            'published_at' => now(),
        ]);
        $assignment->applications()->create([
            'tutor_id' => $tutor->id,
            'status' => 'applied',
            'message' => 'Available.',
            'applied_at' => now(),
        ]);
        MatchResult::create([
            'student_request_id' => $studentRequest->id,
            'tutor_id' => $tutor->id,
            'total_score' => 90,
            'score_breakdown' => ['subject' => 30],
            'deterministic_explanation' => 'Strong fit.',
            'status' => 'shortlisted',
            'outreach_status' => 'contacted',
            'generated_at' => now(),
        ]);

        $this->withToken($token)
            ->getJson('/api/dashboard/summary')
            ->assertOk()
            ->assertJsonPath('requests.no_matches', 1)
            ->assertJsonPath('requests.needs_follow_up', 1)
            ->assertJsonPath('requests.urgent', 1)
            ->assertJsonPath('applications.pending', 1)
            ->assertJsonPath('matches.shortlisted', 1)
            ->assertJsonPath('matches.contacted', 1);

        $this->assertDatabaseHas('student_requests', ['id' => $noMatchRequest->id, 'status' => 'no_matches']);
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

    public function test_public_intake_can_create_persisted_request_with_limited_response(): void
    {
        $subject = Subject::create(['name' => 'Chemistry']);
        $level = Level::create(['name' => 'Sec 4 O-Level']);

        $response = $this->postJson('/api/intake/requests', [
            'student_name' => 'Public Student',
            'parent_name' => 'Public Parent',
            'subject_id' => $subject->id,
            'level_id' => $level->id,
            'location' => 'Bishan',
            'teaching_mode' => 'home',
            'budget_min' => 45,
            'budget_max' => 65,
            'requested_day_of_week' => 'saturday',
            'requested_time_block' => 'morning',
            'schedule_notes' => 'Weekend mornings preferred',
            'notes' => 'Submitted from public intake.',
            'privacy_acknowledged' => true,
        ])->assertCreated();

        $requestId = $response->json('data.id');
        $response->assertJsonPath('data.status', 'new')
            ->assertJsonMissingPath('data.student_name')
            ->assertJsonMissingPath('data.parent_name');

        $this->assertDatabaseHas('student_requests', [
            'id' => $requestId,
            'student_name' => 'Public Student',
            'parent_name' => 'Public Parent',
            'status' => 'new',
        ]);
        $this->assertDatabaseHas('assignments', [
            'student_request_id' => $requestId,
            'title' => 'Sec 4 O-Level Chemistry in Bishan',
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'public_intake.created',
            'auditable_type' => StudentRequest::class,
            'auditable_id' => $requestId,
        ]);
    }

    public function test_public_intake_options_expose_only_reference_ids_and_names(): void
    {
        $subject = Subject::create(['name' => 'Chemistry']);
        $level = Level::create(['name' => 'Sec 4 O-Level']);

        $this->getJson('/api/intake/options')
            ->assertOk()
            ->assertJsonPath('data.subjects.0.id', $subject->id)
            ->assertJsonPath('data.subjects.0.name', 'Chemistry')
            ->assertJsonPath('data.levels.0.id', $level->id)
            ->assertJsonPath('data.levels.0.name', 'Sec 4 O-Level');
    }

    public function test_public_intake_requires_privacy_acknowledgement(): void
    {
        $subject = Subject::create(['name' => 'Chemistry']);
        $level = Level::create(['name' => 'Sec 4 O-Level']);

        $this->postJson('/api/intake/requests', [
            'student_name' => 'Public Student',
            'subject_id' => $subject->id,
            'level_id' => $level->id,
            'location' => 'Bishan',
            'teaching_mode' => 'home',
            'budget_max' => 65,
            'schedule_notes' => 'Weekend mornings preferred',
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['privacy_acknowledged']);
    }

    public function test_match_generation_marks_request_when_no_candidates_are_available(): void
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
            'status' => 'new',
        ]);

        $this->withToken($token)->postJson("/api/requests/{$studentRequest->id}/generate-matches")
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertDatabaseHas('student_requests', [
            'id' => $studentRequest->id,
            'status' => 'no_matches',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'matches.generated',
            'auditable_type' => StudentRequest::class,
            'auditable_id' => $studentRequest->id,
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

    public function test_openai_message_draft_prompt_redacts_personal_names_and_notes(): void
    {
        config([
            'services.ai.provider' => 'openai',
            'services.ai.openai_api_key' => 'test-key',
            'services.ai.openai_model' => 'test-model',
            'services.ai.timeout_seconds' => 1,
        ]);
        $capturedPrompt = null;
        Http::fake(function ($request) use (&$capturedPrompt) {
            $payload = $request->data();
            $capturedPrompt = json_decode($payload['messages'][1]['content'], true);

            return Http::response([
                'choices' => [
                    [
                        'message' => [
                            'content' => json_encode([
                                'audience' => 'client',
                                'channel' => 'whatsapp',
                                'body' => 'Hi, we found a suitable tutor option for this request. Please review before we proceed.',
                            ]),
                        ],
                    ],
                ],
            ]);
        });
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
            'schedule_notes' => 'Private note mentioning Mrs Tan prefers weekends.',
            'notes' => 'Sensitive coordinator note about Demo Student A.',
        ]);
        $tutor = Tutor::create([
            'name' => 'Daniel Lim',
            'tutor_type' => 'ex_moe',
            'teaching_mode' => 'hybrid',
            'location' => 'Bishan',
            'hourly_rate_min' => 55,
            'hourly_rate_max' => 70,
        ]);

        $this->withToken($token)->postJson('/api/message-drafts', [
            'student_request_id' => $studentRequest->id,
            'tutor_id' => $tutor->id,
            'audience' => 'client',
            'channel' => 'whatsapp',
        ])
            ->assertCreated()
            ->assertJsonPath('data.generated_by', 'openai')
            ->assertJsonPath('data.fallback_used', false)
            ->assertJsonPath('data.generation_metadata.prompt_redaction', 'personal_names_and_freeform_notes_removed');

        $this->assertIsArray($capturedPrompt);
        $promptJson = json_encode($capturedPrompt, JSON_THROW_ON_ERROR);
        $this->assertStringNotContainsString('Demo Student A', $promptJson);
        $this->assertStringNotContainsString('Mrs Tan', $promptJson);
        $this->assertStringNotContainsString('Daniel Lim', $promptJson);
        $this->assertStringNotContainsString('Sensitive coordinator note', $promptJson);
        $this->assertStringNotContainsString('Private note', $promptJson);
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
            ->assertJsonPath('data.0.applications', [])
            ->assertJsonPath('data.0.request.subject', 'Chemistry');
    }

    public function test_tutor_feed_keeps_assignments_with_own_final_application_status(): void
    {
        $user = User::create([
            'name' => 'Tutor',
            'email' => 'accepted-feed-tutor@example.test',
            'password' => 'password',
            'role' => 'tutor',
        ]);
        $token = $this->tokenFor($user);
        [$studentRequest, $tutor] = $this->matchFixture(['user_id' => $user->id]);
        $assignment = Assignment::create([
            'student_request_id' => $studentRequest->id,
            'title' => 'Sec 4 O-Level Chemistry in Bishan',
            'status' => 'confirmed',
            'published_at' => now(),
        ]);
        $assignment->applications()->create([
            'tutor_id' => $tutor->id,
            'status' => 'accepted',
            'message' => 'Accepted by coordinator.',
            'applied_at' => now(),
        ]);

        $this->withToken($token)
            ->getJson('/api/assignments')
            ->assertOk()
            ->assertJsonPath('data.0.id', $assignment->id)
            ->assertJsonPath('data.0.status', 'confirmed')
            ->assertJsonPath('data.0.application_status', 'accepted')
            ->assertJsonPath('data.0.applications', []);
    }

    public function test_coordinator_can_review_assignment_applications(): void
    {
        $token = $this->tokenForCoordinator();
        [$studentRequest, $tutor] = $this->matchFixture();
        $assignment = Assignment::create([
            'student_request_id' => $studentRequest->id,
            'title' => 'Sec 4 O-Level Chemistry in Bishan',
            'status' => 'open',
            'published_at' => now(),
        ]);
        $assignment->applications()->create([
            'tutor_id' => $tutor->id,
            'status' => 'applied',
            'message' => 'Available for Saturday morning.',
            'applied_at' => now(),
        ]);

        $this->withToken($token)
            ->getJson('/api/assignments')
            ->assertOk()
            ->assertJsonPath('data.0.id', $assignment->id)
            ->assertJsonPath('data.0.applications.0.tutor_id', $tutor->id)
            ->assertJsonPath('data.0.applications.0.tutor_name', 'Daniel Lim')
            ->assertJsonPath('data.0.applications.0.message', 'Available for Saturday morning.');
    }

    public function test_coordinator_can_update_application_status(): void
    {
        $token = $this->tokenForCoordinator();
        [$studentRequest, $tutor] = $this->matchFixture();
        $assignment = Assignment::create([
            'student_request_id' => $studentRequest->id,
            'title' => 'Sec 4 O-Level Chemistry in Bishan',
            'status' => 'open',
            'published_at' => now(),
        ]);
        $application = $assignment->applications()->create([
            'tutor_id' => $tutor->id,
            'status' => 'applied',
            'message' => 'Available for Saturday morning.',
            'applied_at' => now(),
        ]);
        $otherTutor = Tutor::create([
            'name' => 'Backup Tutor',
            'tutor_type' => 'part_time',
            'teaching_mode' => 'online',
            'location' => 'Tampines',
            'hourly_rate_min' => 45,
            'hourly_rate_max' => 60,
        ]);
        $otherApplication = $assignment->applications()->create([
            'tutor_id' => $otherTutor->id,
            'status' => 'applied',
            'message' => 'Can do weekday evenings.',
            'applied_at' => now(),
        ]);

        $this->withToken($token)
            ->patchJson("/api/applications/{$application->id}", [
                'status' => 'accepted',
            ])
            ->assertOk()
            ->assertJsonPath('data.status', 'accepted')
            ->assertJsonPath('data.tutor_name', 'Daniel Lim');

        $this->assertDatabaseHas('applications', [
            'id' => $application->id,
            'status' => 'accepted',
        ]);
        $this->assertDatabaseHas('applications', [
            'id' => $otherApplication->id,
            'status' => 'rejected',
        ]);
        $this->assertDatabaseHas('assignments', [
            'id' => $assignment->id,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('student_requests', [
            'id' => $studentRequest->id,
            'status' => 'confirmed',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'application.status_updated',
            'auditable_type' => \App\Models\Application::class,
            'auditable_id' => $application->id,
        ]);
    }

    public function test_tutor_cannot_update_application_status_directly(): void
    {
        $user = User::create([
            'name' => 'Tutor',
            'email' => 'application-status-tutor@example.test',
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
        $application = $assignment->applications()->create([
            'tutor_id' => $tutor->id,
            'status' => 'applied',
            'applied_at' => now(),
        ]);

        $this->withToken($token)
            ->patchJson("/api/applications/{$application->id}", [
                'status' => 'accepted',
            ])
            ->assertForbidden();
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

    public function test_state_changing_application_routes_are_rate_limited(): void
    {
        $routes = collect(Route::getRoutes());
        $expectedRoutes = [
            ['PATCH', 'api/tutor/profile'],
            ['POST', 'api/assignments/{assignment}/applications'],
            ['DELETE', 'api/assignments/{assignment}/applications'],
            ['PATCH', 'api/applications/{application}'],
        ];

        foreach ($expectedRoutes as [$method, $uri]) {
            $route = $routes->first(
                fn ($route) => $route->uri() === $uri && in_array($method, $route->methods(), true)
            );

            $this->assertNotNull($route, "{$method} {$uri} route should exist.");
            $this->assertTrue(
                collect($route->middleware())->contains(fn (string $middleware) => str_starts_with($middleware, 'throttle:')),
                "{$method} {$uri} route should be rate limited."
            );
        }
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
            'api_token_last_used_at' => null,
        ])->save();

        return $token;
    }

    private function ageRecord(Model $model, int $days): void
    {
        $model::withoutTimestamps(function () use ($model, $days): void {
            $model->forceFill([
                'created_at' => now()->subDays($days),
                'updated_at' => now()->subDays($days),
            ])->save();
        });
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

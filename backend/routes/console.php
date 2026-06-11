<?php

use App\Models\AuditLog;
use App\Models\MessageDraft;
use App\Models\StudentRequest;
use App\Models\Tutor;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tutormatch:prune-retention {--audit-days=} {--draft-days=} {--request-days=} {--inactive-tutor-days=} {--dry-run}', function () {
    $auditDays = (int) ($this->option('audit-days') ?: config('services.retention.audit_log_days', 365));
    $draftDays = (int) ($this->option('draft-days') ?: config('services.retention.message_draft_days', 180));
    $requestDays = (int) ($this->option('request-days') ?: config('services.retention.finalized_request_days', 730));
    $inactiveTutorDays = (int) ($this->option('inactive-tutor-days') ?: config('services.retention.inactive_tutor_days', 730));

    if ($auditDays < 1 || $draftDays < 1 || $requestDays < 1 || $inactiveTutorDays < 1) {
        $this->error('Retention days must be positive integers.');

        return 1;
    }

    $auditCutoff = now()->subDays($auditDays);
    $draftCutoff = now()->subDays($draftDays);
    $requestCutoff = now()->subDays($requestDays);
    $inactiveTutorCutoff = now()->subDays($inactiveTutorDays);
    $auditQuery = AuditLog::query()->where('created_at', '<', $auditCutoff);
    $draftQuery = MessageDraft::query()->where('created_at', '<', $draftCutoff);
    $requestQuery = StudentRequest::query()
        ->whereIn('status', ['confirmed', 'rejected', 'closed'])
        ->where('updated_at', '<', $requestCutoff);
    $inactiveTutorQuery = Tutor::query()
        ->where('is_active', false)
        ->where('updated_at', '<', $inactiveTutorCutoff)
        ->where('name', 'not like', 'Inactive tutor #%');
    $auditCount = $auditQuery->count();
    $draftCount = $draftQuery->count();
    $requestCount = $requestQuery->count();
    $inactiveTutorCount = $inactiveTutorQuery->count();

    if ($this->option('dry-run')) {
        $this->info("Would delete {$auditCount} audit logs older than {$auditDays} days.");
        $this->info("Would delete {$draftCount} message drafts older than {$draftDays} days.");
        $this->info("Would delete {$requestCount} finalized student requests older than {$requestDays} days.");
        $this->info("Would anonymize {$inactiveTutorCount} inactive tutor profiles older than {$inactiveTutorDays} days.");

        return 0;
    }

    $auditQuery->delete();
    $draftQuery->delete();
    $requestQuery->delete();
    $inactiveTutorQuery->chunkById(100, function ($tutors): void {
        $tutors->each(function (Tutor $tutor): void {
            $tutor->availabilities()->delete();
            $tutor->forceFill([
                'user_id' => null,
                'name' => "Inactive tutor #{$tutor->id}",
                'location' => 'Redacted',
                'bio' => null,
            ])->save();
        });
    });
    $this->info("Deleted {$auditCount} audit logs older than {$auditDays} days.");
    $this->info("Deleted {$draftCount} message drafts older than {$draftDays} days.");
    $this->info("Deleted {$requestCount} finalized student requests older than {$requestDays} days.");
    $this->info("Anonymized {$inactiveTutorCount} inactive tutor profiles older than {$inactiveTutorDays} days.");

    return 0;
})->purpose('Prune or anonymize old operational records according to retention settings');

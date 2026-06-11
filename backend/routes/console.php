<?php

use App\Models\AuditLog;
use App\Models\MessageDraft;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('tutormatch:prune-retention {--audit-days=} {--draft-days=} {--dry-run}', function () {
    $auditDays = (int) ($this->option('audit-days') ?: config('services.retention.audit_log_days', 365));
    $draftDays = (int) ($this->option('draft-days') ?: config('services.retention.message_draft_days', 180));

    if ($auditDays < 1 || $draftDays < 1) {
        $this->error('Retention days must be positive integers.');

        return 1;
    }

    $auditCutoff = now()->subDays($auditDays);
    $draftCutoff = now()->subDays($draftDays);
    $auditQuery = AuditLog::query()->where('created_at', '<', $auditCutoff);
    $draftQuery = MessageDraft::query()->where('created_at', '<', $draftCutoff);
    $auditCount = $auditQuery->count();
    $draftCount = $draftQuery->count();

    if ($this->option('dry-run')) {
        $this->info("Would delete {$auditCount} audit logs older than {$auditDays} days.");
        $this->info("Would delete {$draftCount} message drafts older than {$draftDays} days.");

        return 0;
    }

    $auditQuery->delete();
    $draftQuery->delete();
    $this->info("Deleted {$auditCount} audit logs older than {$auditDays} days.");
    $this->info("Deleted {$draftCount} message drafts older than {$draftDays} days.");

    return 0;
})->purpose('Prune old audit logs and message drafts according to retention settings');

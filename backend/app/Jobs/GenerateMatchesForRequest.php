<?php

namespace App\Jobs;

use App\Models\AuditLog;
use App\Models\StudentRequest;
use App\Services\Matching\TutorMatchingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GenerateMatchesForRequest implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly int $studentRequestId,
        public readonly ?int $userId = null,
    ) {
    }

    public function handle(TutorMatchingService $matchingService): void
    {
        $studentRequest = StudentRequest::find($this->studentRequestId);

        if (! $studentRequest) {
            return;
        }

        $studentRequest->update(['status' => 'matching']);
        $matches = $matchingService->generateForRequest($studentRequest);
        $studentRequest->update([
            'status' => $matches->isEmpty() ? 'no_matches' : 'matching',
        ]);

        AuditLog::create([
            'user_id' => $this->userId,
            'action' => 'matches.generated',
            'auditable_type' => $studentRequest->getMorphClass(),
            'auditable_id' => $studentRequest->id,
            'metadata' => [
                'match_count' => $matches->count(),
                'top_score' => $matches->max('total_score'),
                'async' => true,
            ],
        ]);
    }
}

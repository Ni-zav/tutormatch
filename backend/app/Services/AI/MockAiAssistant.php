<?php

namespace App\Services\AI;

use App\Models\MatchResult;
use App\Models\StudentRequest;
use App\Models\Tutor;

class MockAiAssistant implements AiAssistant
{
    public function explainMatch(MatchResult $matchResult): array
    {
        $matchResult->loadMissing(['studentRequest.subject', 'studentRequest.level', 'tutor']);
        $breakdown = $matchResult->score_breakdown;
        $strengths = array_keys(array_filter($breakdown, fn ($score) => $score > 0));
        $risks = array_keys(array_filter($breakdown, fn ($score) => $score === 0));

        return [
            'generated_by' => 'mock_ai',
            'prompt_version' => 'match-explain-v1',
            'fallback_used' => false,
            'summary' => $matchResult->deterministic_explanation,
            'strengths' => $strengths,
            'risks' => $risks,
            'coordinator_note' => $matchResult->total_score >= 75
                ? 'Good shortlist candidate. Confirm availability and parent preferences before sending.'
                : 'Review manually before shortlisting because some matching factors are weak.',
        ];
    }

    public function draftMessage(StudentRequest $request, ?Tutor $tutor, string $audience, string $channel): array
    {
        $request->loadMissing(['subject', 'level']);
        $body = $audience === 'tutor'
            ? "Hi {$tutor?->name}, we have a {$request->level->name} {$request->subject->name} assignment in {$request->location}. Budget is up to SGD {$request->budget_max}/hr, preferred schedule: {$request->schedule_notes}. Please let us know if you are keen and available."
            : "Hi {$request->parent_name}, we found a suitable tutor option for {$request->student_name}'s {$request->level->name} {$request->subject->name} request in {$request->location}. The profile looks aligned on subject, level, schedule, and budget. Please review before we proceed.";

        return [
            'generated_by' => 'mock_ai',
            'prompt_version' => 'message-draft-v1',
            'fallback_used' => false,
            'audience' => $audience,
            'channel' => $channel,
            'body' => $body,
            'generation_metadata' => [
                'provider' => 'mock',
                'template' => 'deterministic_message_v1',
            ],
        ];
    }
}

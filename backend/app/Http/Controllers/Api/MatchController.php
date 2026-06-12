<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMatchWorkflowRequest;
use App\Http\Resources\MatchResultResource;
use App\Jobs\GenerateMatchesForRequest;
use App\Models\MatchResult;
use App\Models\StudentRequest;
use App\Services\AuditLogger;
use App\Services\AI\AiAssistant;
use App\Services\Matching\TutorMatchingService;

class MatchController extends Controller
{
    public function index(StudentRequest $request)
    {
        $matches = $request->matchResults()
            ->with(['tutor.tutorSubjects.subject', 'tutor.tutorSubjects.level', 'tutor.availabilities'])
            ->orderByDesc('total_score')
            ->paginate((int) request('per_page', 10));

        return MatchResultResource::collection($matches);
    }

    public function generate(StudentRequest $request, TutorMatchingService $matchingService, AuditLogger $auditLogger)
    {
        if (request()->boolean('async')) {
            $request->update(['status' => 'matching']);
            GenerateMatchesForRequest::dispatch($request->id, request()->user()?->id);

            return response()->json([
                'data' => [
                    'status' => 'queued',
                    'student_request_id' => $request->id,
                ],
            ], 202);
        }

        $request->update(['status' => 'matching']);
        $matches = $matchingService->generateForRequest($request);
        $request->update([
            'status' => $matches->isEmpty() ? 'no_matches' : 'matching',
        ]);
        $auditLogger->record(request(), 'matches.generated', $request, [
            'match_count' => $matches->count(),
            'top_score' => $matches->max('total_score'),
        ]);

        return MatchResultResource::collection($matches->load('tutor'));
    }

    public function explain(MatchResult $matchResult, AiAssistant $aiAssistant): array
    {
        return $aiAssistant->explainMatch($matchResult);
    }

    public function updateWorkflow(UpdateMatchWorkflowRequest $request, MatchResult $matchResult, AuditLogger $auditLogger): MatchResultResource
    {
        $validated = $request->validated();
        $previous = [
            'status' => $matchResult->status,
            'outreach_status' => $matchResult->outreach_status,
        ];
        $matchResult->fill([
            'status' => $validated['status'],
            'outreach_status' => array_key_exists('outreach_status', $validated) ? $validated['outreach_status'] : $matchResult->outreach_status,
            'coordinator_notes' => array_key_exists('coordinator_notes', $validated) ? $validated['coordinator_notes'] : $matchResult->coordinator_notes,
            'status_updated_at' => now(),
        ])->save();

        $studentRequestStatus = match ($matchResult->status) {
            'shortlisted' => 'shortlisted',
            'confirmed' => 'confirmed',
            'rejected' => 'rejected',
            'closed' => 'closed',
            'needs_follow_up' => 'needs_follow_up',
            default => null,
        };

        if ($studentRequestStatus) {
            $matchResult->studentRequest()->update(['status' => $studentRequestStatus]);
        }
        $auditLogger->record($request, 'match.workflow_updated', $matchResult, [
            'previous' => $previous,
            'current' => [
                'status' => $matchResult->status,
                'outreach_status' => $matchResult->outreach_status,
            ],
            'student_request_id' => $matchResult->student_request_id,
        ]);

        return new MatchResultResource($matchResult->load('tutor'));
    }
}

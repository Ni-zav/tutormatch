<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateMatchWorkflowRequest;
use App\Http\Resources\MatchResultResource;
use App\Models\MatchResult;
use App\Models\StudentRequest;
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

    public function generate(StudentRequest $request, TutorMatchingService $matchingService)
    {
        $request->update(['status' => 'matching']);
        $matches = $matchingService->generateForRequest($request);

        return MatchResultResource::collection($matches->load('tutor'));
    }

    public function explain(MatchResult $matchResult, AiAssistant $aiAssistant): array
    {
        return $aiAssistant->explainMatch($matchResult);
    }

    public function updateWorkflow(UpdateMatchWorkflowRequest $request, MatchResult $matchResult): MatchResultResource
    {
        $validated = $request->validated();
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

        return new MatchResultResource($matchResult->load('tutor'));
    }
}

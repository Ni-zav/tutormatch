<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
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
}

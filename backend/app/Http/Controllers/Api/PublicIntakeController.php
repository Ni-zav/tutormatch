<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePublicIntakeRequest;
use App\Models\Assignment;
use App\Models\Level;
use App\Models\StudentRequest;
use App\Models\Subject;
use App\Services\AuditLogger;

class PublicIntakeController extends Controller
{
    public function options(): array
    {
        return [
            'data' => [
                'subjects' => Subject::query()->orderBy('name')->get(['id', 'name']),
                'levels' => Level::query()->orderBy('name')->get(['id', 'name']),
            ],
        ];
    }

    public function store(StorePublicIntakeRequest $request, AuditLogger $auditLogger)
    {
        $payload = $request->safe()->except('privacy_acknowledged');
        $studentRequest = StudentRequest::create([
            ...$payload,
            'urgency' => $payload['urgency'] ?? 'normal',
            'status' => 'new',
        ]);
        $studentRequest->load(['subject', 'level']);

        $assignment = Assignment::create([
            'student_request_id' => $studentRequest->id,
            'title' => "{$studentRequest->level->name} {$studentRequest->subject->name} in {$studentRequest->location}",
            'status' => 'open',
            'published_at' => now(),
        ]);

        $auditLogger->record($request, 'public_intake.created', $studentRequest, [
            'assignment_id' => $assignment->id,
            'subject_id' => $studentRequest->subject_id,
            'level_id' => $studentRequest->level_id,
        ]);

        return response()->json([
            'data' => [
                'id' => $studentRequest->id,
                'status' => $studentRequest->status,
                'assignment_id' => $assignment->id,
                'submitted_at' => $studentRequest->created_at,
            ],
        ], 201);
    }
}

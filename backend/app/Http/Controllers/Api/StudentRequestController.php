<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreStudentRequestRequest;
use App\Http\Resources\StudentRequestResource;
use App\Models\Assignment;
use App\Models\StudentRequest;
use App\Services\AuditLogger;

class StudentRequestController extends Controller
{
    public function index()
    {
        $requests = StudentRequest::query()
            ->with(['subject', 'level', 'assignment'])
            ->when(request('status'), fn ($query, $status) => $query->where('status', $status))
            ->when(request('urgency'), fn ($query, $urgency) => $query->where('urgency', $urgency))
            ->latest()
            ->paginate((int) request('per_page', 10));

        return StudentRequestResource::collection($requests);
    }

    public function store(StoreStudentRequestRequest $request, AuditLogger $auditLogger)
    {
        $studentRequest = StudentRequest::create($request->validated());
        $studentRequest->load(['subject', 'level']);

        $assignment = Assignment::create([
            'student_request_id' => $studentRequest->id,
            'title' => "{$studentRequest->level->name} {$studentRequest->subject->name} in {$studentRequest->location}",
            'status' => 'open',
            'published_at' => now(),
        ]);
        $auditLogger->record($request, 'request.created', $studentRequest, [
            'assignment_id' => $assignment->id,
            'subject_id' => $studentRequest->subject_id,
            'level_id' => $studentRequest->level_id,
        ]);

        return (new StudentRequestResource($studentRequest->load('assignment')))->response()->setStatusCode(201);
    }

    public function show(StudentRequest $request)
    {
        return new StudentRequestResource($request->load(['subject', 'level', 'assignment']));
    }
}

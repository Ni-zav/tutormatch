<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicationRequest;
use App\Http\Requests\UpdateApplicationStatusRequest;
use App\Models\Application;
use App\Models\Assignment;
use App\Services\AuditLogger;

class AssignmentApplicationController extends Controller
{
    public function store(StoreApplicationRequest $request, Assignment $assignment, AuditLogger $auditLogger)
    {
        $user = $request->user();
        $tutorId = $user->role === 'tutor'
            ? $user->tutor?->id
            : $request->integer('tutor_id');

        if (! $tutorId) {
            return response()->json(['message' => 'Tutor profile is required to apply.'], 422);
        }

        $application = Application::query()->firstOrNew([
            'assignment_id' => $assignment->id,
            'tutor_id' => $tutorId,
        ]);
        $wasRecentlyCreated = ! $application->exists;
        $application->fill([
            'status' => 'applied',
            'message' => $request->input('message', $application->message),
            'applied_at' => now(),
        ])->save();
        $auditLogger->record($request, 'application.applied', $application, [
            'assignment_id' => $assignment->id,
            'tutor_id' => $tutorId,
            'was_recently_created' => $wasRecentlyCreated,
        ]);

        return response()->json(['data' => $application], $wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(StoreApplicationRequest $request, Assignment $assignment, AuditLogger $auditLogger)
    {
        $user = $request->user();
        $tutorId = $user->role === 'tutor'
            ? $user->tutor?->id
            : $request->integer('tutor_id');

        if (! $tutorId) {
            return response()->json(['message' => 'Tutor profile is required to withdraw.'], 422);
        }

        $application = Application::query()
            ->where('assignment_id', $assignment->id)
            ->where('tutor_id', $tutorId)
            ->firstOrFail();

        $application->update(['status' => 'withdrawn']);
        $auditLogger->record($request, 'application.withdrawn', $application, [
            'assignment_id' => $assignment->id,
            'tutor_id' => $tutorId,
        ]);

        return response()->json(['data' => $application]);
    }

    public function update(UpdateApplicationStatusRequest $request, Application $application, AuditLogger $auditLogger)
    {
        $validated = $request->validated();
        $previousStatus = $application->status;
        $application->update([
            'status' => $validated['status'],
        ]);
        $auditLogger->record($request, 'application.status_updated', $application, [
            'assignment_id' => $application->assignment_id,
            'tutor_id' => $application->tutor_id,
            'previous_status' => $previousStatus,
            'current_status' => $application->status,
        ]);

        return response()->json([
            'data' => [
                'id' => $application->id,
                'tutor_id' => $application->tutor_id,
                'tutor_name' => $application->tutor?->name,
                'status' => $application->status,
                'message' => $application->message,
                'applied_at' => $application->applied_at,
            ],
        ]);
    }
}

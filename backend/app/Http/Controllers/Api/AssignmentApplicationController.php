<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicationRequest;
use App\Models\Application;
use App\Models\Assignment;

class AssignmentApplicationController extends Controller
{
    public function store(StoreApplicationRequest $request, Assignment $assignment)
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

        return response()->json(['data' => $application], $wasRecentlyCreated ? 201 : 200);
    }

    public function destroy(StoreApplicationRequest $request, Assignment $assignment)
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

        return response()->json(['data' => $application]);
    }
}

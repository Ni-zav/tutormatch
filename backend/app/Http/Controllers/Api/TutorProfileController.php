<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateTutorProfileRequest;
use App\Http\Resources\TutorResource;
use App\Services\AuditLogger;
use Illuminate\Http\Request;

class TutorProfileController extends Controller
{
    public function show(Request $request): TutorResource
    {
        $tutor = $request->user()->tutor;

        abort_if(! $tutor, 404, 'Tutor profile not found.');

        return new TutorResource($tutor->load(['tutorSubjects.subject', 'tutorSubjects.level', 'availabilities']));
    }

    public function update(UpdateTutorProfileRequest $request, AuditLogger $auditLogger): TutorResource
    {
        $tutor = $request->user()->tutor;

        abort_if(! $tutor, 404, 'Tutor profile not found.');

        $validated = $request->validated();
        $tutor->update([
            'teaching_mode' => $validated['teaching_mode'],
            'location' => $validated['location'],
            'hourly_rate_min' => $validated['hourly_rate_min'],
            'hourly_rate_max' => $validated['hourly_rate_max'],
            'bio' => $validated['bio'] ?? null,
            'is_active' => $validated['is_active'],
        ]);

        $tutor->availabilities()->delete();
        foreach ($validated['availabilities'] ?? [] as $slot) {
            $tutor->availabilities()->create($slot);
        }

        $auditLogger->record($request, 'tutor.profile_updated', $tutor, [
            'availability_count' => count($validated['availabilities'] ?? []),
            'is_active' => $tutor->is_active,
        ]);

        return new TutorResource($tutor->load(['tutorSubjects.subject', 'tutorSubjects.level', 'availabilities']));
    }
}

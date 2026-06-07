<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TutorResource;
use App\Models\Tutor;

class TutorController extends Controller
{
    public function index()
    {
        $tutors = Tutor::query()
            ->with(['tutorSubjects.subject', 'tutorSubjects.level', 'availabilities'])
            ->when(request('subject_id'), fn ($query, $subjectId) => $query->whereHas('tutorSubjects', fn ($q) => $q->where('subject_id', $subjectId)))
            ->when(request('level_id'), fn ($query, $levelId) => $query->whereHas('tutorSubjects', fn ($q) => $q->whereNull('level_id')->orWhere('level_id', $levelId)))
            ->when(request('location'), fn ($query, $location) => $query->where('location', $location))
            ->when(request('teaching_mode'), fn ($query, $mode) => $query->whereIn('teaching_mode', [$mode, 'hybrid']))
            ->orderByDesc('success_score')
            ->paginate((int) request('per_page', 10));

        return TutorResource::collection($tutors);
    }

    public function show(Tutor $tutor)
    {
        return new TutorResource($tutor->load(['tutorSubjects.subject', 'tutorSubjects.level', 'availabilities']));
    }
}

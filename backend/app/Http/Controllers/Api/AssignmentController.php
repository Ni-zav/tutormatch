<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\AssignmentResource;
use App\Models\Assignment;
use Illuminate\Http\Request;

class AssignmentController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $tutorId = $user->tutor?->id;

        $assignments = Assignment::query()
            ->with(['studentRequest.subject', 'studentRequest.level'])
            ->with([
                'applications' => fn ($query) => $user->role === 'tutor'
                    ? $query->where('tutor_id', $tutorId)
                    : $query->with('tutor')->latest('applied_at'),
            ])
            ->where('status', 'open')
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->paginate((int) $request->integer('per_page', 10));

        return AssignmentResource::collection($assignments);
    }
}

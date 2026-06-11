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
            ->with(['applications' => fn ($query) => $tutorId ? $query->where('tutor_id', $tutorId) : $query->whereRaw('1 = 0')])
            ->where('status', 'open')
            ->whereNotNull('published_at')
            ->latest('published_at')
            ->paginate((int) $request->integer('per_page', 10));

        return AssignmentResource::collection($assignments);
    }
}

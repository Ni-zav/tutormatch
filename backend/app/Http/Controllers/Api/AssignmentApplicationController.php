<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreApplicationRequest;
use App\Models\Application;
use App\Models\Assignment;

class AssignmentApplicationController extends Controller
{
    public function __invoke(StoreApplicationRequest $request, Assignment $assignment)
    {
        $application = Application::query()->firstOrCreate(
            [
                'assignment_id' => $assignment->id,
                'tutor_id' => $request->integer('tutor_id'),
            ],
            [
                'status' => 'applied',
                'message' => $request->input('message'),
                'applied_at' => now(),
            ]
        );

        return response()->json(['data' => $application], $application->wasRecentlyCreated ? 201 : 200);
    }
}

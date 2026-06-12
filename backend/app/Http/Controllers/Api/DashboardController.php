<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use App\Models\MatchResult;
use App\Models\StudentRequest;
use App\Models\Tutor;

class DashboardController extends Controller
{
    public function summary(): array
    {
        return [
            'requests' => [
                'total' => StudentRequest::count(),
                'new' => StudentRequest::where('status', 'new')->count(),
                'urgent' => StudentRequest::where('urgency', 'urgent')->count(),
                'no_matches' => StudentRequest::where('status', 'no_matches')->count(),
                'needs_follow_up' => StudentRequest::where('status', 'needs_follow_up')->count(),
            ],
            'tutors' => ['total' => Tutor::count()],
            'applications' => [
                'total' => Application::count(),
                'pending' => Application::where('status', 'applied')->count(),
            ],
            'matches' => [
                'generated' => MatchResult::count(),
                'average_score' => round((float) MatchResult::avg('total_score'), 1),
                'shortlisted' => MatchResult::where('status', 'shortlisted')->count(),
                'contacted' => MatchResult::where('outreach_status', 'contacted')->count(),
            ],
        ];
    }
}

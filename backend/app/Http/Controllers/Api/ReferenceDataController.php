<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Level;
use App\Models\Subject;

class ReferenceDataController extends Controller
{
    public function subjects(): array
    {
        return [
            'data' => Subject::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }

    public function levels(): array
    {
        return [
            'data' => Level::query()
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = ['database' => 'ok'];
        $status = 'ok';
        $httpStatus = 200;

        try {
            DB::select('select 1');
        } catch (Throwable) {
            $checks['database'] = 'error';
            $status = 'degraded';
            $httpStatus = 503;
        }

        return response()->json([
            'status' => $status,
            'service' => 'TutorMatch Ops API',
            'checks' => $checks,
        ], $httpStatus);
    }
}

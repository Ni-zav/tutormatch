<?php

use App\Http\Controllers\Api\AssignmentApplicationController;
use App\Http\Controllers\Api\AssignmentController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\MessageDraftController;
use App\Http\Controllers\Api\ReferenceDataController;
use App\Http\Controllers\Api\StudentRequestController;
use App\Http\Controllers\Api\TutorProfileController;
use App\Http\Controllers\Api\TutorController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::post('/auth/login', [AuthController::class, 'login'])->middleware('throttle:10,1');

Route::middleware('api.token:admin,coordinator,tutor')->group(function (): void {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);
});

Route::middleware('api.token:admin,coordinator,tutor')->group(function (): void {
    Route::get('/assignments', [AssignmentController::class, 'index']);
});

Route::middleware('api.token:tutor')->group(function (): void {
    Route::get('/tutor/profile', [TutorProfileController::class, 'show']);
    Route::patch('/tutor/profile', [TutorProfileController::class, 'update']);
});

Route::middleware('api.token:admin,coordinator')->group(function (): void {
    Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
    Route::get('/subjects', [ReferenceDataController::class, 'subjects']);
    Route::get('/levels', [ReferenceDataController::class, 'levels']);
    Route::apiResource('requests', StudentRequestController::class)->only(['index', 'store', 'show']);
    Route::get('/requests/{request}/matches', [MatchController::class, 'index']);
    Route::post('/requests/{request}/generate-matches', [MatchController::class, 'generate'])->middleware('throttle:20,1');
    Route::apiResource('tutors', TutorController::class)->only(['index', 'show']);
    Route::post('/matches/{matchResult}/explain', [MatchController::class, 'explain'])->middleware('throttle:30,1');
    Route::patch('/matches/{matchResult}/workflow', [MatchController::class, 'updateWorkflow']);
    Route::post('/message-drafts', MessageDraftController::class)->middleware('throttle:30,1');
});

Route::post('/assignments/{assignment}/applications', [AssignmentApplicationController::class, 'store'])
    ->middleware('api.token:admin,coordinator,tutor');
Route::delete('/assignments/{assignment}/applications', [AssignmentApplicationController::class, 'destroy'])
    ->middleware('api.token:admin,coordinator,tutor');

<?php

use App\Http\Controllers\Api\AssignmentApplicationController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\MatchController;
use App\Http\Controllers\Api\MessageDraftController;
use App\Http\Controllers\Api\StudentRequestController;
use App\Http\Controllers\Api\TutorController;
use Illuminate\Support\Facades\Route;

Route::get('/health', HealthController::class);
Route::get('/dashboard/summary', [DashboardController::class, 'summary']);
Route::apiResource('requests', StudentRequestController::class)->only(['index', 'store', 'show']);
Route::get('/requests/{request}/matches', [MatchController::class, 'index']);
Route::post('/requests/{request}/generate-matches', [MatchController::class, 'generate']);
Route::apiResource('tutors', TutorController::class)->only(['index', 'show']);
Route::post('/assignments/{assignment}/applications', AssignmentApplicationController::class);
Route::post('/matches/{matchResult}/explain', [MatchController::class, 'explain']);
Route::post('/message-drafts', MessageDraftController::class);

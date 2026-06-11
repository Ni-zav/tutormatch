<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $studentRequest = $this->relationLoaded('studentRequest') ? $this->studentRequest : null;
        $application = $this->relationLoaded('applications') ? $this->applications->first() : null;

        return [
            'id' => $this->id,
            'title' => $this->title,
            'status' => $this->status,
            'published_at' => $this->published_at,
            'application_status' => $application?->status,
            'application_id' => $application?->id,
            'applications' => $request->user()?->role === 'tutor' ? [] : $this->whenLoaded(
                'applications',
                fn () => $this->applications->map(fn ($application) => [
                    'id' => $application->id,
                    'tutor_id' => $application->tutor_id,
                    'tutor_name' => $application->tutor?->name,
                    'status' => $application->status,
                    'message' => $application->message,
                    'applied_at' => $application->applied_at,
                ])->values()
            ),
            'request' => $studentRequest ? [
                'id' => $studentRequest->id,
                'subject' => $studentRequest->subject?->name,
                'level' => $studentRequest->level?->name,
                'location' => $studentRequest->location,
                'teaching_mode' => $studentRequest->teaching_mode,
                'budget_min' => $studentRequest->budget_min,
                'budget_max' => $studentRequest->budget_max,
                'schedule' => trim(($studentRequest->requested_day_of_week ?? '').' '.($studentRequest->requested_time_block ?? '')),
                'notes' => $studentRequest->notes,
            ] : null,
        ];
    }
}

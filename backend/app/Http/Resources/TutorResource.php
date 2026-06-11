<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TutorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'tutor_type' => $this->tutor_type,
            'teaching_mode' => $this->teaching_mode,
            'location' => $this->location,
            'hourly_rate_min' => $this->hourly_rate_min,
            'hourly_rate_max' => $this->hourly_rate_max,
            'years_experience' => $this->years_experience,
            'rating' => $this->rating,
            'acceptance_rate' => $this->acceptance_rate,
            'success_score' => $this->success_score,
            'is_active' => $this->is_active,
            'bio' => $this->bio,
            'subjects' => $this->whenLoaded('tutorSubjects', fn () => $this->tutorSubjects->map(fn ($ability) => [
                'subject' => $ability->subject?->name,
                'level' => $ability->level?->name,
                'proficiency' => $ability->proficiency,
            ])),
            'availabilities' => $this->whenLoaded('availabilities', fn () => $this->availabilities->map(fn ($slot) => [
                'day_of_week' => $slot->day_of_week,
                'time_block' => $slot->time_block,
            ])),
        ];
    }
}

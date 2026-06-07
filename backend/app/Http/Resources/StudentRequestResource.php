<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StudentRequestResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_name' => $this->student_name,
            'parent_name' => $this->parent_name,
            'subject' => $this->whenLoaded('subject', fn () => ['id' => $this->subject->id, 'name' => $this->subject->name]),
            'level' => $this->whenLoaded('level', fn () => ['id' => $this->level->id, 'name' => $this->level->name]),
            'location' => $this->location,
            'teaching_mode' => $this->teaching_mode,
            'budget_min' => $this->budget_min,
            'budget_max' => $this->budget_max,
            'preferred_tutor_type' => $this->preferred_tutor_type,
            'requested_day_of_week' => $this->requested_day_of_week,
            'requested_time_block' => $this->requested_time_block,
            'urgency' => $this->urgency,
            'status' => $this->status,
            'schedule_notes' => $this->schedule_notes,
            'notes' => $this->notes,
            'assignment' => $this->whenLoaded('assignment', fn () => $this->assignment),
            'created_at' => $this->created_at,
        ];
    }
}

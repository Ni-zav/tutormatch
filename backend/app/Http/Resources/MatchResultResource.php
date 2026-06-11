<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'student_request_id' => $this->student_request_id,
            'tutor' => new TutorResource($this->whenLoaded('tutor')),
            'total_score' => $this->total_score,
            'score_breakdown' => $this->score_breakdown,
            'deterministic_explanation' => $this->deterministic_explanation,
            'status' => $this->status,
            'outreach_status' => $this->outreach_status,
            'coordinator_notes' => $this->coordinator_notes,
            'status_updated_at' => $this->status_updated_at,
            'generated_at' => $this->generated_at,
        ];
    }
}

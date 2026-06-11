<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MatchResult extends Model
{
    protected $fillable = [
        'student_request_id',
        'tutor_id',
        'total_score',
        'score_breakdown',
        'deterministic_explanation',
        'generated_at',
        'status',
        'outreach_status',
        'coordinator_notes',
        'status_updated_at',
    ];

    protected function casts(): array
    {
        return [
            'score_breakdown' => 'array',
            'generated_at' => 'datetime',
            'status_updated_at' => 'datetime',
        ];
    }

    public function studentRequest(): BelongsTo
    {
        return $this->belongsTo(StudentRequest::class);
    }

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class);
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class StudentRequest extends Model
{
    protected $fillable = [
        'student_name',
        'parent_name',
        'subject_id',
        'level_id',
        'location',
        'teaching_mode',
        'budget_min',
        'budget_max',
        'preferred_tutor_type',
        'requested_day_of_week',
        'requested_time_block',
        'urgency',
        'status',
        'schedule_notes',
        'notes',
    ];

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class);
    }

    public function assignment(): HasOne
    {
        return $this->hasOne(Assignment::class);
    }

    public function matchResults(): HasMany
    {
        return $this->hasMany(MatchResult::class);
    }
}

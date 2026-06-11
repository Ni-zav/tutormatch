<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Tutor extends Model
{
    protected $fillable = [
        'name',
        'user_id',
        'tutor_type',
        'teaching_mode',
        'location',
        'hourly_rate_min',
        'hourly_rate_max',
        'years_experience',
        'rating',
        'acceptance_rate',
        'success_score',
        'bio',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rating' => 'float',
            'acceptance_rate' => 'float',
            'success_score' => 'float',
            'is_active' => 'boolean',
        ];
    }

    public function tutorSubjects(): HasMany
    {
        return $this->hasMany(TutorSubject::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function availabilities(): HasMany
    {
        return $this->hasMany(TutorAvailability::class);
    }
}

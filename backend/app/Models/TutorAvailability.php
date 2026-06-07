<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TutorAvailability extends Model
{
    protected $fillable = ['tutor_id', 'day_of_week', 'time_block'];

    public function tutor(): BelongsTo
    {
        return $this->belongsTo(Tutor::class);
    }
}

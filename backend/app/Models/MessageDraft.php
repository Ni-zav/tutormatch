<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MessageDraft extends Model
{
    protected $fillable = [
        'student_request_id',
        'tutor_id',
        'match_result_id',
        'audience',
        'channel',
        'body',
        'generated_by',
        'prompt_version',
        'fallback_used',
        'generation_metadata',
    ];

    protected function casts(): array
    {
        return [
            'fallback_used' => 'boolean',
            'generation_metadata' => 'array',
        ];
    }
}

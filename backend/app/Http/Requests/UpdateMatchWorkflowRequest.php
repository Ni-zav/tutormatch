<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateMatchWorkflowRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['recommended', 'shortlisted', 'accepted', 'rejected', 'confirmed', 'needs_follow_up', 'closed'])],
            'outreach_status' => ['sometimes', Rule::in(['not_contacted', 'drafted', 'contacted', 'responded', 'no_response'])],
            'coordinator_notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

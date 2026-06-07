<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMessageDraftRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'student_request_id' => ['required', 'exists:student_requests,id'],
            'tutor_id' => ['nullable', 'exists:tutors,id'],
            'match_result_id' => ['nullable', 'exists:match_results,id'],
            'audience' => ['required', 'in:client,tutor,internal'],
            'channel' => ['nullable', 'in:whatsapp,email,internal'],
        ];
    }
}

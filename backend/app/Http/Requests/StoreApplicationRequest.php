<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreApplicationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'tutor_id' => ['required', 'exists:tutors,id'],
            'message' => ['nullable', 'string', 'max:1000'],
        ];
    }
}

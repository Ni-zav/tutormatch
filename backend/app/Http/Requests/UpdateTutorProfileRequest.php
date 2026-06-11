<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateTutorProfileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'teaching_mode' => ['required', 'in:home,online,hybrid'],
            'location' => ['required', 'string', 'max:80'],
            'hourly_rate_min' => ['required', 'integer', 'min:0'],
            'hourly_rate_max' => ['required', 'integer', 'gte:hourly_rate_min'],
            'bio' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['required', 'boolean'],
            'availabilities' => ['array', 'max:12'],
            'availabilities.*.day_of_week' => ['required', 'string', 'max:20'],
            'availabilities.*.time_block' => ['required', 'string', 'max:40'],
        ];
    }
}

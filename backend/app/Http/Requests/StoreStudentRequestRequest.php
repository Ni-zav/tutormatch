<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreStudentRequestRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'student_name' => ['required', 'string', 'max:120'],
            'parent_name' => ['nullable', 'string', 'max:120'],
            'subject_id' => ['required', 'exists:subjects,id'],
            'level_id' => ['required', 'exists:levels,id'],
            'location' => ['required', 'string', 'max:80'],
            'teaching_mode' => ['required', 'in:home,online,hybrid'],
            'budget_min' => ['nullable', 'integer', 'min:0'],
            'budget_max' => ['required', 'integer', 'min:1'],
            'preferred_tutor_type' => ['nullable', 'in:part_time,full_time,ex_moe,current_moe'],
            'requested_day_of_week' => ['nullable', 'string', 'max:20'],
            'requested_time_block' => ['nullable', 'string', 'max:40'],
            'urgency' => ['required', 'in:low,normal,urgent'],
            'status' => ['nullable', 'in:new,matching,shortlisted,confirmed,rejected,closed,needs_follow_up'],
            'schedule_notes' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ];
    }
}

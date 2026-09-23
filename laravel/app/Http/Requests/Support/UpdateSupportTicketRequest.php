<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['support', 'admin'], true);
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['open', 'in_progress', 'waiting_customer', 'waiting_internal', 'resolved', 'closed', 'cancelled'])],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'category' => ['required', 'string', 'max:60'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'escalation_level' => ['required', 'integer', 'min:0', 'max:5'],
        ];
    }
}

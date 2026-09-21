<?php

namespace App\Http\Requests\Commercial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommercialActivityRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['commercial', 'admin'], true);
    }

    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['note', 'call', 'email', 'meeting', 'whatsapp', 'proposal', 'task'])],
            'subject' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'outcome' => ['nullable', 'string', 'max:255'],
            'happened_at' => ['nullable', 'date'],
            'next_follow_up_at' => ['nullable', 'date'],
        ];
    }
}

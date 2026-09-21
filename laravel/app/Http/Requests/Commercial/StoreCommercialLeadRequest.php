<?php

namespace App\Http\Requests\Commercial;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommercialLeadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['commercial', 'admin'], true);
    }

    public function rules(): array
    {
        return [
            'user_id' => ['nullable', 'exists:users,id'],
            'shop_id' => ['nullable', 'exists:shops,id'],
            'business_request_id' => ['nullable', 'exists:business_requests,id'],
            'devis_id' => ['nullable', 'exists:devis,id'],
            'appel_offre_id' => ['nullable', 'exists:appel_offres,id'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'source' => ['required', 'string', 'max:50'],
            'lead_type' => ['required', Rule::in(['buyer', 'vendor', 'business', 'partner'])],
            'status' => ['required', Rule::in(['new', 'qualified', 'proposal', 'negotiation', 'won', 'lost', 'cancelled'])],
            'company_name' => ['nullable', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'sector' => ['nullable', 'string', 'max:120'],
            'title' => ['required', 'string', 'max:255'],
            'need_summary' => ['nullable', 'string', 'max:10000'],
            'estimated_value' => ['nullable', 'numeric', 'min:0'],
            'probability' => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_at' => ['nullable', 'date'],
            'next_action_at' => ['nullable', 'date'],
            'lost_reason' => ['nullable', 'string', 'max:2000'],
        ];
    }
}

<?php

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSupportTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return in_array($this->user()?->role, ['support', 'admin'], true);
    }

    public function rules(): array
    {
        return [
            'requester_user_id' => ['nullable', 'exists:users,id'],
            'requester_name' => ['nullable', 'string', 'max:255'],
            'requester_email' => ['nullable', 'email', 'max:255'],
            'requester_phone' => ['nullable', 'string', 'max:40'],
            'channel' => ['required', Rule::in(['internal', 'phone', 'email', 'whatsapp', 'web', 'social'])],
            'category' => ['required', 'string', 'max:60'],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'team' => ['required', 'string', 'max:40'],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:10000'],
            'assigned_to' => ['nullable', 'exists:users,id'],
            'order_id' => ['nullable', 'exists:orders,id'],
            'shop_id' => ['nullable', 'exists:shops,id'],
            'payment_id' => ['nullable', 'exists:payments,id'],
            'shipment_id' => ['nullable', 'exists:shipments,id'],
            'return_id' => ['nullable', 'exists:returns,id'],
            'dispute_id' => ['nullable', 'exists:disputes,id'],
            'delivery_incident_id' => ['nullable', 'exists:delivery_incidents,id'],
            'submission_id' => ['nullable', 'exists:submissions,id'],
        ];
    }
}

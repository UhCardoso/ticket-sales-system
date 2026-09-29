<?php

namespace App\Http\Requests;

use App\Enums\GatewayNotificationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreGatewayNotificationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'external_id' => ['required', 'string', 'max:255'],
            'order_id' => ['required', 'integer', 'exists:orders,id'],
            'type' => ['required', Rule::enum(GatewayNotificationType::class)],
            'occurred_at' => ['required', 'date'],
        ];
    }

    public function messages(): array
    {
        return [
            'external_id.required' => 'O identificador próprio do aviso é obrigatório.',
            'occurred_at.required' => 'A data/hora em que o aviso aconteceu é obrigatória.',
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'idempotency_key' => $this->header('Idempotency-Key'),
            'buyer_document' => preg_replace('/\D/', '', (string) $this->input('buyer_document')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'idempotency_key' => ['required', 'uuid'],
            'ticket_batch_id' => ['required', 'integer', 'exists:ticket_batches,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:'.config('orders.max_quantity_per_order')],
            'buyer_name' => ['required', 'string', 'max:255'],
            'buyer_email' => ['required', 'email', 'max:255'],
            'buyer_document' => ['required', 'digits:11'],
        ];
    }

    public function messages(): array
    {
        return [
            'idempotency_key.required' => 'O header Idempotency-Key é obrigatório.',
            'idempotency_key.uuid' => 'O header Idempotency-Key deve ser um UUID.',
        ];
    }
}

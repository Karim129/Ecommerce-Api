<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'delivery_address' => ['required', 'array'],
            'delivery_address.city' => ['required', 'string', 'max:255'],
            'delivery_address.address' => ['required', 'string', 'max:255'],
            'delivery_address.building_number' => ['required', 'string', 'max:50'],
            'payment_method' => ['required', 'string', 'in:stripe,paypal,cod'],
            'payment_token' => ['required_if:payment_method,stripe,paypal'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    public function messages()
    {
        return [
            'payment_token.required_if' => __('validation.custom.payment_token.required_for_online'),
            'delivery_address.required' => __('validation.custom.delivery_address.required'),
        ];
    }
}

<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateOrderStatusRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:pending,shipped,delivered'],
            'payment_status' => ['required', 'string', 'in:paid,not_paid'],
        ];
    }
}

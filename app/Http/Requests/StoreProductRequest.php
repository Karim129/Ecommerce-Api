<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'array'],
            'name.en' => ['required', 'string', 'max:255'],
            'name.ar' => ['required', 'string', 'max:255'],
            'description' => ['required', 'array'],
            'description.en' => ['required', 'string', 'max:1000'],
            'description.ar' => ['required', 'string', 'max:1000'],
            'price' => ['required', 'numeric', 'min:0', 'regex:/^\d+(\.\d{1,2})?$/'],
            'discounted_price' => ['nullable', 'numeric', 'min:0', 'lt:price', 'regex:/^\d+(\.\d{1,2})?$/'],
            'quantity' => ['required', 'integer', 'min:0'],
            'images' => ['required', 'array', 'min:1', 'max:5'],
            'images.*' => ['required', 'image', 'mimes:jpeg,png,jpg,gif', 'max:2048'],
            'categories' => ['required', 'array', 'min:1'],
            'categories.*' => ['required', 'exists:categories,id'],
            'status' => ['required', 'string', 'in:active,inactive'],
        ];
    }

    public function messages()
    {
        return [
            'price.regex' => 'The price must have at most 2 decimal places.',
            'discounted_price.regex' => 'The discounted price must have at most 2 decimal places.',
            'images.max' => 'You may not upload more than 5 images.',
        ];
    }
}

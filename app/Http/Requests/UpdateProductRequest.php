<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProductRequest extends FormRequest
{
    protected function prepareForValidation()
    {
        if ($this->has('categories')) {

            $this->merge([
                'categories' => is_string($this->categories) ? json_decode($this->categories, true) : $this->categories,
            ]);
        }
    }

    public function authorize(): bool
    {
        return $this->user()->hasRole('admin');
    }

    public function rules(): array
    {
        return [
            'name_en' => 'sometimes|required|string|max:255',
            'name_ar' => 'sometimes|required|string|max:255',
            'description_en' => 'sometimes|required|string',
            'description_ar' => 'sometimes|required|string',
            'images.*' => 'sometimes|required|image|max:2048',
            'images' => 'sometimes|required|array|min:1',
            'price' => 'sometimes|required|numeric|min:0',
            'discounted_price' => 'sometimes|nullable|numeric|min:0|lt:price',
            'quantity' => 'required|integer|min:0',
            'status' => 'sometimes|required|string|in:active,not active',
            'categories' => 'sometimes|required|array|min:1',
            'categories.*' => 'sometimes|required|integer|exists:categories,id',
        ];
    }
}

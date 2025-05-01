<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Foundation\Http\FormRequest;

class UpdateCartRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity' => [
                'required',
                'integer',
                'min:1',
                function ($attribute, $value, $fail) {
                    $product = Product::find($this->route('cart')->product_id);
                    if ($product && $value > $product->quantity) {
                        $fail(__('validation.custom.quantity.exceeds_stock'));
                    }
                },
            ],
        ];
    }
}

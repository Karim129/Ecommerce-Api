<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CartResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $subtotal = $this->quantity * ($this->product->discounted_price ?? $this->product->price);

        return [
            'id' => $this->id,
            'quantity' => (int) $this->quantity,
            'product' => new ProductResource($this->whenLoaded('product')),
            'subtotal' => $this->formatPrice($subtotal),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}

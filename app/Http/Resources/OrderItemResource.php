<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class OrderItemResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'quantity' => (int) $this->quantity,
            'price' => $this->formatPrice($this->price),
            'total' => $this->formatPrice($this->quantity * $this->price),
            'product' => new ProductResource($this->whenLoaded('product')),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}

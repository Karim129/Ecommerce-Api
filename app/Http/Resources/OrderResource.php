<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class OrderResource extends BaseResource
{
    public function toArray(Request $request): array
    {

        return [
            'id' => $this->id,
            'order_number' => $this->order_number,
            'status' => $this->status,
            'city' => $this->city,
            'address' => $this->address,
            'building_number' => $this->building_number,

            'payment_method' => $this->payment_method,
            'payment_status' => $this->payment_status,
            'total_amount' => $this->formatPrice($this->total_amount),
            'notes' => $this->notes,
            'user' => new UserResource($this->whenLoaded('user')),
            'items' => OrderItemResource::collection($this->whenLoaded('items')),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}

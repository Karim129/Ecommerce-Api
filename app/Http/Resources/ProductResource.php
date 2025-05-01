<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class ProductResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        return [
            'id' => $this->id,
            'name' => $this->name[$locale] ?? $name['en'] ?? '',

            'description' => $this->description[$locale] ?? $description['en'] ?? '',
            'images' => array_map(function ($image) {
                return $this->formatImageUrl($image);
            }, is_array($this->images) ? $this->images : json_decode($this->images, true) ?? []),
            'price' => $this->formatPrice($this->price),
            'discounted_price' => $this->formatPrice($this->discounted_price),
            'quantity' => (int) $this->quantity,
            'status' => $this->status,
            'categories' => CategoryResource::collection($this->whenLoaded('categories')),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}

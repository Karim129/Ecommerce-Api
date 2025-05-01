<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;

class CategoryResource extends BaseResource
{
    public function toArray(Request $request): array
    {
        $locale = app()->getLocale();

        // $name = json_decode($this->name, true);
        // $description = json_decode($this->description, true);
        // dd($this->name[$locale]);
        return [
            'id' => $this->id,
            'name' => $this->name[$locale] ?? $name['en'] ?? '',
            // 'translations' => [
            //     'name' => $name,
            //     'description' => $description
            // ],
            'description' => $this->description[$locale] ?? $description['en'] ?? '',
            'image' => $this->formatImageUrl($this->image),
            'status' => $this->status,
            'products_count' => $this->whenCounted('products'),
            // 'products' => ProductResource::collection($this->whenLoaded('products')),
            'created_at' => $this->formatDate($this->created_at),
            'updated_at' => $this->formatDate($this->updated_at),
        ];
    }
}

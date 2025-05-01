<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class BaseResource extends JsonResource
{
    public static function collection($resource)
    {
        return tap(parent::collection($resource), function ($collection) {
            $collection->additional([
                'status' => 'success',
                'message' => null,
            ]);
        });
    }

    public function additional($data)
    {
        return parent::additional($data + [
            'status' => 'success',
            'message' => null,
        ]);
    }

    protected function formatDate($date)
    {
        return $date ? $date->toIso8601String() : null;
    }

    protected function formatPrice($price)
    {
        return $price ? number_format((float) $price, 2, '.', '') : null;
    }

    protected function formatImageUrl($path)
    {
        return $path ? asset('storage/'.trim($path, '"')) : null;
    }

    public function withResponse($request, $response)
    {
        $response->header('Accept-Language', app()->getLocale());
    }
}

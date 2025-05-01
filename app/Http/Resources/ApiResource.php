<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ApiResource extends JsonResource
{
    public function withResponse($request, $response)
    {
        $response->header('Content-Type', 'application/json');
    }

    protected function withStatus($data)
    {
        return array_merge($data, [
            'status' => 'success',
        ]);
    }

    public static function collection($resource)
    {
        return tap(parent::collection($resource), function ($collection) {
            $collection->additional(['status' => 'success']);
        });
    }
}

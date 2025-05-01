<?php

namespace App\Repositories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProductRepository extends BaseRepository
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    public function getActiveProducts(int $perPage = 15, array $relations = ['categories']): LengthAwarePaginator
    {
        return $this->model
            ->with($relations)
            ->where('status', 'active')
            ->where('quantity', '>', 0)
            ->latest()
            ->paginate($perPage);
    }

    public function findByCategory($categoryId, int $perPage = 15, array $relations = ['categories']): LengthAwarePaginator
    {
        return $this->model
            ->with($relations)
            ->whereHas('categories', function ($query) use ($categoryId) {
                $query->where('categories.id', $categoryId)
                    ->where('categories.status', 'active');
            })
            ->where('status', 'active')
            ->where('quantity', '>', 0)
            ->latest()
            ->paginate($perPage);
    }

    public function searchProducts(string $term, int $perPage = 15, array $relations = ['categories']): LengthAwarePaginator
    {
        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');

        return $this->model
            ->with($relations)
            ->where(function ($query) use ($term, $locale, $fallbackLocale) {
                $query->where(DB::raw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.".$locale."')))"), 'like', '%'.strtolower($term).'%')
                    ->orWhere(DB::raw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.".$fallbackLocale."')))"), 'like', '%'.strtolower($term).'%')
                    ->orWhere(DB::raw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(description, '$.".$locale."')))"), 'like', '%'.strtolower($term).'%')
                    ->orWhere(DB::raw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(description, '$.".$fallbackLocale."')))"), 'like', '%'.strtolower($term).'%');
            })
            ->where('status', 'active')
            ->where('quantity', '>', 0)
            ->latest()
            ->paginate($perPage);
    }

    public function getRelatedProducts(Product $product, $limit = 4): Collection
    {
        return $this->model
            ->with('categories')
            ->whereHas('categories', function ($query) use ($product) {
                $query->whereIn('categories.id', $product->categories->pluck('id'))
                    ->where('categories.status', 'active');
            })
            ->where('id', '!=', $product->id)
            ->where('status', 'active')
            ->where('quantity', '>', 0)
            ->inRandomOrder()
            ->take($limit)
            ->get();
    }

    public function updateStock(Product $product, int $quantity): bool
    {
        return DB::transaction(function () use ($product, $quantity) {
            $updatedQuantity = $product->quantity - $quantity;
            if ($updatedQuantity < 0) {
                throw new \Exception(__('api.products.insufficient_stock'));
            }

            return $product->update([
                'quantity' => $updatedQuantity,
                'status' => $updatedQuantity > 0 ? $product->status : 'inactive',
            ]);
        });
    }
}

<?php

namespace App\Repositories;

use App\Models\Category;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class CategoryRepository extends BaseRepository
{
    public function __construct(Category $model)
    {
        parent::__construct($model);
    }

    public function getActiveCategories(int $perPage = 15): LengthAwarePaginator
    {
        return $this->model
            ->where('status', 'active')
            ->withCount(['products' => function ($query) {
                $query->where('status', 'active')
                    ->where('quantity', '>', 0);
            }])
            ->orderBy('name->en')
            ->paginate($perPage);
    }

    public function searchCategories(string $term, int $perPage = 15): LengthAwarePaginator
    {
        $locale = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');

        return $this->model
            ->where(function ($query) use ($term, $locale, $fallbackLocale) {
                $query->where(DB::raw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.".$locale."')))"), 'like', '%'.strtolower($term).'%')
                    ->orWhere(DB::raw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(name, '$.".$fallbackLocale."')))"), 'like', '%'.strtolower($term).'%')
                    ->orWhere(DB::raw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(description, '$.".$locale."')))"), 'like', '%'.strtolower($term).'%')
                    ->orWhere(DB::raw("LOWER(JSON_UNQUOTE(JSON_EXTRACT(description, '$.".$fallbackLocale."')))"), 'like', '%'.strtolower($term).'%');
            })
            ->where('status', 'active')
            ->withCount(['products' => function ($query) {
                $query->where('status', 'active')
                    ->where('quantity', '>', 0);
            }])
            ->orderBy('name->en')
            ->paginate($perPage);
    }

    public function getWithActiveProducts(int $id): ?Category
    {
        return $this->model
            ->where('id', $id)
            ->where('status', 'active')
            ->with(['products' => function ($query) {
                $query->where('status', 'active')
                    ->where('quantity', '>', 0)
                    ->latest();
            }])
            ->withCount(['products' => function ($query) {
                $query->where('status', 'active')
                    ->where('quantity', '>', 0);
            }])
            ->firstOrFail();
    }
}

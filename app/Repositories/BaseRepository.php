<?php

namespace App\Repositories;

use App\Traits\ApiResponse;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

abstract class BaseRepository
{
    use ApiResponse;

    protected Model $model;

    public function __construct(Model $model)
    {
        $this->model = $model;
    }

    public function all(array $columns = ['*'], array $relations = []): Collection
    {
        return $this->model->with($relations)->get($columns);
    }

    public function paginate(int $perPage = 10, array $columns = ['*'], array $relations = []): LengthAwarePaginator
    {
        return $this->model->with($relations)->paginate($perPage, $columns);
    }

    public function getById(int $id, array $columns = ['*'], array $relations = [], array $appends = []): ?Model
    {
        return $this->model->select($columns)->with($relations)->findOrFail($id)->append($appends);
    }

    public function create(array $data): Model
    {
        $model = $this->model->create($data);

        return $model->fresh();
    }

    public function update(int $id, array $data): Model
    {
        $model = $this->getById($id);
        $model->update($data);

        return $model->fresh();
    }

    public function delete(int $id): bool
    {
        return $this->getById($id)->delete();
    }

    public function with(array $relations): Builder
    {
        return $this->model->with($relations);
    }
}

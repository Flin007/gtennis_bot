<?php

namespace App\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use \Illuminate\Database\Eloquent\Builder;

abstract class ModelRepository
{
    /**
     * Create an query builder object for model.
     * The method can only be overridden if need a special
     * logic when instantiating the model for queries to the database.
     *
     * @return Builder построитель запросов
     */
    public function createQueryBuilder()
    {
        /** @var class-string<Model>&Model $className */
        $className = $this->getClassName();

        return $className::query();
    }


    /**
     * Returns the class name of the object managed by the repository.
     *
     * @return class-string<Model>
     */

    abstract public function getClassName(): string;

    /**
     * Finds objects by a set of criteria.
     *
     * Optionally sorting and limiting details can be passed. An implementation may throw
     * an UnexpectedValueException if certain values of the sorting or limiting details are
     * not supported.
     *
     * @param array<string, mixed>|Closure|null $criteria
     * @param array<string, string> $orderBy
     *
     * @psalm-param array<string, 'asc'|'desc'|'ASC'|'DESC'> $orderBy
     *
     * @return array<int, T>|Collection the objects
     * @psalm-return T[]
     */
    public function findBy(
        $criteria = null,
        ?array $orderBy = null,
        ?int $limit = null,
        ?int $offset = null
    ): Collection {
        $builder = $this->createQueryBuilder();

        if (null !== $criteria) {
            $builder = $this->applyFilter($builder, $criteria);
        }

        foreach ((array)$orderBy as $key => $direction) {
            $builder->orderBy($key, $direction);
        }

        if (null !== $limit) {
            $builder->take($limit);
        }

        if (null !== $offset) {
            $builder->skip($offset);
        }

        return $builder->get();
    }

    /**
     * Разбираем специфичные для модели фильтры,
     * конвертируя их в запрос к БД.
     * Переопределяем метод только если есть потребность в сложных фильтрах,
     * которые невозможно прокинуть через обычное использование.
     *
     * @see \Illuminate\Database\Query\Builder::where
     *
     * @param Builder $builder
     * @param array<string, mixed>|Closure $criteria the criteria
     *
     * @return Builder
     */
    public function applyFilter(Builder $builder, $criteria): Builder
    {
        return $builder->where($criteria);
    }


}

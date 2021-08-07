<?php

namespace Tarre\RedisScoutEngine\Engines;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Redis;


class RedisScoutEngine extends Engine
{
    /**
     * @param Collection $models
     */
    public function update($models)
    {
        $this->pipelineModels($models, function (string $modelKey, Model $model, Redis $redis) {
            /*
             * convert "toSearchableArray" to a searchable string for strpos search
             */
            $searchableString = array_values($searchable = $model->toSearchableArray());
            $searchableString = implode(' ', $searchableString);
            $scoutKeyName = $this->getScoutKeyNameWithoutTable($model);
            /*
             * save data
             */
            $payload = json_encode([
                'id' => $scoutKey = $model->getScoutKey(),
                'meta' => $model->scoutMetadata(),
                'model' => array_merge($searchable, [$scoutKeyName => $scoutKey]),
                'searchable' => $searchableString,
            ]);
            /*
             * Save
             */
            $redis->hset($modelKey, $scoutKey, $payload);
        });
    }

    public function delete($models)
    {
        $this->pipelineModels($models, function (string $modelKey, Model $model, Redis $redis) {
            $redis->hDel($modelKey, $model->getScoutKey());
        });
    }

    public function search(Builder $builder)
    {
        $limit = $builder->limit ?: $builder->model->getPerPage();

        return $this->paginate($builder, $limit, 1);
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        $skip = $perPage * ($page - 1);
        $take = $perPage;
        $fqdn = $this->getClassSearchableFqdn($builder->model);

        $results = $this
            ->rss
            ->search(
                $fqdn,
                $builder->query,
                $builder->wheres,
                $builder->whereIns,
                $builder->orders,
                $skip,
                $take,
                $count
            );

        return [
            'results' => $results,
            'count' => $count,
            'key' => $this->getScoutKeyNameWithoutTable($builder->model)
        ];
    }

    public function mapIds($results)
    {
        return $results['results']->pluck($results['key'])->values();
    }

    /**
     * Map the given results to instances of the given model.
     *
     * @param Builder $builder
     * @param mixed $results
     * @param Model $model
     * @return Collection|mixed
     */
    public function map(Builder $builder, $results, $model)
    {
        return $this->lazyMap($builder, $results, $model);
    }

    /**
     * Map the given results to instances of the given model via a lazy collection.
     *
     * @param Builder $builder
     * @param mixed $results
     * @param Model $model
     * @return \Illuminate\Support\LazyCollection|mixed
     */
    public function lazyMap(Builder $builder, $results, $model)
    {
        if ($results['count'] == 0) {
            return $model->newCollection();
        }
        /*
         * Pluck ids
         */
        $ids = $this->mapIds($results)->toArray();
        /*
         * Return as models
         */
        return $model->getScoutModelsByIds($builder, $ids);
    }

    /**
     * Get the total count from a raw result returned by the engine.
     *
     * @param mixed $results
     * @return int|mixed
     */
    public function getTotalCount($results)
    {
        return $results['count'];
    }

    /**
     * Flush all of the model's records from the engine.
     *
     * @param Model $model
     */
    public function flush($model)
    {
        $this->rss->redis()->del($this->getClassSearchableFqdn($model));
    }

    public function createIndex($name, array $options = [])
    {
        // not used
    }

    /**
     * @param string $name
     * @return mixed|void
     */
    public function deleteIndex($name)
    {
        // not used
    }
}

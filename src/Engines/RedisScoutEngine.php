<?php

namespace Tarre\RedisScoutEngine\Engines;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Tarre\RedisScoutEngine\Exceptions\FeatureNotSupportedException;


class RedisScoutEngine extends Engine
{
    /**
     * Update the given model in the index.
     *
     * @param \Illuminate\Database\Eloquent\Collection $models
     * @return void
     */
    public function update($models)
    {
        $this->pipelineModels($models, function (string $scoutKeyName, string $modelKey, bool $hasSoftDeletes, Model $model, $pipe) {
            /*
             *  prep options
             */
            $searchableString = $this->serializeToSearchableString($searchable = $model->toSearchableArray());
            $scoutKey = $model->getScoutKey();
            /*
             * Define vip fields for later use
             */
            $vipFields = [
                $scoutKeyName => $scoutKey
            ];
            /*
             * Check if we have softdeletes
             */
            if ($hasSoftDeletes) {
                $vipFields['__soft_deleted'] = $model->trashed() ? 1 : 0;
            }
            /*
             * Create model array with the models searchable items and override with vip fields.
             */
            $modelArr = array_merge($searchable, $vipFields);
            /*
             * save payload to scoutKey record
             */
            $payload = json_encode([
                'model' => $modelArr,
                'searchable' => $searchableString,
            ]);
            /*
             * Save to redis db
             */
            $pipe->hset($modelKey, $scoutKey, $payload);
        });
    }

    /**
     * Remove the given model from the index.
     *
     * @param Collection $modelse
     */
    public function delete($models)
    {
        $this->pipelineModels($models, function (string $scoutKeyName, string $modelKey, bool $hasSoftDeletes, Model $model, $pipe) {
            $pipe->hDel($modelKey, $model->getScoutKey());
        });
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @return array|mixed
     * @throws FeatureNotSupportedException
     */
    public function search(Builder $builder)
    {
        $limit = $builder->limit ?: $builder->model->getPerPage();

        return $this->paginate($builder, $limit, 1);
    }

    /**
     * Perform the given search on the engine.
     *
     * @param Builder $builder
     * @param int $perPage
     * @param int $page
     * @return array
     * @throws FeatureNotSupportedException
     */
    public function paginate(Builder $builder, $perPage, $page)
    {
        if ($builder->index) {
            throw new FeatureNotSupportedException('within');
        }

        if ($builder->callback) {
            throw new FeatureNotSupportedException('search(..., callback)');
        }

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

    /**
     * Pluck and return the primary keys of the given results.
     *
     * @param mixed $results
     * @return \Illuminate\Support\Collection
     */
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

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
             * convert "toSearchableArray" to a searchable string
             */
            $searchableString = array_values($model->toSearchableArray());
            $searchableString = implode(' ', $searchableString);
            /*
             * Save the model and the searchable string in an array
             */
            $payload = json_encode([
                'model' => $model->toArray(),
                'searchable' => $searchableString,
            ]);
            /*
             * Save
             */
            $redis->hset($modelKey, $model->getScoutKey(), $payload);
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
            'key' => $builder->model->getScoutKey()
        ];
    }

    public function mapIds($results)
    {
        return $results['results']->pluck($results['key']);
    }

    public function map(Builder $builder, $results, $model)
    {
        return $results['results'];
    }

    public function lazyMap(Builder $builder, $results, $model)
    {
        return $results['results'];
    }

    public function getTotalCount($results)
    {
        return $results['count'];
    }

    public function flush($model)
    {
        $this->rss->redis()->del($this->getClassSearchableFqdn($model));
    }

    public function createIndex($name, array $options = [])
    {
        // not used
    }

    public function deleteIndex($name)
    {
        // not used
    }
}

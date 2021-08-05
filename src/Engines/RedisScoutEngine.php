<?php

namespace Tarre\RedisScoutEngine\Engines;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine;
use Illuminate\Support\Facades\Redis as RedisFacade;
use Redis;
use Tarre\RedisScoutEngine\Cache;
use Tarre\RedisScoutEngine\Helper;
use Tarre\RedisScoutEngine\Service\RedisSearchService;

/**
 * @property RedisSearchService rss
 */
class RedisScoutEngine extends Engine
{

    protected $rss;

    public function __construct(RedisSearchService $rss)
    {
        $this->rss = $rss;
    }

    /**
     * @param Collection $models
     */
    public function update($models)
    {
        $this->rss->pipelineModels($models, function (string $modelKey, Model $model, Redis $redis) {
            $redis->hset($modelKey, $model->getScoutKey(), $model->toJson());
        });
    }

    public function delete($models)
    {
        $this->rss->pipelineModels($models, function (string $modelKey, Model $model, Redis $redis) {
            $redis->hDel($modelKey, $model->getScoutKey());
        });
    }

    public function search(Builder $builder)
    {
        $limit = $this->rss->getLimitFromBuilder($builder);

        return $this->paginate($builder, $limit, 1);
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        $skip = $perPage * ($page - 1);
        $take = $perPage;

        $results = $this->rss->getResult($builder, $skip, $take, $count);

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
        $this->rss->redis->del($this->rss->getClassSearchableFqdn($model));
    }

    public function createIndex($name, array $options = [])
    {
        // no need
    }

    public function deleteIndex($name)
    {
        // no need
    }
}

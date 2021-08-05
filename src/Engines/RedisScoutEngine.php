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

/**
 * @property \Illuminate\Redis\Connections\PhpRedisConnection redis
 */
class RedisScoutEngine extends Engine
{
    protected $redis;

    protected $prefix = 'redis-scout-engine.';

    public function __construct()
    {
        $this->redis = RedisFacade::connection();
    }

    /**
     * @param Collection $models
     */
    public function update($models)
    {
        $this->pipelineModels($models, function (string $modelKey, Model $model, Redis $redis) {
            $redis->hset($modelKey, $model->getScoutKey(), $model->toJson());
        });
    }

    /**
     * @param Collection $models
     */
    public function delete($models)
    {
        $this->pipelineModels($models, function (string $modelKey, Model $model, Redis $redis) {
            $redis->hDel($modelKey, $model->getScoutKey());
        });
    }

    public function search(Builder $builder)
    {
        $limit = $this->getLimitFromBuilder($builder);

        return $this->paginate($builder, $limit, 1);
    }

    public function paginate(Builder $builder, $perPage, $page)
    {
        $skip = $perPage * ($page - 1);
        $take = $perPage;

        $results = $this->getResult($builder, $skip, $take, $count);

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
        $this->redis->del($this->getClassSearchableFqdn($model));
    }

    /**
     * @param Builder $builder
     * @param $skip
     * @param $take
     * @return LazyCollection
     */
    protected function getResult(Builder $builder, $skip, $take, &$count = 0)
    {
        $fqdn = $this->getClassSearchableFqdn($builder->model);
        Cache::init($fqdn);
        /*
         * Initialize lazy collection
         */
        $lc = LazyCollection::make($this->yeetKeysFromFqdn($fqdn));
        /*
         * Handle wheres
         */
        if (!!$builder->wheres) {
            $lc = $lc->filter(Helper::filter($this, $builder, $fqdn));
        }
        /*
         * Handle whereIns
         */
        if (!!$builder->whereIns) {
            $lc = $lc->filter(Helper::filterArray($this, $builder, $fqdn));
        }
        /*
         * Handle orders
         */
        foreach ($builder->orders as $order) {
            switch ($order['direction']) {
                case 'asc':
                    $lc = $lc->sortBy(Helper::sortBy($this, $builder, $fqdn, $order['column']));
                    break;
                case 'desc':
                    $lc = $lc->sortByDesc(Helper::sortBy($this, $builder, $fqdn, $order['column']));
                    break;
            }
        }
        /*
         * Handle searches
         */
        if ($builder->query) {
            $lc = $lc->filter(Helper::search($this, $builder, $fqdn));
        }
        /*
         * Update count result
         */
        $count = $lc->count();
        /*
         * Return result
         */
        return $lc
            ->slice($skip, $take)
            ->map(function ($key) use ($fqdn) {
                return $this->hGetAssoc($fqdn, $key);
            })->values();
    }

    /**
     * @param $fqdn
     * @param $key
     * @return mixed
     */
    public function hGetAssoc($fqdn, $key)
    {
        return json_decode($this->hGet($fqdn, $key), true);
    }

    /**
     * @param $fqdn
     * @param $key
     * @return false|mixed|string|null
     */
    public function hGet($fqdn, $key)
    {
        if ($res = Cache::g($fqdn, $key)) {
            return $res;
        }

        $res = $this->redis->hGet($fqdn, $key);

        Cache::s($fqdn, $key, $res);

        return $res;
    }

    /**
     * @param $fqdn
     * @return \Generator
     */
    protected function yeetKeysFromFqdn($fqdn)
    {
        $keys = $this->allKeys($fqdn);

        foreach ($keys as $key) {
            yield $key;
        }
    }

    /**
     * @param $fqdn
     * @return array
     */
    protected function allKeys($fqdn): array
    {
        return $this->redis->hKeys($fqdn);
    }

    /**
     * @param Builder $builder
     * @return int
     */
    protected function getLimitFromBuilder(Builder $builder)
    {
        return $builder->limit ?: $builder->model->getPerPage();
    }

    /**
     * @param Model $model
     * @return string
     */
    protected function getClassSearchableFqdn(Model $model): string
    {
        return $this->prefix . $model->searchableAs();
    }

    /**
     * @param Collection $models
     * @param callable $fn
     */
    protected function pipelineModels(Collection $models, callable $fn)
    {
        $this->redis->pipeline(function (Redis $pipe) use (&$models, $fn) {
            $models->each(function (Model &$model) use (&$pipe, $fn) {
                $modelKey = $this->getClassSearchableFqdn($model);
                $fn($modelKey, $model, $pipe);
            });
        });
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

<?php


namespace Tarre\RedisScoutEngine\Service;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis as RedisFacade;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Redis;
use Tarre\RedisScoutEngine\Cache;

/**
 * @property \Illuminate\Redis\Connections\PhpRedisConnection redis
 */
class RedisSearchService
{
    protected $redis;
    protected $prefix = 'redis-scout-engine.';

    public function __construct()
    {
        $this->redis = RedisFacade::connection();

    }

    /**
     * @param Builder $builder
     * @param $skip
     * @param $take
     * @return LazyCollection
     */
    public function getResult(Builder $builder, $skip, $take, &$count = 0)
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
            $lc = $lc->filter($this->filter($builder->wheres, $fqdn));
        }
        /*
         * Handle whereIns
         */
        if (!!$builder->whereIns) {
            $lc = $lc->filter($this->filterArray($builder->whereIns, $fqdn));
        }
        /*
         * Handle orders
         */
        foreach ($builder->orders as $order) {
            switch ($order['direction']) {
                case 'asc':
                    $lc = $lc->sortBy($this->sortBy($fqdn, $order['column']));
                    break;
                case 'desc':
                    $lc = $lc->sortByDesc($this->sortBy($fqdn, $order['column']));
                    break;
            }
        }
        /*
         * Handle searches
         */
        if ($builder->query) {
            $lc = $lc->filter($this->filterSearch($builder->query, $fqdn));
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
    public function yeetKeysFromFqdn($fqdn)
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
    public function allKeys($fqdn): array
    {
        return $this->redis->hKeys($fqdn);
    }

    /**
     * @param Builder $builder
     * @return int
     */
    public function getLimitFromBuilder(Builder $builder)
    {
        return $builder->limit ?: $builder->model->getPerPage();
    }

    /**
     * @param Model $model
     * @return string
     */
    public function getClassSearchableFqdn(Model $model): string
    {
        return $this->prefix . $model->searchableAs();
    }

    /**
     * @param Collection $models
     * @param callable $fn
     */
    public function pipelineModels(Collection $models, callable $fn)
    {
        $this->redis->pipeline(function (Redis $pipe) use (&$models, $fn) {
            $models->each(function (Model &$model) use (&$pipe, $fn) {
                $modelKey = $this->getClassSearchableFqdn($model);
                $fn($modelKey, $model, $pipe);
            });
        });
    }

    /*
     * Filter helpers
     */
    protected function filterSearch($query, $fqdn)
    {
        return function ($key) use ($query, $fqdn) {
            $res = $this->hget($fqdn, $key);
            return stripos($res, $query) !== false;
        };
    }

    protected function filter(array $wheres, $fqdn)
    {
        return function ($key) use ($wheres, $fqdn) {
            $m = $this->hGetAssoc($fqdn, $key);
            /*
             * Check if at least one condition failed, then we abort
             */
            foreach ($wheres as $key => $value) {
                if ($m[$key] !== $value) {
                    return false;
                }
            }
            /*
             * All conditions passed
             */
            return true;
        };
    }

    protected function filterArray(array $whereIns, $fqdn)
    {
        return function ($key) use ($whereIns, $fqdn) {
            $m = $this->hGetAssoc($fqdn, $key);
            /*
             * Check if at least one condition failed, then we abort
             */
            foreach ($whereIns as $key => $value) {
                if (!in_array($m[$key], $value)) {
                    return false;
                }
            }
            /*
             * All conditions passed
             */
            return true;
        };
    }

    protected function sortBy($fqdn, $sortBy)
    {
        return function ($key) use ($fqdn, $sortBy) {
            $m = $this->hGetAssoc($fqdn, $key);

            return $m[$sortBy];
        };
    }

}

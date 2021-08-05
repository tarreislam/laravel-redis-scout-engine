<?php


namespace Tarre\RedisScoutEngine\Service;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Redis as RedisFacade;
use Illuminate\Support\LazyCollection;
use Laravel\Scout\Builder;
use Redis;
use Tarre\RedisScoutEngine\Cache;
use Tarre\RedisScoutEngine\Helper;

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
    public  function getResult(Builder $builder, $skip, $take, &$count = 0)
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
    public  function hGet($fqdn, $key)
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
    public  function yeetKeysFromFqdn($fqdn)
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
    public  function allKeys($fqdn): array
    {
        return $this->redis->hKeys($fqdn);
    }

    /**
     * @param Builder $builder
     * @return int
     */
    public  function getLimitFromBuilder(Builder $builder)
    {
        return $builder->limit ?: $builder->model->getPerPage();
    }

    /**
     * @param Model $model
     * @return string
     */
    public  function getClassSearchableFqdn(Model $model): string
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

}

<?php


namespace Tarre\RedisScoutEngine\Services;

use Illuminate\Contracts\Redis\Connection as GenericRedisConnection;
use Illuminate\Support\LazyCollection;
use Tarre\RedisScoutEngine\SearchMethods;

/**
 * @property \Illuminate\Contracts\Redis\Connection $redisInstance
 */
class RedisSearchService
{
    protected $redisInstance;

    public function __construct(GenericRedisConnection $redisInstance)
    {
        $this->redisInstance = $redisInstance;
    }

    /**
     * @param string $fqdn
     * @param string $query
     * @param array $wheres
     * @param array $whereIns
     * @param $skip
     * @param $take
     * @param int $count
     * @return LazyCollection
     */
    public function search(string $fqdn, $query, array $wheres, array $whereIns, $skip, $take, &$count)
    {
        /*
         * Initialize lazy collection
         */
        $lc = LazyCollection::make($this->hGetAll($fqdn))->map($this->mapRes());
        /*
         * Handle wheres
         */
        if (!!$wheres) {
            $lc = $lc->filter($this->handleWheres($wheres));
        }
        /*
         * Handle whereIns
         */
        if (!!$whereIns) {
            $lc = $lc->filter($this->handleWhereIns($whereIns));
        }
        /*
         * Handle searches
         */
        if ($query) {
            $lc = $lc->filter($this->handleSearch($query));
        }
        /*
         * Get count of results
         */
        $count = $lc->count();
        /*
         * Slice and return
         */
        return $lc
            ->slice($skip, $take)
            ->pluck('model');
    }

    /**
     * The redis instance for the service
     * @return \Illuminate\Contracts\Redis\Connection
     */
    public function redis()
    {
        return $this->redisInstance;
    }

    /**
     * @param $fqdn
     * @return array
     */
    protected function hGetAll($fqdn): array
    {
        return $this->redisInstance->hGetAll($fqdn);
    }

    /**
     * @return \Closure
     */
    protected function mapRes()
    {
        return function ($pair) {
            $pairArray = json_decode($pair, true);
            return [
                'searchable' => $pairArray['searchable'],
                'model' => $pairArray['model']
            ];
        };
    }

    /**
     * @param $query
     * @return \Closure
     */
    protected function handleSearch($query)
    {
        switch (config('scout.redis.method')) {
            default:
            case SearchMethods::STRIPOS:
                return function ($pair) use ($query) {
                    return stripos($pair['searchable'], $query) !== false;
                };
            case SearchMethods::STRPOS:
                return function ($pair) use ($query) {
                    return strpos($pair['searchable'], $query) !== false;
                };
            case SearchMethods::WILDCARD:
                $query = preg_quote($query, '/');
                $query = str_replace('\*', '.*', $query);
                $query = "/$query/i";
                return function ($pair) use ($query) {
                    return preg_match($query, $pair['searchable']);
                };
            case SearchMethods::REGEX:
                return function ($pair) use ($query) {
                    return preg_match($query, $pair['searchable']);
                };
        }
    }

    /**
     * @param array $wheres
     * @return \Closure
     */
    protected function handleWheres(array $wheres)
    {
        return function ($pair) use ($wheres) {
            $model = $pair['model'];
            /*
             * Check if at least one condition failed, then we abort
             */
            foreach ($wheres as $key => $value) {
                if ($model[$key] !== $value) {
                    return false;
                }
            }
            /*
             * All conditions passed
             */
            return true;
        };
    }

    /**
     * @param array $whereIns
     * @return \Closure
     */
    protected function handleWhereIns(array $whereIns)
    {
        return function ($pair) use ($whereIns) {
            $model = $pair['model'];
            /*
             * if anything present in the array, we allow
             */
            foreach ($whereIns as $key => $value) {
                if (in_array($model[$key], $value)) {
                    return true;
                }
            }
            /*
             * ignore record
             */
            return false;
        };
    }
}

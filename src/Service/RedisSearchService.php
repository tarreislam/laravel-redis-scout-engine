<?php


namespace Tarre\RedisScoutEngine\Service;

use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\LazyCollection;
use Tarre\RedisScoutEngine\Cache;

/**
 * @property \Illuminate\Redis\Connections\PhpRedisConnection $redisInstance
 */
class RedisSearchService
{
    protected $redisInstance;

    public function __construct(PhpRedisConnection $redisInstance)
    {
        $this->redisInstance = $redisInstance;
    }

    /**
     * @param string $fqdn
     * @param string $query
     * @param array $wheres
     * @param array $whereIns
     * @param array $orders
     * @param $skip
     * @param $take
     * @param int $count
     * @return LazyCollection
     */
    public function search(string $fqdn, $query, array $wheres, array $whereIns, array $orders, $skip, $take, &$count)
    {
        Cache::init($fqdn);
        /*
         * Initialize lazy collection
         */
        $lc = LazyCollection::make($this->hKeys($fqdn));
        /*
         * Handle wheres
         */
        if (!!$wheres) {
            $lc = $lc->filter($this->filter($wheres, $fqdn));
        }
        /*
         * Handle whereIns
         */
        if (!!$whereIns) {
            $lc = $lc->filter($this->filterArray($whereIns, $fqdn));
        }
        /*
         * Handle orders
         */
        foreach ($orders as $order) {
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
        if ($query) {
            $lc = $lc->filter($this->filterSearch($query, $fqdn));
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
            ->map(function ($key) use ($fqdn) {
                return $this->hGetAssoc($fqdn, $key);
            })->values();
    }

    /**
     * The redis instance for the service
     * @return \Illuminate\Redis\Connections\PhpRedisConnection
     */
    public function redis()
    {
        return $this->redisInstance;
    }

    /**
     * @param $fqdn
     * @param $key
     * @return mixed
     */
    protected function hGetAssoc($fqdn, $key)
    {
        return json_decode($this->hGet($fqdn, $key), true);
    }

    /**
     * @param $fqdn
     * @param $key
     * @return false|mixed|string|null
     */
    protected function hGet($fqdn, $key)
    {
        if ($res = Cache::g($fqdn, $key)) {
            return $res;
        }

        $res = $this->redisInstance->hGet($fqdn, $key);

        Cache::s($fqdn, $key, $res);

        return $res;
    }

    /**
     * @param $fqdn
     * @return array
     */
    protected function hKeys($fqdn): array
    {
        return $this->redisInstance->hKeys($fqdn);
    }

    /**
     * @param $query
     * @param $fqdn
     * @return \Closure
     */
    protected function filterSearch($query, $fqdn)
    {
        return function ($key) use ($query, $fqdn) {
            $res = $this->hget($fqdn, $key);
            return stripos($res, $query) !== false;
        };
    }

    /**
     * @param array $wheres
     * @param $fqdn
     * @return \Closure
     */
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

    /**
     * @param array $whereIns
     * @param $fqdn
     * @return \Closure
     */
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

    /**
     * @param $fqdn
     * @param $sortBy
     * @return \Closure
     */
    protected function sortBy($fqdn, $sortBy)
    {
        return function ($key) use ($fqdn, $sortBy) {
            $m = $this->hGetAssoc($fqdn, $key);

            return $m[$sortBy];
        };
    }
}

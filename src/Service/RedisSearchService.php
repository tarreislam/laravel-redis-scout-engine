<?php


namespace Tarre\RedisScoutEngine\Service;

use Illuminate\Redis\Connections\PhpRedisConnection;
use Illuminate\Support\LazyCollection;

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
        /*
         * Initialize lazy collection
         */
        $lc = LazyCollection::make($this->hGetAll($fqdn))->map($this->mapRes());
        /*
         * Handle wheres
         */
        if (!!$wheres) {
            $lc = $lc->filter($this->filter($wheres));
        }
        /*
         * Handle whereIns
         */
        if (!!$whereIns) {
            $lc = $lc->filter($this->filterArray($whereIns));
        }
        /*
         * Handle orders
         */
        foreach ($orders as $order) {
            switch ($order['direction']) {
                case 'asc':
                    $lc = $lc->sortBy($this->sortBy($order['column']));
                    break;
                case 'desc':
                    $lc = $lc->sortByDesc($this->sortBy($order['column']));
                    break;
            }
        }
        /*
         * Handle searches
         */
        if ($query) {
            $lc = $lc->filter($this->filterSearch($query));
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
            ->pluck('assoc')
            ->values();
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
        return function ($res) {
            return [
                'res' => $res,
                'assoc' => json_decode($res, true)
            ];
        };
    }

    /**
     * @param $query
     * @return \Closure
     */
    protected function filterSearch($query)
    {
        return function ($pair) use ($query) {
            $res = $pair['res'];
            return stripos($res, $query) !== false;
        };
    }

    /**
     * @param array $wheres
     * @return \Closure
     */
    protected function filter(array $wheres)
    {
        return function ($pair) use ($wheres) {
            $m = $pair['assoc'];
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
     * @return \Closure
     */
    protected function filterArray(array $whereIns)
    {
        return function ($pair) use ($whereIns) {
            $m = $pair['assoc'];
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
     * @param $sortBy
     * @return \Closure
     */
    protected function sortBy($sortBy)
    {
        return function ($pair) use ($sortBy) {
            $m = $pair['assoc'];

            return $m[$sortBy];
        };
    }
}

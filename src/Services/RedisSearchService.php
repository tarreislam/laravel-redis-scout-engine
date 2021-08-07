<?php


namespace Tarre\RedisScoutEngine\Services;

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
            ->pluck('model')
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
    protected function filterSearch($query)
    {
        return function ($pair) use ($query) {
            $res = $pair['searchable'];
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
    protected function filterArray(array $whereIns)
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

    /**
     * @param $sortBy
     * @return \Closure
     */
    protected function sortBy($sortBy)
    {
        return function ($pair) use ($sortBy) {
            $model = $pair['model'];
            return $model[$sortBy];
        };
    }
}

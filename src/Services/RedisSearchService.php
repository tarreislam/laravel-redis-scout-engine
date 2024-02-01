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
            $lc = $lc->filter($this->handleWheres($wheres));
        }
        /*
         * Handle whereIns
         */
        if (!!$whereIns) {
            $lc = $lc->filter($this->handleWhereIns($whereIns));
        }
        /*
         * Handle orders
         */
        $options = config('scout.redis.sort_options', SORT_NATURAL);
        foreach ($orders as $order) {
            switch ($order['direction']) {
                case 'asc':
                    $lc = $lc->sortBy($this->sortBy($order['column']), $options);
                    break;
                case 'desc':
                    $lc = $lc->sortByDesc($this->sortBy($order['column']), $options);
                    break;
            }
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
     */
    protected function hGetAll($fqdn)
    {
        return function () use ($fqdn) {
            $iterator = null;
            $count = (int)config('scout.redis.scan_chunk', 1000);

            while (true) {
                $result = $this->redisInstance->hscan($fqdn, $iterator, [
                    'count' => $count,
                ]);

                if ($result === false) {
                    break;
                }

                $iterator = $result[0];

                foreach ($result[1] as $key => $value) {
                    yield $key => $value;
                }
            }
        };
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
        // allow us to do greater than etc, its hacky but whatever i need it
        // allows for: $builder->where('salary', "\$OP:GTE:450")
        // enable by settings config/scout.php -> redis ['allow_advanced_operators' => true]
        if (config('scout.redis.allow_advanced_operators', false)) {
            return function ($pair) use ($wheres) {
                $model = $pair['model'];
                /*
                 * Check if at least one condition failed, then we abort
                 */
                foreach ($wheres as $key => $value) {
                    $modelValue = $model[$key];

                    if (preg_match('/^\$OP:(EQQ|NEQ|GTE|LTE):(.*)/', $value, $matches)) {
                        $operator = $matches[1];
                        $matchedValue = $matches[2];


                        switch ($operator) {
                            case 'EQQ':
                                if (($modelValue == $matchedValue)) {
                                    continue 2; // we do not return true because we might have more conditions to test
                                } else {
                                    return false;
                                }
                            case 'NEQ':
                                if ($modelValue != $matchedValue) {
                                    continue 2; // we do not return true because we might have more conditions to test
                                } else {
                                    return false;
                                }
                            case 'GTE':
                                if ($modelValue >= $matchedValue) {
                                    continue 2; // we do not return true because we might have more conditions to test
                                } else {
                                    return false;
                                }
                            case 'LTE':
                                if ($modelValue <= $matchedValue) {
                                    continue 2;
                                } else {
                                    return false;
                                }
                        }
                    } else if ($modelValue !== $value) { // default behaviour
                        return false;
                    }
                }
                /*
                 * All conditions passed
                 */
                return true;
            };
        }
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
    protected
    function handleWhereIns(array $whereIns)
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
    protected
    function sortBy($sortBy)
    {
        return function ($pair) use ($sortBy) {
            $model = $pair['model'];
            return $model[$sortBy];
        };
    }
}

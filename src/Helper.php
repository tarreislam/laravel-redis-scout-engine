<?php


namespace Tarre\RedisScoutEngine;

use Laravel\Scout\Builder;
use Tarre\RedisScoutEngine\Service\RedisSearchService;

class Helper
{
    /**
     * @param RedisSearchService $scoutEngine
     * @param Builder $builder
     * @param $fqdn
     * @return \Closure
     */
    public static function search(RedisSearchService $scoutEngine, Builder $builder, $fqdn)
    {
        return function ($key) use ($scoutEngine, $builder, $fqdn) {
            $res = $scoutEngine->hget($fqdn, $key);
            return stripos($res, $builder->query) !== false;
        };
    }

    /**
     * @param RedisSearchService $scoutEngine
     * @param Builder $builder
     * @param $fqdn
     * @return \Closure
     */
    public static function filter(RedisSearchService $scoutEngine, Builder $builder, $fqdn)
    {
        return function ($key) use ($scoutEngine, $builder, $fqdn) {
            $m = $scoutEngine->hGetAssoc($fqdn, $key);
            /*
             * Check if at least one condition failed, then we abort
             */
            foreach ($builder->wheres as $key => $value) {
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
     * @param RedisSearchService $scoutEngine
     * @param Builder $builder
     * @param $fqdn
     * @return \Closure
     */
    public static function filterArray(RedisSearchService $scoutEngine, Builder $builder, $fqdn)
    {
        return function ($key) use ($scoutEngine, $builder, $fqdn) {
            $m = $scoutEngine->hGetAssoc($fqdn, $key);
            /*
             * Check if at least one condition failed, then we abort
             */
            foreach ($builder->whereIns as $key => $value) {
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
     * @param RedisSearchService $scoutEngine
     * @param Builder $builder
     * @param $fqdn
     * @param $sortBy
     * @return \Closure
     */
    public static function sortBy(RedisSearchService $scoutEngine, Builder $builder, $fqdn, $sortBy)
    {
        return function ($key) use ($scoutEngine, $builder, $fqdn, $sortBy) {
            $m = $scoutEngine->hGetAssoc($fqdn, $key);

            return $m[$sortBy];
        };
    }
}

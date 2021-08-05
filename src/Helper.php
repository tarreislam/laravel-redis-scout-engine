<?php


namespace Tarre\RedisScoutEngine;

use Laravel\Scout\Builder;
use Tarre\RedisScoutEngine\Engines\RedisScoutEngine;

class Helper
{
    /**
     * @param RedisScoutEngine $scoutEngine
     * @param Builder $builder
     * @param $fqdn
     * @return \Closure
     */
    public static function search(RedisScoutEngine $scoutEngine, Builder $builder, $fqdn)
    {
        return function ($key) use ($scoutEngine, $builder, $fqdn) {
            $res = $scoutEngine->hget($fqdn, $key);
            return stripos($res, $builder->query) !== false;
        };
    }

    /**
     * @param RedisScoutEngine $scoutEngine
     * @param Builder $builder
     * @param $fqdn
     * @return \Closure
     */
    public static function filter(RedisScoutEngine $scoutEngine, Builder $builder, $fqdn)
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
     * @param RedisScoutEngine $scoutEngine
     * @param Builder $builder
     * @param $fqdn
     * @return \Closure
     */
    public static function filterArray(RedisScoutEngine $scoutEngine, Builder $builder, $fqdn)
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
     * @param RedisScoutEngine $scoutEngine
     * @param Builder $builder
     * @param $fqdn
     * @param $sortBy
     * @return \Closure
     */
    public static function sortBy(RedisScoutEngine $scoutEngine, Builder $builder, $fqdn, $sortBy)
    {
        return function ($key) use ($scoutEngine, $builder, $fqdn, $sortBy) {
            $m = $scoutEngine->hGetAssoc($fqdn, $key);

            return $m[$sortBy];
        };
    }
}

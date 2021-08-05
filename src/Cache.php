<?php


namespace Tarre\RedisScoutEngine;

class Cache
{
    static protected $cache;

    public static function init($fqdn)
    {
        self::$cache[$fqdn] = [];
    }

    public static function s($fqdn, $k, $v)
    {
        self::$cache[$fqdn][$k] = $v;
    }

    public static function g($fqdn, $k)
    {
        return self::$cache[$fqdn][$k] ?? null;
    }

}

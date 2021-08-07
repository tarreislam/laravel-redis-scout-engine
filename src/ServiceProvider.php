<?php

namespace Tarre\RedisScoutEngine;

use Illuminate\Support\Facades\Redis;
use Illuminate\Support\ServiceProvider as BaseProvider;
use Laravel\Scout\EngineManager;
use Tarre\RedisScoutEngine\Engines\RedisScoutEngine;
use Tarre\RedisScoutEngine\Services\RedisSearchService;

class ServiceProvider extends BaseProvider
{
    public function boot()
    {
        resolve(EngineManager::class)->extend('redis', function () {
            /*
             * Create redis instance for engine
             */
            $redis = Redis::connection(config('services.redis-scout-engine.connection.name'));
            /*
             * Create Redis Search service with the given redis instance
             */
            $rss = new RedisSearchService($redis);
            /*
             * Return Scout engine with redis search service
             */
            return new RedisScoutEngine($rss);
        });
    }

}

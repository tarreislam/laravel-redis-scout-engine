<?php

namespace Tarre\RedisScoutEngine;

use Illuminate\Support\ServiceProvider as BaseProvider;
use Laravel\Scout\EngineManager;
use Tarre\RedisScoutEngine\Engines\RedisScoutEngine;
use Tarre\RedisScoutEngine\Service\RedisSearchService;

class ServiceProvider extends BaseProvider
{
    public function boot()
    {
        resolve(EngineManager::class)->extend('redis', function () {
            return new RedisScoutEngine(new RedisSearchService);
        });
    }

}

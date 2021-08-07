<p align="center"><img src="https://i.imgur.com/C6Nk83V.png"></p>

<p align="center">
<a href="https://packagist.org/packages/tarre/laravel-redis-scout-engine"><img src="https://img.shields.io/packagist/v/tarre/laravel-redis-scout-engine?style=flat-square"></a>
<a href="https://packagist.org/packages/tarre/laravel-redis-scout-engine"><img src="https://img.shields.io/packagist/l/tarre/laravel-redis-scout-engine?style=flat-square"></a>
</p>

## About Laravel Redis Scout engine
Since no proper Redis engine was available for Laravel Scout I created one. Tested with ~10k records, response time was ~0.1 sec on local redis instance

### Installation

*Install with composer*

```
composer require tarre/laravel-redis-scout-engine
```

*.env*
```
SCOUT_DRIVER=redis

REDIS_HOST=....
REDIS_PASSWORD=null
REDIS_PORT=6379
```

*services.php (only required if you want to change anything)* 
```php
<?php

return [
    // ....
    'redis-scout-engine' => [
        /*
        |--------------------------------------------------------------------------
        | What connection to use
        |--------------------------------------------------------------------------
        |
        | Decide which redis connection will be used by the Engine
        |
        | Read more here: https://laravel.com/docs/8.x/redis
        |
        */
        'connection' => [
            'name' => null, // use default connection
        ],
        /*
        |--------------------------------------------------------------------------
        | Search method
        |--------------------------------------------------------------------------
        |
        | Decide which search method to use when searching
        |
        | * STRPOS              (case-sensitive https://php.net/strpos)
        | * STRIPOS (DEFAULT)   (case-insensitive https://php.net/stripos)
        | * WILDCARD            (case-insensitive preg_match but it will only accept "*" as wildcard)
        | * REGEX               (Can cause exceptions https://php.net/preg_match)
        */
        'method' => \Tarre\RedisScoutEngine\SearchMethods::STRIPOS
    ]

];
```

### Usage

See [official docs for usage](https://laravel.com/docs/8.x/scout#searching)

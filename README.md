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

*services.php*
```php
   'redis-scout-engine' => [
        'connection' => [
            'name' => null, // use default connection
        ]
    ]
```

### Usage

See [official docs for usage](https://laravel.com/docs/8.x/scout#searching)

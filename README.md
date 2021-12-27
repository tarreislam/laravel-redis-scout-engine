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

*scout.php (only required if you want to change anything)* 
```php
<?php

return [
    // ....
    /*
    |--------------------------------------------------------------------------
    | Redis configuration
    |--------------------------------------------------------------------------
    |
    */
    'redis' => [
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
        /*
        |--------------------------------------------------------------------------
        | orderBy sort options
        |--------------------------------------------------------------------------
        |
        | Read more about sort options on PHPs official docs
        |
        | https://www.php.net/manual/en/function.sort.php
        */
        'sort_options' => SORT_NATURAL
    ]

];
```

### Usage

See [official docs for usage](https://laravel.com/docs/8.x/scout#searching)

#### Callback for search

If you to filter the results for `get` and `paginate` you can use the `\Tarre\RedisScoutEngine\Callback`
```php
use App\Models\User;
use Tarre\RedisScoutEngine\Callback;

User::search('xxxx', fn(Callback $cb) => $cb->mapResult(fn(User $user) => ['id' => $user->id, 'name' => $user->name, 'abc' => 123]))->paginate()
```
```json
{
    "current_page":1,
    "data":[
        {
            "id":1,
            "name":"Kade Trantow",
            "abc":123
        },
        {
            "id":73,
            "name":"Kaden Gulgowski",
            "abc":123
        },
        {
            "id":722,
            "name":"Kade Goyette",
            "abc":123
        },
        {
            "id":1836,
            "name":"Dr. Kade Ankunding",
            "abc":123
        },
        {
            "id":3260,
            "name":"Kade Murray",
            "abc":123
        },
        {
            "id":8916,
            "name":"Prof. Kade Howe",
            "abc":123
        },
        {
            "id":9889,
            "name":"Kade Spinka",
            "abc":123
        }
    ],
    "first_page_url":"http:\/\/localhost?query=kade&page=1",
    "from":1,
    "last_page":1,
    "last_page_url":"http:\/\/localhost?query=kade&page=1",
    "links":[
        {
            "url":null,
            "label":"&laquo; Previous",
            "active":false
        },
        {
            "url":"http:\/\/localhost?query=kade&page=1",
            "label":"1",
            "active":true
        },
        {
            "url":null,
            "label":"Next &raquo;",
            "active":false
        }
    ],
    "next_page_url":null,
    "path":"http:\/\/localhost",
    "per_page":15,
    "prev_page_url":null,
    "to":7,
    "total":7
}
```

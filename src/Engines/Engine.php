<?php


namespace Tarre\RedisScoutEngine\Engines;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Laravel\Scout\Engines\Engine as BaseEngine;
use Redis;
use Tarre\RedisScoutEngine\Services\RedisSearchService;

/**
 * @property RedisSearchService rss
 */
/*
 * "jkuhyuij <A" -Björn
 */

abstract class Engine extends BaseEngine
{
    protected $rss;
    protected $prefix = 'redis-scout-engine.';

    public function __construct(RedisSearchService $rss)
    {
        $this->rss = $rss;
    }

    protected function modelHasSoftDeletes(Model $model)
    {
        return in_array(SoftDeletes::class, class_uses_recursive($model));
    }

    /**
     * @param Model $model
     * @return mixed|string
     */
    protected function getScoutKeyNameWithoutTable(Model $model)
    {
        $key = $model->getScoutKeyName();
        if (strpos($key, '.')) {
            return explode('.', $key)[1];
        }
        return $key;
    }

    /**
     * @param Model $model
     * @return string
     */
    protected function getClassSearchableFqdn(Model $model): string
    {
        return $this->prefix . $model->searchableAs();
    }

    /**
     * @param Collection $models
     * @param callable $fn
     */
    public function pipelineModels(Collection $models, callable $fn)
    {
        $this->rss->redis()->pipeline(function (Redis $pipe) use (&$models, $fn) {
            $models->each(function (Model &$model) use (&$pipe, $fn) {
                $modelKey = $this->getClassSearchableFqdn($model);
                $fn($modelKey, $model, $pipe);
            });
        });
    }

}

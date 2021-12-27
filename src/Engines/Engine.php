<?php


namespace Tarre\RedisScoutEngine\Engines;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection as SupportCollection;
use Laravel\Scout\Engines\Engine as BaseEngine;
use Tarre\RedisScoutEngine\Callback;
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
    protected $callback;

    public function __construct(RedisSearchService $rss)
    {
        $this->rss = $rss;
        $this->callback = new Callback;
    }

    /**
     * Convert searchable indefinitely
     *
     * @param $searchable
     * @return string
     */
    protected function serializeToSearchableString($searchable)
    {
        $searchableString = "";

        if (
            $searchable instanceof EloquentCollection ||
            $searchable instanceof SupportCollection ||
            $searchable instanceof Arrayable
        ) {
            $searchable = $searchable->toArray();
        }

        foreach ($searchable as $value) {
            if ((is_numeric($value) && strlen($value) > 1) || is_string($value)) { // only accept strings and numeric values greater than 1 char
                $searchableString .= "$value ";
            } elseif (is_array($value)) {
                $searchableString .= $this->serializeToSearchableString($value);
            } elseif (
                $value instanceof EloquentCollection ||
                $value instanceof SupportCollection ||
                $value instanceof Arrayable
            ) {
                $searchableString .= $this->serializeToSearchableString($value->toArray());
            }
        }

        return $searchableString;
    }

    /**
     * @param Model $model
     * @return bool
     */
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
     * @param EloquentCollection $models
     * @param callable $fn
     */
    public function pipelineModels(EloquentCollection $models, callable $fn)
    {
        $this->rss->redis()->pipeline(function ($pipe) use (&$models, $fn) {
            /*
             * Determine if models have SoftDeletes, all models will have common information here.
             */
            $hasSoftDeletes = $this->modelHasSoftDeletes($model = $models->first());
            $modelKey = $this->getClassSearchableFqdn($model);
            $scoutKeyName = $this->getScoutKeyNameWithoutTable($model);
            /*
             *
             */
            $models->each(function (Model &$model) use (&$pipe, $scoutKeyName, $modelKey, $hasSoftDeletes, $fn) {
                $fn($scoutKeyName, $modelKey, $hasSoftDeletes, $model, $pipe);
            });
        });
    }

}

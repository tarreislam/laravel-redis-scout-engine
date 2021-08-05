<?php


namespace Tarre\RedisScoutEngine\Engines;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Builder;
use Laravel\Scout\Engines\Engine as BaseEngine;
use Redis;
use Tarre\RedisScoutEngine\Service\RedisSearchService;

/**
 * @property RedisSearchService rss
 */
abstract class Engine extends BaseEngine
{
    protected $rss;
    protected $prefix = 'redis-scout-engine.';

    public function __construct(RedisSearchService $rss)
    {
        $this->rss = $rss;
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
        $this->rss->pipeline(function (Redis $pipe) use (&$models, $fn) {
            $models->each(function (Model &$model) use (&$pipe, $fn) {
                $modelKey = $this->getClassSearchableFqdn($model);
                $fn($modelKey, $model, $pipe);
            });
        });
    }

}

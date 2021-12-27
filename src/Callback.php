<?php

namespace Tarre\RedisScoutEngine;

use Closure;

class Callback
{
    /**
     * @var null|Closure
     */
    public $callableMapResult = null;

    /**
     * @param $callable
     */
    public function mapResult(Closure $callable): self
    {
        $this->callableMapResult = $callable;
        return $this;
    }

}

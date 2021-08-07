<?php


namespace Tarre\RedisScoutEngine\Exceptions;

use Exception;
use Throwable;

class FeatureNotSupportedException extends Exception
{
    public function __construct($message = "", $code = 0, Throwable $previous = null)
    {
        parent::__construct("\"$message\"" . " is not supported with this Scout engine", $code, $previous);
    }
}

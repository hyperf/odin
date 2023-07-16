<?php

namespace Hyperf\Odin\Exception;


use JetBrains\PhpStorm\Pure;
use Throwable;

class NotImplementedException extends RuntimeException
{
    #[Pure] public function __construct(string $message = "Not implemented", int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }

}
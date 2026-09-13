<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

class ReservaYaRequestException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
    }
}

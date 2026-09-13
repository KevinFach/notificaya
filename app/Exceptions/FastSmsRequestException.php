<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * FastSMS answered with an error status. The request definitively did not
 * take effect, so retrying it cannot create a duplicate message.
 */
class FastSmsRequestException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $status, $previous);
    }
}

<?php

namespace App\Exceptions;

use RuntimeException;
use Throwable;

/**
 * The call to FastSMS never produced a response (timeout, DNS, connection
 * reset). Whether the message was created is unknown, so a blind retry could
 * send the same SMS twice — these are surfaced for manual review instead.
 */
class FastSmsUnreachableException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}

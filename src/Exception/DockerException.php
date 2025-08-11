<?php

declare(strict_types=1);

namespace Docker\API\Exception;

use Exception;
use Throwable;

/**
 * Base Docker API exception
 */
class DockerException extends Exception
{
    public function __construct(string $message = '', int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
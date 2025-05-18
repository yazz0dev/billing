<?php

namespace Core\Exception;

class HttpException extends \Exception
{
    protected int $statusCode;

    public function __construct(string $message = "", int $statusCode = 500, \Throwable $previous = null)
    {
        $this->statusCode = $statusCode;
        parent::__construct($message, $statusCode, $previous); // Use statusCode also for the general \Exception code
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

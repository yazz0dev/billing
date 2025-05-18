<?php

namespace App\Core\Exception;

class HttpException extends \Exception
{
    protected int $statusCode;

    public function __construct(string $message = "", int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->statusCode = $code;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }
}

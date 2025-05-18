<?php

namespace Core\Exception;

class AccessDeniedException extends HttpException
{
    public function __construct(string $message = "Access Denied", \Throwable $previous = null)
    {
        parent::__construct($message, 403, $previous);
    }
}

<?php

namespace Core\Exception;

class RouteNotFoundException extends HttpException
{
    public function __construct(string $message = "Route Not Found", \Throwable $previous = null)
    {
        parent::__construct($message, 404, $previous);
    }
}

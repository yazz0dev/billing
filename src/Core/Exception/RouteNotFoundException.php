<?php

namespace Core\Exception;

class RouteNotFoundException extends \Exception
{
    protected $message = 'Route Not Found';
    protected $code = 404;
}

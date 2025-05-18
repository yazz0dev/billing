<?php

namespace Core\Exception;

class AccessDeniedException extends \Exception
{
    protected $message = 'Access Denied';
    protected $code = 403;
}

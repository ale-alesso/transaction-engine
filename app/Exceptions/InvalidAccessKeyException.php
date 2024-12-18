<?php

namespace App\Exceptions;

use Exception;

class InvalidAccessKeyException extends Exception
{
    public function __construct($message = 'Invalid access key provided.', $code = 101, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}

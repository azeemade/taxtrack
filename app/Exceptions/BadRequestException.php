<?php

namespace App\Exceptions;

use Exception;

class BadRequestException extends Exception
{
    public function __construct($message = 'Bad Request', $statusCode = 400)
    {
        parent::__construct($message, $statusCode);
    }
}

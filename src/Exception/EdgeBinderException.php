<?php

declare(strict_types=1);

namespace EdgeBinder\Exception;

use Exception;

/**
 * Base exception for all EdgeBinder-related errors.
 * 
 * This serves as the root exception that all other EdgeBinder exceptions
 * extend from, allowing consumers to catch all EdgeBinder-specific errors
 * with a single catch block if desired.
 */
class EdgeBinderException extends Exception
{
}

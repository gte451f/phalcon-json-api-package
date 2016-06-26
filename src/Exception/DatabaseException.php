<?php
namespace PhalconRest\Exception;

use PhalconRest\Exception\HTTPException;

/**
 * where caught Database Exceptions go to die
 *
 * @author jjenkins
 *
 */
class DatabaseException extends HTTPException
{
}
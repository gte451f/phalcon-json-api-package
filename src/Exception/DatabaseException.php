<?php
namespace PhalconRest\Exception;

/**
 * where caught Database Exceptions go to die
 * this is really just a HTTPException, we haven't found any special logic needed for this type of exception yet
 *
 * @author jjenkins
 */
class DatabaseException extends HTTPException
{
}
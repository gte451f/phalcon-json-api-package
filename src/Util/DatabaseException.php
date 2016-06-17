<?php
namespace PhalconRest\Util;

/**
 * where caught Database Exceptions go to die, based on HTTP Exceptions
 *
 * @author jjenkins
 */
class DatabaseException extends \HttpException { }
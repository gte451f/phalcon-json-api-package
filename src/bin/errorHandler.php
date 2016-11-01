<?php
use PhalconRest\Exception\HTTPException;
use PhalconRest\API\Output;

// ***************************************************************
// this set of functions hooks custom handling for
// 1) Exceptions via set_exception_handler
// 2) General Errors via set_error_handler
// 3) PHP Fatal Errors via register_shutdown_function
//
// the general goal is to always return json formatted errors via a
// $result object stuffed with one or more ErrorStore objects
// ***************************************************************


/** @var array $config */

/**
 * use this function to route Exceptions to their proper handlers for eventual output on screen
 *
 * TODO: Improve this.
 * TODO: Kept here due to dependency on $app
 */
set_exception_handler(function (\Throwable $thrown) use ($app, $config, $di) {

    // if the application throws an HTTPException, DatabaseException or ValidationException
    // use that class's internal handling to output results to server
    if ($thrown instanceof HTTPException) {
        error_log($thrown);
        $thrown->send();
    } else {
        // create an errorStore
        $errorStore = new PhalconRest\Exception\ErrorStore([
            'code' => $thrown->getCode(),
            'detail' => $thrown->getMessage(),
            'file' => $thrown->getFile(),
            'line' => $thrown->getLine(),
            'title' => 'Unexpected ' . get_class($thrown),
            'stack' => $thrown->getTrace()
        ]);

        if ($thrown->getPrevious()) {
            $errorStore->context = '[Previous] ' . (string)$thrown->getPrevious();
        }

        // push to result
        $result = $di->get('result', []);
        $result->addError($errorStore);

        // send to output
        $output = new Output();
        return $output->send($result);
    }
});


/**
 * this is a small function to connect fatal PHP errors to global error handling
 */
register_shutdown_function(function () use ($app, $config, $di) {
    $error = error_get_last();
    if ($error) {
        // clean any pre-existing error text output to the screen
        ob_clean();

        $errorStore = new \PhalconRest\Exception\ErrorStore([
            'code' => '8273492734598729347598237',
            'title' => $error['type'] . ' - ' . errorTypeStr($error['type']),
            'more' => $error['message'],
            'line' => $error['line'],
            'file' => $error['file']
        ]);

        $backTrace = debug_backtrace(true, 5);

        // generates a simplified backtrace
        $backTraceLog = [];
        foreach ($backTrace as $record) {
            // clean out args since these can cause recursion problems and isn't all that valuable anyway
            if (isset($record['args'])) {
                unset($record['args']);
            }
            $backTraceLog[] = $record;
        }
        $errorStore->stack = $backTraceLog;

        // push to result
        $result = $di->get('result', []);
        $result->addError($errorStore);

        // send to output
        $output = new Output();
        return $output->send($result);

    }
});


/**
 * Translates PHP's bit error codes into actual human text
 * @param int $code
 * @return int|string
 */
function errorTypeStr($code)
{
    switch ($code) {
        case E_ERROR:
            return 'ERROR';
        case E_WARNING:
            return 'WARNING';
        case E_PARSE:
            return 'PARSE';
        case E_NOTICE:
            return 'NOTICE';
        case E_CORE_ERROR:
            return 'CORE ERROR';
        case E_CORE_WARNING:
            return 'CORE WARNING';
        case E_COMPILE_ERROR:
            return 'COMPILE ERROR';
        case E_COMPILE_WARNING:
            return 'COMPILE WARNING';
        case E_USER_ERROR:
            return 'USER ERROR';
        case E_USER_WARNING:
            return 'USER WARNING';
        case E_USER_NOTICE:
            return 'USER NOTICE';
        case E_STRICT:
            return 'STRICT';
        case E_RECOVERABLE_ERROR:
            return 'RECOVERABLE ERROR';
        case E_DEPRECATED:
            return 'DEPRECATED';
        case E_USER_DEPRECATED:
            return 'USER DEPRECATED';
        default:
            return $code;
    }
}

/**
 * custom error handler function will process regular PHP errors
 * and convert them to ErrorStore objects then run them through our regular api processing
 *
 * @param $errno
 * @param $errstr
 * @param $errfile
 * @param $errline
 * @param \Throwable|mixed $context
 * @param string $title
 */

set_error_handler(function ($errno, $errstr, $errfile, $errline, $context = null, $title = 'Fatal Error Occurred') use (
    $app,
    $config,
    $di
) {
    // clean any pre-existing error text output to the screen
    ob_clean();

    $errorStore = new \PhalconRest\Exception\ErrorStore([
        'code' => $errno,
        'title' => is_string($title) ? $title : 'Fatal Error Occurred and bad $title given',
        'more' => $errstr,
        'context' => $context,
        'line' => $errline,
        'file' => $errfile
    ]);


    if ($context instanceof \Throwable) {
        if ($previous = $context->getPrevious()) {
            $errorStore->context = '[Previous] ' . (string)$previous; //todo: could recurse the creation of exception details
        } else {
            $errorStore->context = null;
        }
        $backTrace = explode("\n", $context->getTraceAsString());
        array_walk($backTrace, function (&$line) {
            $line = preg_replace('/^#\d+ /', '', $line);
        });
    } else {
        $errorStore->context = $context;
        $backTrace = debug_backtrace(true, 5); //FIXME: shouldn't backtrace be shown only in debug mode?
    }

    // generates a simplified backtrace
    $backTraceLog = [];
    foreach ($backTrace as $record) {
        // clean out args since these can cause recursion problems and isn't all that valuable anyway
        if (isset($record['args'])) {
            unset($record['args']);
        }
        $backTraceLog[] = $record;
    }
    $errorStore->stack = $backTraceLog;

    // push to result
    $result = $di->get('result', []);
    $result->addError($errorStore);

    // send to output
    $output = new Output();
    return $output->send($result);
});

<?php
use PhalconRest\Util\HTTPException;
use PhalconRest\Util\ValidationException;

/** @var array $config */

/**
 * If the application throws an HTTPException, send it on to the client as json.
 * Elsewise, just log it.
 * TODO: Improve this.
 * TODO: Kept here due to dependency on $app
 */
set_exception_handler(function ($exception) use ($app, $config) {
    switch (get_class($exception)) {
        case "PhalconRest\\Exception\\HTTPException":
        case 'PhalconRest\Exception\HTTPException':
        case "PhalconRest\\Exception\\ValidationException":
        case 'PhalconRest\Exception\ValidationException':
            error_log($exception);
            $exception->send();
            break;
        default:
            // wow an unexpected exception
            print_r($exception);
            break;
    }
});

/**
 * custom error handler function to always return 500 on errors
 *
 * @param $errno
 * @param $errstr
 * @param $errfile
 * @param $errline
 * @param mixed $context
 * @param string $title
 */
function customErrorHandler($errno, $errstr, $errfile, $errline, $context = null, $title = 'Fatal Error Occurred')
{
    // clean any pre-existing error text output to the screen
    ob_clean();

    $errorReport = new stdClass();
    $errorReport->id = 'root API package error handler';
    $errorReport->code = $errno;
    $errorReport->title = is_string($title) ? $title : 'Fatal Error Occurred and bad $title given';
    $errorReport->detail = $errstr;
    $errorReport->context = $context;

    // generate a simplified backtrace
    //FIXME: shouldn't backtrace be shown only in debug mode?
    $backTrace = debug_backtrace(true, 5);
    $backTraceLog = [];
    foreach ($backTrace as $record) {
        // clean out args since these can cause recursion problems and isn't all that valuable anyway
        if (isset($record['args'])) {
            unset($record['args']);
        }
        $backTraceLog[] = $record;
    }

    $errorReport->meta = [
        'line' => $errline,
        'file' => $errfile,
        'stack' => $backTraceLog
    ];

    // connect this to the default way of handling errors?
    $errors = new stdClass();
    $errors->errors = [$errorReport];
    $errorOutput = json_encode($errors);
    if ($errorOutput == false) {
        // a little meta, but the error function produced an error generating the json response
        echo "Error generating error code.  Ironic right?  " . json_last_error_msg();
    }

    http_response_code(500);
    header('Content-Type: application/json');
    echo $errorOutput;
    exit(1);

}

/**
 * this is a small function to connect fatal PHP errors to global error handling
 */
function shutDownFunction()
{
    $error = error_get_last();
    if ($error) {
        customErrorHandler($error['type'], $error['message'], $error['file'], $error['line'], null, errorTypeStr($error['type']));
    }
}

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

// set to the user defined error handler
set_error_handler("customErrorHandler");

// provide function to catch fatal errors
register_shutdown_function('shutDownFunction');
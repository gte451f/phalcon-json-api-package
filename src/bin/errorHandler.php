<?php

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
 */
function customErrorHandler($errno, $errstr, $errfile, $errline)
{
    // clean any pre-existing error text output to the screen
    ob_clean();

    $errorReport = new stdClass();
    $errorReport->id = '28374987239482793472';
    $errorReport->code = $errno;
    $errorReport->title = "Fatal Error Occurred";
    $errorReport->detail = $errstr;

    // generate a simplified backtrace
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
    if ($error) customErrorHandler($error["type"], $error["message"], $error["file"], $error["line"]);
}

// set to the user defined error handler
set_error_handler("customErrorHandler");

// provide function to catch fatal errors
register_shutdown_function('shutDownFunction');
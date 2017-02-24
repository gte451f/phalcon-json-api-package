<?php
namespace PhalconRest\Exception;

use PhalconRest\API\Output;
use PhalconRest\Exception\ErrorStore;

/**
 * General HTTP Exception Handler
 * contains helpful functions for dealing with a single error kept in $this->errorStore
 *
 * @author jjenkins
 *
 */
class HTTPException extends \Exception
{

    /**
     * store a copy of the DI
     */
    private $di;

    /**
     * hold a valid errorStore object
     *
     * @var \PhalconRest\Exception\ErrorStore
     */
    protected $errorStore;

    /**
     * @param string $title required user friendly message to return to the requestor
     * @param int $code required HTTP response code
     * @param array $errorList list of optional properties to set on the error object
     * @param \Throwable $previous previous exception, if any
     */
    public function __construct($title, $code, $errorList = [], \Throwable $previous = null)
    {
        //attaching local code to Exception message in case it's catch somewhere else
        $localCode = isset($errorList['code']) ? $errorList['code'] . '/' . $code : $code;

        parent::__construct("[$localCode] $title", $code, $previous);

        // store general error data
        $this->errorStore = new ErrorStore($errorList);
        $this->errorStore->title = $title;
        $this->errorStore->code = $localCode;

        $this->di = \Phalcon\Di::getDefault();
    }

    /**
     * Calls out {@link Output::sendError()} with the appropriate values.
     */
    public function send()
    {
        $output = new Output();
        $output->setStatusCode($this->code, $this->getResponseDescription($this->code));

        //push errorStore into $result object for proper handling
        $result = $this->di->get('result', []);
        $result->addError($this->errorStore);
        $output->send($result);
    }

    /**
     * @see https://developer.yahoo.com/social/rest_api_guide/http-response-codes.html
     * @param int $code
     * @return string
     */
    protected function getResponseDescription(int $code):string
    {
        $codes = [
            // Informational 1xx
            100 => 'Continue',
            101 => 'Switching Protocols',

            // Success 2xx
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',

            // Redirection 3xx
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found', // 1.1
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',

            // 306 is deprecated but reserved
            307 => 'Temporary Redirect',

            // Client Error 4xx
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            422 => 'Unprocessable Entity',

            // Server Error 5xx
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported',
            509 => 'Bandwidth Limit Exceeded'
        ];

        return $codes[$code] ?? 'Unknown Status Code';
    }
}

<?php
namespace PhalconRest\Util;

/**
 * where caught HTTP Exceptions go to die
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
     * http response code
     *
     * @var int
     */
    protected $code;

    private $errorStore;

    /**
     *
     * @param string $message
     *            required user friendly message to return to the requestor
     * @param string $code
     *            required HTTP response code
     * @param array $errorArray
     *            list of optional properites to set on the error object
     */
    public function __construct($title, $code, $errorList)
    {
        // store general error data
        $this->errorStore = new \PhalconRest\Util\ErrorStore($errorList);
        $this->errorStore->title = $title;
        
        // store HTTP specific data
        $this->code = $code;
        
        $this->response = $this->getResponseDescription($code);
        $this->di = \Phalcon\DI::getDefault();
    }

    /**
     *
     * @return void|boolean
     */
    public function send()
    {
        $output = new \PhalconRest\API\Output();
        $output->setStatusCode($this->code, $this->response);
        $output->sendError($this->errorStore);
        return true;
    }

    /**
     *
     * see also: https://developer.yahoo.com/social/rest_api_guide/http-response-codes.html
     *
     * @param unknown $code            
     * @return string
     */
    protected function getResponseDescription($code)
    {
        $codes = array(
            
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
        );
        
        $result = (isset($codes[$code])) ? $codes[$code] : 'Unknown Status Code';
        
        return $result;
    }
}
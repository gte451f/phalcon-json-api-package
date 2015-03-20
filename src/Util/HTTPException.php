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
     *
     * @var unknown
     */
    public $devMessage;

    /**
     * array of additional value that can be passed to the exception
     * 
     * @var array
     */
    public $errorArray;

    /**
     *
     * @var unknown
     */
    public $errorCode;

    /**
     *
     * @var unknown
     */
    public $response;

    /**
     *
     * @var string
     */
    public $additionalInfo;

    /**
     *
     * @param string $message            
     * @param string $code            
     * @param array $errorArray            
     */
    public function __construct($message, $code, $errorArray)
    {
        $this->message = $message;
        $this->errorArray = $errorArray;
        $this->devMessage = @$errorArray['dev'];
        $this->errorCode = @$errorArray['internalCode'];
        $this->code = $code;
        $this->additionalInfo = @$errorArray['more'];
        $this->response = $this->getResponseDescription($code);
        $this->di = \Phalcon\DI::getDefault();
        
        // pull from messageBag if no explicit devMessage is provided
        if (is_null($this->devMessage)) {
            $messageBag = $this->di->getMessageBag();
            $this->devMessage = $messageBag->getString();
        }
    }

    /**
     *
     * @return void|boolean
     */
    public function send()
    {
        $res = $this->di->get('response');
        $req = $this->di->get('request');
        
        // query string, filter, default
        if (! $req->get('suppress_response_codes', null, null)) {
            $res->setStatusCode($this->getCode(), $this->response)
                ->sendHeaders();
        } else {
            $res->setStatusCode('200', 'OK')->sendHeaders();
        }
        
        $error = array(
            'errorCode' => $this->getCode(),
            'userMessage' => $this->getMessage(),
            'devMessage' => $this->devMessage,
            'more' => $this->additionalInfo,
            'applicationCode' => $this->errorCode
        );
        
        // alter type based on what was requested
        if (! $req->get('type') || $req->get('type') == 'json') {
            $response = new \PhalconRest\Responses\JSONResponse();
            $response->send($error, true);
            return;
        } else 
            if ($req->get('type') == 'csv') {
                $response = new \PhalconRest\Responses\CSVResponse();
                $response->send(array(
                    $error
                ));
                return;
            }
        
        // also route log to PHP server?
        error_log('HTTPException: ' . $this->getFile() . ' at ' . $this->getLine());
        
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
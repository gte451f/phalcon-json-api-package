<?php
namespace PhalconRest\Util;

/**
 * where caught HTTP Exceptions go to die
 *
 * @author jjenkins
 *        
 */
class ValidationException extends \Exception
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
     * @var array
     */
    private $validationArray = array();

    /**
     *
     * @param string $message            
     * @param string $code            
     * @param array $errorArray            
     */
    public function __construct($message, $errorArray, $validationArray)
    {
        $this->message = $message;
        $this->errorArray = $errorArray;
        $this->devMessage = @$errorArray['dev'];
        $this->errorCode = @$errorArray['internalCode'];
        $this->additionalInfo = @$errorArray['more'];
        
        $this->code = 400;
        $this->response = 'Bad Request';
        
        $this->di = \Phalcon\DI::getDefault();
        
        $this->validationArray = $validationArray;
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
        
        $validationList = array();
        foreach ($this->validationArray as $validation) {
            $validationList[] = array(
                'message' => $validation->getMessage(),
                'field' => $validation->getField(),
                'type' => $validation->getType()
            );
        }
        
        $error = array(
            'errorCode' => $this->getCode(),
            'userMessage' => $this->getMessage(),
            'devMessage' => $this->devMessage,
            'more' => $this->additionalInfo,
            'applicationCode' => $this->errorCode,
            'validationList' => $validationList
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
        error_log('ValidationException: ' . $this->getFile() . ' at ' . $this->getLine());
        
        return true;
    }
}
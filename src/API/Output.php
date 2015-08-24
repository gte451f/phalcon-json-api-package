<?php
namespace PhalconRest\API;

/**
 * Gather the results of script execution and output for browser consumption
 * Assumes JSON API is only output option
 * assumes this is used one time per script execution
 */
class Output extends \Phalcon\DI\Injectable
{

    /**
     * default value since we assume everything went just dandy
     * upto parent class to configure for better code
     */
    private $httpCode = 200;

    private $httpMessage = 'OK';

    public $errorStore = false;

    /**
     *
     * @var unknown
     */
    protected $snake = true;

    /**
     *
     * @var unknown
     */
    protected $envelope = true;

    /**
     *
     * @var boolean
     */
    protected $head = false;

    public function __construct()
    {
        $di = \Phalcon\DI::getDefault();
        $this->setDI($di);
    }

    /**
     * format result set for output to web browser
     *
     * @param array $records            
     * @param string $error            
     */
    public function send($records)
    {
        // Most devs prefer camelCase to snake_Case in JSON, but this can be overriden here
        if ($this->snake) {
            $records = $this->arrayKeysToSnake($records);
        }
        
        // stop timer and add to meta
        $timer = $this->di->get('stopwatch');
        $timer->end();
        $interval = round(($timer->laps[0]['total']) * 1000);
        $records['meta']['duration'] = $interval . ' milliseconds';
        
        $this->_send($records);
        return $this;
    }

    public function sendError(\PhalconRest\Util\ErrorStore $errorStore)
    {
        $message = array();
        $message['errors']['title'] = $errorStore->title;
        $message['errors']['code'] = $errorStore->code;
        $message['errors']['detail'] = $errorStore->more;
        $message['errors']['status'] = $this->httpCode;
        
        $config = $this->di->get('config');
        if ($config['application']['debugApp'] == true and isset($errorStore->dev)) {
            $message['errors']['meta']['developer_message'] = $errorStore->dev;
        }
        if (count($errorStore->validationList) > 0) {
            foreach ($errorStore->validationList as $validation) {
                $message['errors'][$validation->getField()] = $validation->getMessage();
            }
        }
        
        $this->_send($message);
        return $this;
    }

    private function _send($message)
    {
        // Error's come from HTTPException. This helps set the proper envelope data
        $response = $this->di->get('response');
        $response->setContentType('application/json');
        $response->setStatusCode($this->httpCode, $this->httpMessage)->sendHeaders();
        
        // HEAD requests are detected in the parent constructor.
        // HEAD does everything exactly the same as GET, but contains no body
        if (! $this->head) {
            $response->setJsonContent($message);
        }
        $response->send();
    }

    /**
     * should we convert array keys to snake_case?
     * otherwise array keys are left untouched
     *
     * @param bool $snake            
     * @return \PhalconRest\Responses\JSONResponse
     */
    public function convertSnakeCase($snake)
    {
        $this->snake = (bool) $snake;
        return $this; // for method chaining
    }

    /**
     * include an envelop as part of the response
     *
     * @param bool $envelope            
     * @return \PhalconRest\Responses\JSONResponse
     */
    public function useEnvelope($envelope)
    {
        $this->envelope = (bool) $envelope;
        return $this; // for method chaining
    }

    /**
     * In-Place, recursive conversion of array keys in snake_Case to camelCase
     *
     * @param array $snakeArray
     *            Array with snake_keys
     * @return no return value, array is edited in place
     */
    protected function arrayKeysToSnake(array $snakeArray)
    {
        foreach ($snakeArray as $k => $v) {
            if (is_array($v)) {
                $v = $this->arrayKeysToSnake($v);
            }
            $snakeArray[$this->snakeToCamel($k)] = $v;
            if ($this->snakeToCamel($k) != $k) {
                unset($snakeArray[$k]);
            }
        }
        return $snakeArray;
    }

    /**
     * Replaces underscores with spaces, uppercases the first letters of each word,
     * lowercases the very first letter, then strips the spaces
     *
     * @param string $val
     *            String to be converted
     * @return string Converted string
     */
    protected function snakeToCamel($val)
    {
        return str_replace(' ', '', lcfirst(ucwords(str_replace('_', ' ', $val))));
    }

    public function setStatusCode($code, $message)
    {
        $this->httpCode = $code;
        $this->httpMessage = $message;
    }
}

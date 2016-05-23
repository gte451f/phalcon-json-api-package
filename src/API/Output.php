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

    /**
     * hold a valid errorStore object
     *
     * @var \PhalconRest\Util\ErrorStore
     */
    public $errorStore = false;

    /**
     *
     * @var boolean
     */
    protected $snake = true;

    /**
     *
     * @var boolean
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
    public function send($result)
    {

        // Most devs prefer camelCase to snake_Case in JSON, but this can be overridden here
//        if ($this->snake) {
//            $records = $this->arrayKeysToSnake($records);
//        }

        // stop timer and add to meta
        if ($this->di->get('config')['application']['debugApp'] == true) {
            $timer = $this->di->get('stopwatch');
            $timer->end();

            $summary = [
                'total_run_time' => round(($timer->endTime - $timer->startTime) * 1000, 2) . ' ms',
                'laps' => []
            ];
            foreach ($timer->laps as $lap) {
                $summary['laps'][$lap['name']] = round(($lap['end'] - $lap['start']) * 1000, 2) . ' ms';
            }
            $result->addMeta('stopwatch', $summary);
        }

        $this->_send($result->outputJSON());
        return $this;
    }

    /**
     * process an errorStore into a simple message
     *
     * @param \PhalconRest\Util\ErrorStore $errorStore
     * @return \PhalconRest\API\Output
     */
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

    /**
     * for a given string message, prepare a basic json response for the browser
     *
     * @param string $message
     */
    private function _send($message)
    {
        // Error's come from HTTPException. This helps set the proper envelope data
        $response = $this->di->get('response');
        $response->setContentType('application/json');
        $response->setStatusCode($this->httpCode, $this->httpMessage)->sendHeaders();

        // HEAD requests are detected in the parent constructor.
        // HEAD does everything exactly the same as GET, but contains no body
        if (!$this->head) {
            $response->setJsonContent($message);
        }
        $response->send();
    }

    /**
     * should we convert array keys to snake_case?
     * otherwise array keys are left untouched
     *
     * @param bool $snake
     * @return object $this
     */
    public function convertSnakeCase($snake)
    {
        $this->snake = (bool)$snake;
        return $this; // for method chaining
    }

    /**
     * include an envelop as part of the response
     *
     * @param bool $envelope
     * @return object $this
     */
    public function useEnvelope($envelope)
    {
        $this->envelope = (bool)$envelope;
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

    /**
     * simple setter for properties
     *
     * @param int $code
     * @param string $message
     */
    public function setStatusCode($code, $message)
    {
        $this->httpCode = $code;
        $this->httpMessage = $message;
    }
}

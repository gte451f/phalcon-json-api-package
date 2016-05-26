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
     * add any final meta data
     *
     * @param $result
     * @return $this
     */
    public function send($result)
    {
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
        // shouldn't we get out now?
        exit();
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

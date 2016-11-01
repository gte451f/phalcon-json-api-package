<?php
namespace PhalconRest\API;

use Phalcon\Mvc\Model\Message;
use PhalconRest\Exception\ErrorStore;
use PhalconRest\Result\Result;

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
     * @var ErrorStore
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
     * @param Result $result
     * @return void
     */
    public function send(Result $result)
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
     * Process an errorStore into a simple message.
     * An errorStore may contains a single error message; in such cases, the single message is wrapped up into an array
     * in order to conform to the JSON API spec.
     * Validation errors, otherwise, can possibly yield multiple objects inside the array, with details on each
     * field's errors.
     *
     * @param ErrorStore $errorStore
     * @return \PhalconRest\API\Output
     */
    public function sendError(ErrorStore $errorStore)
    {
        $appConfig = $this->di->get('config')['application'];

        if (count($errorStore->validationList) > 0) {
            $inflector = $this->di->get('inflector');

            $result = [
                'errors' => array_map(function(Message $validation) use ($errorStore, $appConfig, $inflector) {
                    $field = $inflector->normalize($validation->getField(), $appConfig['propertyFormatTo']);
                    return [
    //                    'status' => $this->httpCode, //FIXME: is this even needed?
                        'code'   => $errorStore->code,
                        'title'  => $errorStore->title,
                        'detail' => $validation->getMessage(),
                        'source' => ['pointer' => "/data/attributes/$field"],
                        'meta'   => ['field' => $field]
                    ];
                }, $errorStore->validationList)
            ];
        } else {
            $error = [
                'title'  => $errorStore->title,
                'code'   => $errorStore->code,
                'detail' => $errorStore->more,
                'status' => $this->httpCode
            ];

            if ($appConfig['debugApp'] && isset($errorStore->dev)) {
                $error['meta'] = ['developer_message' => $errorStore->dev];
            }

            // wrap single error into an object key'd by "errors", conforming to JSON-API spec
            $result = ['errors' => [$error]];
        }

        $this->_send($result);
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

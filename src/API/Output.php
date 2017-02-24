<?php
namespace PhalconRest\API;

use Phalcon\Http\Response;
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

    /**
     * @var string
     */
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
     * @param Result $result Could be null in case of 204 results
     * @return void
     */
    public function send(Result $result = null)
    {
        if ($result) {
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
        } else {
            $this->setStatusCode(204);
            $this->_send('');
        }

        // shouldn't we get out now?
        exit();
    }

    /**
     * for a given string message, prepare a basic json response for the browser
     *
     * @param string $message
     */
    private function _send($message)
    {
        // Errors come from HTTPException. This helps set the proper envelope data
        /** @var Response $response */
        $response = $this->di->get('response');
        $response->setStatusCode($this->httpCode, $this->httpMessage);

        // HEAD does everything exactly the same as GET, but contains no body
        // empty responses (such as a 204 result) should also skip JSON configuration
        if ($this->head || !$message) {
            $response->setContentType(null); //forces content-type to not be sent
        } else {
            $response->setJsonContent($message);
        }

        $response->send();
    }

    /**
     * simple setter for properties
     *
     * @param int $code
     * @param string $message
     */
    public function setStatusCode($code, $message = null)
    {
        $this->httpCode = $code;
        $this->httpMessage = $message;
    }

    public function getStatusCode()
    {
        return $this->httpCode;
    }
}

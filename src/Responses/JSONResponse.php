<?php
namespace PhalconRest\Responses;

/**
 * reeturn output from server to client in JSON format
 */
class JSONResponse extends Response
{

    protected $snake = true;

    protected $envelope = true;

    public function send($records, $error = false)
    {
        
        // Error's come from HTTPException. This helps set the proper envelope data
        $response = $this->di->get('response');
        $success = ($error) ? 'ERROR' : 'SUCCESS';
        
        // If the query string 'envelope' is set to false, do not use the envelope.
        // Instead, return headers.
        $request = $this->di->get('request');
        if ($request->get('envelope', null, null) === 'false') {
            $this->envelope = false;
        }
        
        // Most devs prefer camelCase to snake_Case in JSON, but this can be overriden here
        if ($this->snake) {
            $records = $this->arrayKeysToSnake($records);
        }
        
        $etag = md5(serialize($records));
        
        if ($this->envelope) {
            // Provide an envelope for JSON responses. '_meta' and 'records' are the objects.
            $message = array();
            $message['_meta'] = array(
                'status' => $success,
                'count' => ($error) ? 1 : count($records)
            );
            
            // Handle 0 record responses, or assign the records
            if ($message['_meta']['count'] === 0) {
                // This is required to make the response JSON return an empty JS object. Without
                // this, the JSON return an empty array: [] instead of {}
                $message['records'] = new \stdClass();
            } else {
                $message['records'] = $records;
            }
        } else {
            $response->setHeader('X-Record-Count', count($records));
            $response->setHeader('X-Status', $success);
            $message = $records;
        }
        
        $response->setContentType('application/json');
        $response->setHeader('E-Tag', $etag);
        
        // stop timer and add to meta
        $timer = $this->di->get('stopwatch');
        // $timer = $di->getShared('stopwatch');
        $foo = $timer->end();
        $interval = round(($foo['total']) * 1000);
        $message['meta']['duration'] = $interval . ' milliseconds';
        
        // HEAD requests are detected in the parent constructor. HEAD does everything exactly the
        // same as GET, but contains no body.
        if (! $this->head) {
            $response->setJsonContent($message);
        }
        
        $response->send();
        
        return $this;
    }

    /**
     *
     * @param bool $snake            
     * @return \PhalconRest\Responses\JSONResponse
     */
    public function convertSnakeCase($snake)
    {
        $this->snake = (bool) $snake;
        return $this;
    }

    /**
     *
     * @param unknown $envelope            
     * @return \PhalconRest\Responses\JSONResponse
     */
    public function useEnvelope($envelope)
    {
        $this->envelope = (bool) $envelope;
        return $this;
    }
}

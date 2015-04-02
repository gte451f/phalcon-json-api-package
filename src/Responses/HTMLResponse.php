<?php
namespace PhalconRest\Responses;

/**
 * return output from server to client in pretty printed HTML format
 * useful for debugging and development
 */
class HTMLResponse extends Response
{

    private $config;

    protected $snake = true;

    protected $envelope = true;

    public function __construct()
    {
        parent::__construct();
        
        // load required objects
        $this->config = $this->di->getShared('config');
        $this->odata = $this->di->getShared('odata');
    }

    public function send($records, $error = false)
    {
        $record_count = count($records);
        
        $this->odata->records = $records;
        
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
        
        // some sort of caching thing?
        $etag = md5(serialize($records));
        $response->setHeader('E-Tag', $etag);
        
        $message = $this->odata->output();
        
        // optional JS code
        $script = ($record_count > 500) ? '' : '<script src="' . $this->config['application']['publicUrl'] . '/js/run_prettify.js"></script>';
        $html_header = '<html><head>' . $script . '</head><body>';
        $html_footer = "</body></html>";
        
        if (! $this->head) {
            $response->setContent($html_header . '<pre class="prettyprint linenums">' . print_r($message, true) . "</pre>" . $html_footer);
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

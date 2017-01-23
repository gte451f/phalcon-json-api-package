<?php
namespace PhalconRest\API;

use Phalcon\Http\ResponseInterface;

trait CORSTrait
{

    protected $CORSMethodsBase   = 'GET, POST, OPTIONS, HEAD';
    protected $CORSMethodsSingle = 'GET, PUT, PATCH, DELETE, OPTIONS, HEAD';

    /**
     * Provides a base CORS policy for routes like '/users' that represent a Resource's base url
     * Origin is allowed from all urls.
     * Setting it here using the Origin header from the request
     * allows multiple Origins to be served. It is done this way instead of with a wildcard '*'
     * because wildcard requests are not supported when a request needs credentials.
     */
    public function optionsBase()
    {
        $this->setCorsHeaders($this->response, $this->CORSMethodsBase);
        return true;
    }

    /**
     * Provides a CORS policy for routes like '/users/123' that represent a specific resource
     */
    public function optionsOne()
    {
        $this->setCorsHeaders($this->response, $this->CORSMethodsSingle);
        return true;
    }

    private function setCorsHeaders(ResponseInterface $response, string $methods)
    {
        $config = $this->getDI()->get('config');
        $response->setHeader('Access-Control-Allow-Methods', $methods);
        $response->setHeader('Access-Control-Allow-Origin', $config['application']['corsOrigin']);
        $response->setHeader('Access-Control-Allow-Credentials', 'true');
        $response->setHeader('Access-Control-Allow-Headers', 'Origin, X-Requested-With, Content-Type, X-Authorization, X-CI-KEY');
        $response->setHeader('Access-Control-Max-Age', '86400');
    }
}
<?php

use PhalconRest\Exception\HTTPException;
use PhalconRest\Result\Result;

/**
 * Our application is a Micro application, so we must explicitly define all the routes.
 * For APIs, this is ideal. This is as opposed to the more robust MVC Application
 *
 * @var $app
 */
$app = new Phalcon\Mvc\Micro();
$app->setDI($di);

/**
 * Before every request:
 * Returning true in this function resumes normal routing.
 * Returning false stops any route from executing.
 */
$app->before(function () use ($app, $di) {
    // set standard CORS headers before routing just in case no valid route is found
    $config = $di->get('config');
    $app->response->setHeader('Access-Control-Allow-Origin', $config['application']['corsOrigin']);
    return true;
});

/**
 * Mount all of the collections, which makes the routes active.
 */
$T->lap('Loading Routes');
foreach ($di->get('collections') as $collection) {
    $app->mount($collection);
}
$T->lap('Processing Request');

/**
 * The base route return the list of defined routes for the application.
 * This is not strictly REST compliant, but it helps to base API documentation off of.
 * By calling this, you can quickly see a list of all routes and their methods.
 */
$app->get('/', function () use ($app, $di) {
    $routes = $app->getRouter()->getRoutes();
    $routeDefinitions = [
        'GET' => [],
        'POST' => [],
        'PUT' => [],
        'PATCH' => [],
        'DELETE' => [],
        'HEAD' => [],
        'OPTIONS' => []
    ];
    foreach ($routes as $route) {
        $method = $route->getHttpMethods() ?? 'any';
        $routeDefinitions[$method][] = $route->getPattern();
    }
    /** @var Result $result */
    $result = $di->get('result', []);
    $result->outputMode = 'other';
    $result->addPlains($routeDefinitions);
    return $result;
});

/**
 * After a route is run, usually when its Controller returns a final value,
 * the application runs the following function which actually sends the response to the client.
 *
 * The default behavior is to send the Controller's returned value to the client as JSON.
 * However, by parsing the request query string's 'type' parameter, it is easy to install
 * different response type handlers.
 */
$app->after(function () use ($app, $di) {
    $method = $app->request->getMethod();

    /**
     * if the system was dealing with an atomic request -> we commit/rollback the transaction
     * depending if any problems were encountered or not
     */
    $store = $di->get('store');
    $is_atomic = $store->get('transaction_is_atomic');
    if ($is_atomic) {
        $rollback_transaction = $store->get('rollback_transaction');
        $db = $di->get('db');
        if ($rollback_transaction) {
            $db->rollback();
        } else {
            if (intval($app->response->getStatusCode()) >= 400) {
                $db->rollback();
            } else {
                $db->commit();
            }
        }
    }

    // Results returned from the route's controller passed to output class for delivery, in case
    // something else didn't send it already (useful for plain-text actions)
    if (!$app->response->isSent()) {
        $output = new \PhalconRest\API\Output();

        if ($output->getStatusCode() == 200) { //if it's still the default one, let's override in some cases
            switch ($method) {
                case 'OPTIONS':
                    $app->response->setStatusCode('200', 'OK');
                    $app->response->send();
                    return;

                case 'DELETE': //FIXME: usually this is true, but not all DELETE requests would come without content
                    $app->response->setStatusCode('204', 'No Content');
                    $app->response->send();
                    return;

                case 'POST':
                    //FIXME: not all POST requests actually create a new resource. 200 should be used otherwise
                    $output->setStatusCode('201', 'Created');
                    break;
            }
        }

        $output->send($app->getReturnedValue());
    }
});

/**
 * The notFound service is the default handler function that runs when no route was matched.
 * We set a 404 here unless there's a suppress error codes.
 */
$app->notFound(function () use ($app) {
    throw new HTTPException('Not Found.', 404, [
        'dev' => 'That route was not found on the server.',
        'code' => '4',
        'more' => 'Check route for misspellings.'
    ]);
});
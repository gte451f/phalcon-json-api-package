<?php

use Phalcon\Mvc\Micro\Collection;
use \PhalconRest\Exception\HTTPException;

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
    $routes = $app->getRouter()
        ->getRoutes();
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
        $method = $route->getHttpMethods();
        $routeDefinitions[$method][] = $route->getPattern();
    }
    $result = $di->get('result', []);
    $result->outputMode = 'other';

    foreach ($routeDefinitions as $key => $value) {
        $result->setPlain($key, $value);
    }
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
$app->after(function () use ($app) {
    $method = $app->request->getMethod();
    $output = new \PhalconRest\API\Output();

    switch ($method) {
        case 'OPTIONS':
            $app->response->setStatusCode('200', 'OK');
            $app->response->send();
            return;
            break;

        case 'DELETE':
            $app->response->setStatusCode('204', 'No Content');
            $app->response->send();
            return;
            break;

        case 'POST':
            $output->setStatusCode('201', 'Created');
            break;
    }

    // Results returned from the route's controller passed to output class for delivery
    $output->send($app->getReturnedValue());
});

/**
 * The notFound service is the default handler function that runs when no route was matched.
 * We set a 404 here unless there's a suppress error codes.
 */
$app->notFound(function () use ($app) {
    throw new HTTPException('Not Found.', 404, array(
        'dev' => 'That route was not found on the server.',
        'code' => '4',
        'more' => 'Check route for misspellings.'
    ));
});
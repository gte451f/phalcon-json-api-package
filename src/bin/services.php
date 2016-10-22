<?php
/** @var array $config defined outside (probably at bin/config.php) */

// Factory Default loads all services by default....
use Phalcon\DI;

// PhalconRest libraries
use PhalconRest\Request\Request as Request;
use PhalconRest\Util\Inflector;

// for password and credit card encryption
use PHPBenchTime\Timer;
use Phalcon\Logger\Adapter\File as FileLogger;

$T = new Timer();
$T->start('Booting App');

/**
 * The DI is our direct injector.
 * It will store pointers to all of our services
 * and we will insert it into all of our controllers.
 * @var $di DI\FactoryDefault\Cli|DI\FactoryDefault
 */

$di = (PHP_SAPI == 'cli') ? new DI\FactoryDefault\Cli : new DI\FactoryDefault;

// load the proper request object depending on the specified format
$di->setShared('request', function () use ($config) {
    if (isset($config['application']['outputFormat'])) {
        $outputFormat = $config['application']['outputFormat'];
    } else {
        $outputFormat = 'JsonApi';
    }
    $classpath = '\PhalconRest\Request\Adapters\\' . $outputFormat;
    $request = new $classpath();
    $request->defaultCaseFormat = $config['application']['propertyFormatFrom'];
    return $request;
});

// stopwatch service to track
$di->setShared('stopwatch', function () use ($T) {
    return $T;
});

$di->setShared('logger', function () use ($config) {
    return new FileLogger($config['application']['loggingDir'] . date('d_m_y') . '-api.log');
});

if (PHP_SAPI != 'cli') {
    /**
     * Return array of the Collections, which define a group of routes, from
     * routes/collections.
     * These will be mounted into the app itself later.
     */
    $di->set('collections', function () {
        $collections = include('../app/routes/routeLoader.php');
        return $collections;
    });
}

/**
 * $di's setShared method provides a singleton instance.
 * If the second parameter is a function, then the service is lazy-loaded
 * on its first instantiation.
 */
$di->setShared('config', function () use ($config) {
    return $config;
});

// As soon as we request the session service, it will be started.
$di->setShared('session', function () {
    $session = new \Phalcon\Session\Adapter\Files();
    $session->start();
    return $session;
});

// general purpose cache used to store complex or database heavy structures
$di->setShared('cache', function () use ($config) {
    // Cache data for one hour by default
    $frontCache = new \Phalcon\Cache\Frontend\Data(array(
        'lifetime' => 60
    ));

    // Create the component that will cache "Data" to a "File" backend
    // Set the cache file directory - important to keep the "/" at the end of
    // of the value for the folder
    $cache = new \Phalcon\Cache\Backend\File($frontCache, array(
        'cacheDir' => $config['application']['cacheDir']
    ));
    return $cache;
});


// load a result adapter based on what is configured in the app
$di->setShared('result', function () use ($config) {
    if (isset($config['application']['outputFormat'])) {
        $outputFormat = $config['application']['outputFormat'];
    } else {
        $outputFormat = 'JsonApi';
    }
    $classpath = '\PhalconRest\Result\Adapters\\' . $outputFormat . '\Result';
    return new $classpath();
});


// load a data adapter based on what is configured in the app
$di->set(
    "data",
    [
        "className" => "\\PhalconRest\\Result\\Adapters\\" . $config['application']['outputFormat'] . "\\Data",
        "arguments" => [
            ["type" => "parameter"],
            ["type" => "parameter"],
            ["type" => "parameter"],
            ["type" => "parameter"]
        ]
    ]
);


$di->setShared('modelsManager', function () {
    return new \Phalcon\Mvc\Model\Manager();
});

$di->set('modelsMetadata', function () use ($config) {
    $metaData = new \Phalcon\Mvc\Model\Metadata\Files(array(
        'metaDataDir' => $config['application']['tempDir']
    ));
    return $metaData;
});

// used in model?
$di->setShared('memory', function () {
    return new \Phalcon\Mvc\Model\MetaData\Memory();
});


$di->set('queryBuilder', [
    'className' => '\\PhalconRest\\API\\QueryBuilder',
    'arguments' => [
        ['type' => 'parameter'],
        ['type' => 'parameter'],
        ['type' => 'parameter']
    ]
]);


// phalcon inflector?
$di->setShared('inflector', function () {
    return new Inflector();
});

/**
 * If our request contains a body, it has to be valid JSON.
 * This parses the body into a standard Object and makes that available from the DI.
 * If this service is called from a function, and the request body is not valid JSON or is empty,
 * the program will throw an Exception.
 */
$di->setShared('requestBody', function () {
    $in = file_get_contents('php://input');
    $in = json_decode($in, false);

    // JSON body could not be parsed, throw exception
    if ($in === null) {
        throw new HTTPException('There was a problem understanding the data sent to the server by the application.',
            409, array(
                'dev' => 'The JSON body sent to the server was unable to be parsed.',
                'code' => '5',
                'more' => ''
            ));
    }
    return $in;
});
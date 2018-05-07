<?php
/** @var array $config defined outside (probably at bin/config.php) */

use Phalcon\Cache;
use Phalcon\Di\FactoryDefault; // Factory Default loads all services by default....
use Phalcon\Logger\Adapter\File as FileLogger;
use PhalconRest\Exception\HTTPException;
use PhalconRest\Util\Inflector;
use PHPBenchTime\Timer;

$T = new Timer();
$T->start('Booting App');

/**
 * The DI is our dependency injector.
 * It will store pointers to all of our services
 * and we will insert it into all of our controllers.
 * @var $di FactoryDefault\Cli|FactoryDefault
 */

$di = (PHP_SAPI == 'cli') ? new FactoryDefault\Cli : new FactoryDefault;

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
    $di->set('collections', function () use ($config) {
        return $config['application']['maintenance'] ?
            include('maintenanceRoute.php') :
            include('../app/routes/routeLoader.php');
    });
}

// return a single copy of the config array
$di->setShared('config', function () use ($config) {
    return $config;
});


// As soon as we request the session service, it will be started.
$di->setShared('session', function () {
    $session = new \Phalcon\Session\Adapter\Files();
    $session->start();
    return $session;
});

/**
 * Generates one caching instance, or an array of, given the "cache" config option.
 * If none is found, a default FileCache is created.
 *
 * Explanation about the "cache" config entry:
 *   It should be an array of arrays, as follows:
 *   First, the zeroed entry is the cache class; the rest are config options for that cache entry.
 *   If "front" option is specified, it should follow the same structure (0 => class, rest => options); the default is
 *   "Frontend\Data" (what is needed in most cases). The storage path is taken from "fileStorage" config if needed.
 * Example:
 * 'cache' => [
 *      [Cache\Backend\Redis::class, 'front' => ['lifetime' => 300]], //5 min
 *      [Cache\Backend\File::class, 'cacheDir' => '/tmp/cache'],
 *      [
 *          Cache\Backend\File::class,
 *          'front'    => [Cache\Frontend\Base64, 'lifetime' => 172800], //2 days, in seconds
 *          'cacheDir' => '/tmp/cache-blobs'
 *      ],
 * ]
 *
 * @return Cache\Multiple|Cache\BackendInterface
 */
$di->setShared('cache', function () use ($config) {
    $instances = array_map(function ($cacheConfig) use ($config) {
        //creates a caching frontend for this interface
        if (isset($cacheConfig['front'])) {
            //gets the type or defaults to the most common frontend: serialize()-based
            $frontType = array_shift($cacheConfig['front']) ?: Cache\Frontend\Data::class;
            $frontOptions = $cacheConfig['front'];
            unset($cacheConfig['front']);
        } else {
            $frontType = new Cache\Frontend\Data;
            $frontOptions = [];
        }
        if (!isset($frontOptions['lifetime'])) {
            $frontOptions['lifetime'] = 60 * 60; //2 hours is the default lifetime if none is given
        }
        $front = new $frontType($frontOptions);

        //now we identify the backend type. Fallback to an ephemeral caching if nothing is given
        $type = array_shift($cacheConfig) ?: Cache\Backend\Memory::class;

        //default values / house-keeping for File-based caching
        if ($type == Cache\Backend\File::class && !isset($cacheConfig['cacheDir'])) {
            $cacheConfig['cacheDir'] = $config['application']['cacheDir'];
        }

        return new $type($front, $cacheConfig);
    }, $config['cache'] ?? [\Phalcon\Cache\Backend\File::class]);

    //packs the cache instances in a Multiple cache if needed
    return (sizeof($instances) > 1) ? new Cache\Multiple($instances) : current($instances);
});

// load a result adapter based on what is configured in the app
//$di->setShared('result', function () use ($config) {
//    if (isset($config['application']['outputFormat'])) {
//        $outputFormat = $config['application']['outputFormat'];
//    } else {
//        $outputFormat = 'JsonApi';
//    }
//    $classpath = '\PhalconRest\Result\Adapters\\' . $outputFormat . '\Result';
//    return new $classpath();
//});

$di->setShared('result', [
    'className' => "\\PhalconRest\\Result\\Adapters\\" . $config['application']['outputFormat'] . "\\Result",
    'arguments' => [
        ['type' => 'parameter']
    ]
]);

// load a data adapter based on what is configured in the app
$di->set('data', [
    'className' => "\\PhalconRest\\Result\\Adapters\\" . $config['application']['outputFormat'] . "\\Data",
    'arguments' => [
        ['type' => 'parameter'],
        ['type' => 'parameter'],
        ['type' => 'parameter'],
        ['type' => 'parameter']
    ]
]);

$di->setShared('modelsManager', function () {
    return new \Phalcon\Mvc\Model\Manager();
});

$di->set('modelsMetadata', function () use ($config) {
    $metaData = new \Phalcon\Mvc\Model\Metadata\Files([
        'metaDataDir' => $config['application']['tempDir']
    ]);
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
            409, [
                'dev' => 'The JSON body sent to the server was unable to be parsed.',
                'code' => '5',
                'more' => json_last_error() . ' - ' . json_last_error_msg()
            ]);
    }

    return $in;
});

// hold custom variables
$di->set('store', function () {
    $myObject = new class {
        private $store = [];
        public function update($key, $value){
            $this->store[$key] = $value;
        }
        public function get($key){
            if (array_key_exists($key, $this->store)) {
                return $this->store[$key];
            }
            return null;
        }
    };
    return $myObject;

}, true);
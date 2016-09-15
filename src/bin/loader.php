<?php

// read in config values from API and currently defined environment
require_once API_PATH . 'bin/config.php';

/*
 * Interact with Phalcon auto loader, also load composer related vendor/* files
 * This is the best way I've found to use Phalcon's autoload while seamlessly incorporating Composer libs
 *
 */
use Phalcon\Loader;
use PhalconRest\Libraries\Formatters;

/**
 * By default, namespaces are assumed to be the same as the path.
 * This function allows us to assign namespaces to alternative folders.
 * It also puts the classes into the PSR-0 autoLoader.
 */
$loader = new Loader();

$nameSpaces = array(
    'PhalconRest\Models' => $config['application']['modelsDir'],
    'PhalconRest\Entities' => $config['application']['entitiesDir'],
    'PhalconRest\Controllers' => $config['application']['controllersDir'],
    'PhalconRest\Exceptions' => $config['application']['exceptionsDir'],
    'PhalconRest\Libraries' => $config['application']['librariesDir'],
    'PhalconRest\Responses' => $config['application']['responsesDir']
);

// load Composer Namespaces
$map = require COMPOSER_PATH . 'composer/autoload_namespaces.php';
foreach ($map as $nameSpace => $path) {
    $nameSpace = trim($nameSpace, '\\');
    if (!isset($namespaces[$nameSpace])) {
        // use the first key in the path array for now....
        $nameSpaces[$nameSpace] = $path[0];
    }
}
$loader->registerNamespaces($nameSpaces);

// load Composer Classes
$classMap = require COMPOSER_PATH . 'composer/autoload_classmap.php';
$loader->registerClasses($classMap);

// load Composer Files
// careful with this one since it pollutes the global name space
$autoLoadFilesPath = COMPOSER_PATH . 'composer/autoload_files.php';
if (file_exists($autoLoadFilesPath)) {
    $files = require $autoLoadFilesPath;
    foreach ($files as $file) {
        require_once $file;
    }
}

$loader->register();

// now init Phalcon DI object with services required by the core API
require_once "services.php";

// load logic to bootstrap the web app
if (PHP_SAPI == 'cli') {
    require_once 'bootstrap/cli.php';
} else {
    require_once 'bootstrap/web.php';

    // include special error handling logic for JSON output
    require "errorHandler.php";
}
<?php
/**
 * load low level helper here so it also works when used in conjunction with phalcon devtools
 */
require_once API_PATH . 'bin/base.php';

// your main application config file
// you can override these values with an environmental specific file in app/config/FILE.php
$config = [
    'application' => [
        // the path to the main directory holding the application
        'appDir' => APPLICATION_PATH,
        // path values to commonly expected api files
        "controllersDir" => APPLICATION_PATH . 'controllers/',
        "modelsDir" => APPLICATION_PATH . 'models/',
        "entitiesDir" => APPLICATION_PATH . 'entities/',
        "responsesDir" => APPLICATION_PATH . 'responses/',
        "librariesDir" => APPLICATION_PATH . 'libraries/',

        // is this used?
        "exceptionsDir" => APPLICATION_PATH . 'exceptions/',

        // base string after FQDN.../api/v1 or some such
        // set to simple default and expect app to override
        'baseUri' => '/',
        'basePath' => '/',
        // should the api return additional meta data and enable additional server logging?
        'debugApp' => true,
        // where to store cache related files?
        'cacheDir' => '/tmp/',
        // where should system temp files go?
        'tempDir' => '/tmp/',
        // where should app generated logs be stored?
        'loggingDir' => '/tmp/',

        // how should property names be formatted in results?
        // possible values are camel, snake, dash and none
        // none means perform no processing on the final output
        'propertyFormatTo' => 'dash',

        // how are your existing database field name formatted?
        // possible values are camel, snake, dash
        // none means perform no processing on the incoming values
        'propertyFormatFrom' => 'snake',

        // would also accept any FOLDER name in Result\Adapters
        'outputFormat' => 'JsonApi'
    ],

    // location to various code sources
    'namespaces' => [
        'models' => 'PhalconRest\Models\\',
        'controllers' => 'PhalconRest\Controllers\\',
        'libraries' => 'PhalconRest\Libraries\\',
        'entities' => 'PhalconRest\Entities\\',

        // what is the default entity to be loaded when no other is specified?
        'defaultEntity' => '\PhalconRest\API\Entity'
    ],

    // is security enabled for this app?
    'security' => true,

    // a series of experimental features
    // this section may be left blank
    'feature_flags' => [

    ]
];

// incorporate the correct environmental config file
// TODO throw error if no file is found?
$overridePath = APPLICATION_PATH . 'config/config.php';
if (file_exists($overridePath)) {
    $config = array_merge_recursive_replace($config, require($overridePath));
} else {
    throw new Exception("Invalid Environmental Config!  Could not load the specific config file. Your environment is:"
        . APPLICATION_ENV . " but not matching file was found in /app/config/", 23897293759275);
}

// makes no sense to convert from like to like
// can't throw Exception, not sure why
if ($config['application']['propertyFormatTo'] == $config['application']['propertyFormatFrom']) {
    throw new Exception('The API attempted to normalize from one format to the same format', 21345);
}

return new \Phalcon\Config($config);
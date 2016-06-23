<?php
// define security roles
define("PORTAL_USER", "Portal - User");
define("ADMIN_USER", "System - Administrator");

/**
 * load low level helper here so it also works when used in conjunction with phalcon devtools
 */
require_once API_PATH . 'bin/base.php';

// your main application config file
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

    ],
    'namespaces' => [
        'models' => 'PhalconRest\Models\\',
        'controllers' => 'PhalconRest\Controllers\\',
        'libraries' => 'PhalconRest\Libraries\\',
        'entities' => 'PhalconRest\Entities\\'
    ],
    // is security enabled for this app?
    'security' => true,
    // a series of experimental features
    'feature_flags' => [
        // run this in the main query instead of pulling as individual queries
        'fastBelongsTo' => false,
        'fastHasMany' => false
    ]
];

// incorporate the correct environmental config file
// TODO throw error if no file is found?
$overridePath = APPLICATION_PATH . 'config/' . APPLICATION_ENV . '.php';
if (file_exists($overridePath)) {
    $config = array_merge_recursive_replace($config, require($overridePath));
} else {
    throw new HTTPException("Fatal Exception Caught.", 500, array(
        'dev' => "Invalid Environmental Config!  Could not load the specific config file.  Your environment is: "
            . APPLICATION_ENV . " but not matching file was found in /app/config/",
        'code' => '23897293759275'
    ));
}

return new \Phalcon\Config($config);
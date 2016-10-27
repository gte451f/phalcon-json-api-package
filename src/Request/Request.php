<?php
namespace PhalconRest\Request;

use \PhalconRest\Util\Inflector;
use PhalconRest\API\BaseModel;

/**
 * extend to provide easy case conversion on incoming data
 * take json data and convert to either snake or camel case
 * depends on CaesConversion library
 */
abstract class Request extends \Phalcon\Http\Request
{

    /**
     * in what format will PUT fields be named?
     *
     * snake|camel|false
     *
     * @var string
     */
    public $defaultCaseFormat = false;


    /**
     * function that should be implemented to perform additional processing that "fixes" data
     *
     * @param $post
     * * @param BaseModel $model
     * @return mixed
     */
    abstract function mungeData($post, BaseModel $model);

    /**
     * @param $name
     * @param BaseModel $model
     * @return mixed
     */
    abstract function getJson($name, \PhalconRest\API\BaseModel $model);


    /**
     * extend to hook up possible case conversion
     *
     * @param string $name
     * @param string $filters
     * @param string $defaultValue
     * @return multiple
     */
    public function getPut(
        $name = null,
        $filters = null,
        $defaultValue = null,
        $notAllowEmpty = null,
        $noRecursive = null
    ) {
        // perform parent function
        $request = parent::getPut($name, $filters, $defaultValue);

        // special handling for array requests, for individual inputs return what is request
        if (is_array($request) and $this->defaultCaseFormat != false) {
            return $this->convertCase($request);
        } else {
            return $request;
        }
    }

    /**
     * extend to hook up possible case conversion
     *
     * @param string $name
     * @param string $filters
     * @param string $defaultValue
     * @return object
     */
    public function getPost($name = null, $filters = null, $defaultValue = null)
    {
        // perform parent function
        $request = parent::getPost($name, $filters, $defaultValue);

        // special handling for array requests, for individual inputs return what is request
        if (is_array($request) and $this->defaultCaseFormat != false) {
            return $this->convertCase($request);
        } else {
            return $request;
        }
    }

    /**
     * for a given array of values, convert cases to the defaultCaseFormat
     *
     * @param array $request
     * @return array
     */
    protected function convertCase($request)
    {
        $inflector = new Inflector();
        switch ($this->defaultCaseFormat) {
            // assume camel case and should convert to snake
            case "snake":
                if (is_object($request)) {
                    $request = $inflector->objectPropertiesToSnake($request);
                } elseif (is_array($request)) {
                    $request = $inflector->arrayKeysToSnake($request);
                }

                break;

            // assume snake and should convert to camel
            case "camel":
                if (is_object($request)) {
                    $request = $inflector->objectPropertiesToCamel($request);
                } elseif (is_array($request)) {
                    $request = $inflector->arrayKeysToCamel($request);
                }
                break;
            default:
                break;
        }
        return $request;
    }
}
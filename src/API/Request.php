<?php
namespace PhalconRest\API;

use \PhalconRest\UtilInflector;

/**
 * extend to provide easy case conversion on incoming data
 * take json data and convert to either snake or camel case
 * depends on CaesConversion library
 */
class Request extends \Phalcon\Http\Request
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
     * pull data from a json supplied POST/PUT
     * Will either return the whole input as an array otherwise, will return an individual property
     * supports existing case conversion logic
     * TODO: Filter
     *
     * @param string $name            
     * @return mixed the requested JSON property otherwise false
     */
    public function getJson($name = null)
    {
        // normalize name to all lower case
        $inflector = new Inflector();
        $name = $inflector->underscore($name);
        // $name = strtolower($name);
        
        $json = $this->getJsonRawBody();
        
        $request = NULL;
        if (is_object($json)) {
            if ($name != NULL) {
                if (isset($json->$name)) {
                    $request = $json->$name;
                } else {
                    // expected name not found
                    throw new HTTPException("Could not find expected json data.", 500, array(
                        'dev' => json_encode($json),
                        'internalCode' => '112',
                        'more' => ''
                    )); // Could have link to documentation here.
                    return false;
                }
            } else {
                // return the entire result set
                $request = $json;
                unset($json);
            }
        } else {
            // invalid json detected
            return false;
        }
        // give convert a chance to run
        return $this->convertCase($request);
    }

    /**
     * extend to hook up possible case conversion
     *
     * @param string $name            
     * @param string $filters            
     * @param string $defaultValue            
     * @return multiple
     */
    public function getPut($name = null, $filters = null, $defaultValue = null)
    {
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
    private function convertCase($request)
    {
        // leave quickly if no reason to be here
        if ($this->defaultCaseFormat == FALSE) {
            return $request;
        }
        
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
                
                // echo "no match";
                // no matching format found
                break;
        }
        return $request;
    }
} 
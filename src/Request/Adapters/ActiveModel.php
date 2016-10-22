<?php
namespace PhalconRest\Request\Adapters;

use PhalconRest\Exception\HTTPException;
use PhalconRest\Util\Inflector;
use PhalconRest\Request\Request;
use PhalconRest\API\BaseModel;

/**
 * extend to provide easy case conversion on incoming data
 * take json data and convert to either snake or camel case
 * depends on CaesConversion library
 */
class ActiveModel extends Request
{

    /**
     * pull data from a JSON API supplied POST/PUT
     * Will either return the whole input as an array otherwise, will return an individual property
     * supports existing case conversion logic
     * TODO: Filter
     *
     * @param string $name
     * @throws HTTPException
     * @return mixed the requested JSON property otherwise false
     */
    public function getJson($name = null)
    {
        // normalize name to all lower case
        $inflector = new Inflector();
        $name = $inflector->underscore($name);
        $json = $this->getJsonRawBody();

        $request = null;
        if (is_object($json)) {
            if ($name != null) {
                if (isset($json->$name)) {
                    $request = $json->$name;
                } else {
                    // expected name not found
                    throw new HTTPException("Could not find expected json data.", 500, array(
                        'dev' => json_encode($json),
                        'code' => '112928308402707'
                    ));
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
     * will disentangle the mess JSON API submits down to something our API can work with
     *
     * @param $post
     * @param BaseModel $model
     * @return mixed
     * @throws HTTPException
     */

    public function mungeData($post, BaseModel $model)
    {
        return $post;
    }

}
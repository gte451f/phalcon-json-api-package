<?php
namespace PhalconRest\Request\Adapters;

use PhalconRest\Exception\HTTPException;
use PhalconRest\Util\Inflector;
use PhalconRest\Request\Request;
use PhalconRest\API\BaseModel;

/**
 * allow each adapter to deal with the various oddities that adapter wants
 */
class ActiveModel extends Request
{

    /**
     * pull data from a supplied POST/PUT
     * Will either return the whole input as an array otherwise, will return an individual property
     * supports existing case conversion logic
     * TODO: Filter
     *
     * @param $name
     * @param BaseModel $model
     * @return \stdClass|bool  the requested JSON property otherwise false
     * @throws HTTPException
     */
    public function getJson($name, \PhalconRest\API\BaseModel $model)
    {
        // normalize name to all lower case
        $inflector = new Inflector();
        $name = $inflector->underscore($name);

        // $raw = $this->getRawBody();
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
                        'code' => '4684646464684'
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
        $request = $this->convertCase($request);
        return $this->mungeData($request, $model);
    }


    /**
     * not much to do here in activemodel
     *
     * @param $post
     * @param BaseModel $model
     * @return mixed
     */
    public function mungeData($post, BaseModel $model)
    {
        return $post;
    }

}
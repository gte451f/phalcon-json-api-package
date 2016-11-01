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
     * @return \stdClass the requested JSON property otherwise null
     * @throws HTTPException
     */
    public function getJson($name, BaseModel $model)
    {
        // normalize name to all lower case
        $inflector = new Inflector();
        $name = $inflector->underscore($name);

        // $raw = $this->getRawBody();
        $json = $this->getJsonRawBody();
        if (is_object($json)) {
            if ($name) {
                if (isset($json->$name)) {
                    $request = $json->$name;
                } else {
                    // expected name not found
                    throw new HTTPException('Could not find expected json data.', 500, [
                        'dev' => json_encode($json),
                        'code' => '4684646464684'
                    ]);
                }
            } else {
                // return the entire result set
                $request = $json;
            }
        } else {
            // invalid json detected
            return null;
        }

        // give convert a chance to run
        return $this->convertCase($request);
    }

}
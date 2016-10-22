<?php
namespace PhalconRest\Request\Adapters;

use PhalconRest\Exception\HTTPException;
use PhalconRest\Util\Inflector;
use PhalconRest\Request\Request;
use PhalconRest\API\BaseModel;
use Phalcon\Mvc\Model\Relation as PhalconRelation;

/**
 * extend to provide easy case conversion on incoming data
 * take json data and convert to either snake or camel case
 * depends on CaesConversion library
 */
class JsonApi extends Request
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
        // $name = strtolower($name);

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
                        'code' => '112928308402707'
                    ));
                    return false;
                }
            } else {
                // return the entire result set
                $request = $json->data;
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
        // munge a bit so it works for internal data handling
        if (!isset($post->attributes)) {
            // error here, all posts require an attributes tag
            throw new HTTPException("The API received a malformed API request", 400, [
                'dev' => 'Bad or incomplete attributes property submitted to the api',
                'code' => '894168146168168168161'
            ]);
        } else {
            $data = $post->attributes;
        }

        if (isset($post->id)) {
            $data->id = $post->id;
        }

        // pull out relationships and convert to simple FKs
        if (isset($post->relationships)) {
            // go through model relationships and look for foreign keys
            $modelRelations = $model->getRelations();
            foreach ($modelRelations as $relation) {
                switch ($relation->getType()) {
                    case PhalconRelation::HAS_ONE:
                    case PhalconRelation::BELONGS_TO:
                        // pull from singular
                        $name = $relation->getTableName('singular');
                        if (isset($post->relationships->$name)) {
                            $fk = $relation->getFields();
                            if (isset($post->relationships->$name->data->id)) {
                                $data->$fk = $post->relationships->$name->data->id;
                            } else {
                                // A bad or incomplete relationship record was submitted
                                // this isn't always an error, it might be that an empty relationship was submitted
                            }
                        }
                        break;

                    case PhalconRelation::HAS_MANY:
                        // pull plural?
                        break;
                    default:
                        break;
                }
            }
        }

        return $data;
    }

}
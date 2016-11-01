<?php
namespace PhalconRest\Request\Adapters;

use PhalconRest\Exception\HTTPException;
use PhalconRest\Request\Request;
use PhalconRest\API\BaseModel;
use Phalcon\Mvc\Model\Relation as PhalconRelation;

/**
 * allow each adapter to deal with the various oddities that adapter wants
 */
class JsonApi extends Request
{

    /**
     * pull data from a JSON API supplied POST/PUT
     * design goal is to return a single json api post, but properly munged to work with the API
     * supports existing case conversion logic
     *
     * @param string $name
     * @param BaseModel $model
     * @return \stdClass requested JSON property otherwise null
     */
    public function getJson($name, BaseModel $model)
    {
        // $raw = $this->getRawBody();
        $json = $this->getJsonRawBody();

        $request = null;
        if (is_object($json)) {
            // return the entire result set
            $request = $json->data;
        } else {
            // invalid json detected
            return null; //todo: throw an error instead?
        }
        // give convert a chance to run
        $request = $this->convertCase($request);
        return $this->mungeData($request, $model);
    }


    /**
     * will disentangle the mess JSON API submits down to something our API can work with
     *
     * @param $post
     * @param BaseModel $model
     * @return \stdClass
     * @throws HTTPException
     */

    public function mungeData($post, BaseModel $model):\stdClass
    {
        // munge a bit so it works for internal data handling
        if (!isset($post->attributes)) {
            // error here, all posts require an attributes tag
            throw new HTTPException('The API received a malformed request', 400, [
                'dev' => 'Bad or incomplete attributes property submitted to the API',
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
                        //TODO: pull plural?
                        break;
                    default:
                        break;
                }
            }
        }

        return $data;
    }

}
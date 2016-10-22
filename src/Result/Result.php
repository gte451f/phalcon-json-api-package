<?php
namespace PhalconRest\Result;

use \PhalconRest\Exception\HTTPException;
use Phalcon\Mvc\Model\Relation as PhalconRelation;
use PhalconRest\Result\Data;

/**
 * The object used to store intermediate api results before they are sent to the client
 * This result object is designed specifically for use in JSON API and is not intended
 * as a general purpose result collection
 */
abstract class Result extends \Phalcon\DI\Injectable
{

    // a collection of individual data objects
    protected $data = [];
    protected $meta = false;
    protected $errors = [];
    // store a collection of data like items
    protected $included = [];

    // store the list of relationships for a "main" record type
    // each data object will refer to this when formatting data objects
    protected $relationshipRegistry = [];

    /**
     * @var string is the result going to output a single result or an array of results?
     * single | multiple | error
     */
    public $outputMode = 'error';

    public function __construct()
    {
        $di = \Phalcon\DI::getDefault();
        $this->setDI($di);
    }


    /**
     * write or overwrite a definition
     * each definition should map to a table name
     * we'll massage the name a little bit to cut down on errors
     *
     * // 1 = hasOne 0 = belongsTo 2 = hasMany
     *
     * @throws HTTPException
     * @param $relation
     */
    public function registerRelationshipDefinitions($relation)
    {
        //convert to something that makes sense :)
        switch ($relation->getType()) {
            case PhalconRelation::HAS_ONE:
            case PhalconRelation::BELONGS_TO:
            case PhalconRelation::HAS_ONE_THROUGH:
                $name = $relation->getTableName('singular');
                break;
            case PhalconRelation::HAS_MANY:
            case PhalconRelation::HAS_MANY_THROUGH:
                $name = $relation->getTableName('plural');
                break;
            default:
                // throw error for bad type
                throw new HTTPException("A Bad Relationship Type was supplied!", 500, array(
                    'code' => '8948616164789797'
                ));
                break;
        }
        $this->relationshipRegistry[strtolower($name)] = $relation;
    }

    /**
     * simple getter
     *
     * @param $name
     * @return array
     */
    public function getRelationshipDefinition($name)
    {
        if (isset($this->relationshipRegistry[$name])) {
            return $this->relationshipRegistry[$name];
        } else {
            return 'Unknown';
        }
    }

    /**
     * push new data objects into the data array
     *
     * @param Data $newData
     */
    public function addData(Data $newData)
    {
        $this->data[] = $newData;
    }


    /**
     * @param $id
     * @param null $status
     * @param null $code
     * @param null $title
     * @param null $detail
     * @param array $meta
     */
    public function addErrors($id, $status = null, $code = null, $title = null, $detail = null, array $meta = [])
    {
        $this->outputMode = 'error';
        $this->errors[] = new Error($id, $status, $code, $title, $detail, $meta);
    }

    public function addIncluded(Data $newData)
    {
        $this->included[] = $newData;
    }

    /**
     * @param $key
     * @param $value
     */
    public function addMeta($key, $value)
    {
        // flag for initial use
        if (!$this->meta) {
            $this->meta = new \stdClass();
        }
        $this->meta->$key = $value;
    }

    /**
     * to be implemented by each adapter
     *
     * @return mixed
     */
    abstract public function outputJSON();

    /**
     * for a supplied primary id and related id, create a relationship
     * @param $id
     * @param $relationship
     * @param $related_id
     * @type mixed $type just pass through to data
     * @return boolean
     */
    public function addRelationship($id, $relationship, $related_id, $type = false)
    {
        foreach ($this->data as $key => $data) {
            if ($data->getId() == $id) {
                $this->data[$key]->addRelationship($relationship, $related_id, $type);
                return true;
            }
        }
        return false;
    }


    /**
     * return the number of records stored in the result object
     *
     * @return int
     */
    public function countResults()
    {
        return count($this->data);
    }


    /**
     * for a given reltionship and id, return the matching included record
     *
     * @param $relationshipName
     * @param $id
     * @return bool|mixed
     */
    public function getInclude($relationshipName, $id)
    {
        foreach ($this->included as $item) {
            if ($item->getType() === $relationshipName AND $item->getId() === $id) {
                return $item;
            }
        }

        return false;

    }

}
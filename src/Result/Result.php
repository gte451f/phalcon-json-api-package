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
    /** @var string store the primary record type, this is probably similar to what is stored in individual data objects
     * but might be used by adapters for their own purposes */
    protected $type = false;

    /** a collection of individual data objects */
    protected $data = [];

    /** meta data describing the request or data collected */
    protected $meta = [];

    /** store errors to be returned to the client */
    protected $errors = [];

    /** store any simple non-namespaced data that is to be included in the output */
    protected $plain = [];

    /** store a collection of data like items */
    protected $included = [];

    /** store the list of relationships for a "main" record type
     * each data object will refer to this when formatting data objects */
    protected $relationshipRegistry = [];

    /**
     * @var string describe what type of result should be returned, should be constrained to one of the MODE_* constants
     */
    public $outputMode = self::MODE_OTHER;

    // possible outputModes, useful for some adapter types
    const MODE_SINGLE = 'single';  // return a single result
    const MODE_MULTIPLE = 'multiple'; // return "n" results
    const MODE_ERROR = 'error'; // indicate one or more errors occured
    const MODE_OTHER = 'other'; // return custom data of some type

    /**
     * Result constructor.
     * @param $type
     */
    public function __construct($type = false)
    {
        $di = \Phalcon\DI::getDefault();
        $this->setDI($di);
        $this->type = $type;
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
                throw new HTTPException('A Bad Relationship Type was supplied!', 500, [
                    'code' => '8948616164789797'
                ]);
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
     * add an error store object to the Result payload
     *
     * @param \PhalconRest\Exception\ErrorStore $Error
     */
    public function addError(\PhalconRest\Exception\ErrorStore $Error)
    {
        $this->outputMode = self::MODE_ERROR;
        $this->errors[] = $Error;
    }


    /**
     * add a Data object to include array on result payload
     *
     * @param \PhalconRest\Result\Data $newData
     */
    public function addIncluded(Data $newData)
    {
        $this->included[] = $newData;
    }

    /**
     * add a simple key/value pair to the meta object
     *
     * @todo expand with dot.notation to store nested values
     *
     * @param $key
     * @param $value
     */
    public function addMeta($key, $value)
    {
        $this->meta[$key] = $value;
    }

    /**
     * for a supplied primary id and related id, create a relationship
     *
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
     * this is a simple set function to store values in an array which is intended to be included in api responses
     *
     * @todo expand with dot.notation to store nested values
     *
     * @param $key
     * @param $value
     */
    public function addPlain($key, $value)
    {
        $this->Plain[$key] = $value;
    }


    /**
     * used this function to perform some final checks on the result set before passing to the adapter
     *
     * @return mixed
     * @throws \Exception
     */
    public function outputJSON()
    {
        if (count($this->data) > 1 && $this->outputMode == self::MODE_SINGLE) {
            throw new \Exception('multiple records returned, but outputmod is single?');
        }
        return $this->formatJSON();
    }

    /**
     * to be implemented by each adapter
     *
     * @return mixed
     */
    abstract protected function formatJSON();


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
     * kept here to preserve backwards comparability but will be removed at a later date
     *
     * @deprecated - use addPlain so we use the same function name for other properties
     * @param $key
     * @param $value
     */
    public function setPlain($key, $value)
    {
        return $this->addPlain($key, $value);
    }

    /**
     * for a given relationship and id, return the matching included record
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

    /**
     * get a copy of all loaded data in the result object
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * provide access to the plain array
     *
     * @param bool $key
     * @return array|mixed
     */
    public function getPlain($key = false)
    {
        if ($key) {
            if (isset($this->plain[$key])) {
                $this->plain[$key];
            } else {
                return null;
            }
        } else {
            return $this->plain;
        }
    }

}
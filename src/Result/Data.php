<?php
namespace PhalconRest\Result;

use PhalconRest\Exception\HTTPException;
use Phalcon\Mvc\Model\Relation as PhalconRelation;

/**
 * Basic object to store a single Data object, one or more data objects are strung together for
 * a complete JSON API response
 */
class Data extends \Phalcon\DI\Injectable implements \JsonSerializable
{

    /**
     * @var int
     */
    private $id;
    /**
     * @var string
     */
    private $type;

    /**
     * the list of related attributes for this resource
     * stored as simple key=>value
     * @var array
     */
    public $attributes;

    /**
     * store a list of related records by their table name (which may also be their type)
     * $relationships[TABLENAME] = [ID=>#, TYPE=>''];
     * or
     * $relationships[TABLENAME] = [ [ID=>#, TYPE=>''] ];
     *
     * @var array
     */
    public $relationships;

    public function __construct($id, $type, array $attributes = [], array $relationships = [])
    {
        $di = \Phalcon\DI::getDefault();
        $this->setDI($di);

        //parse supplied data array and populate object
        $this->id = $id;
        $this->type = $type;

        // remove this since it is already included in the $id property
        unset($attributes['id']);
        $this->attributes = $attributes;
        $this->relationships = $relationships;
    }

    /**
     * hook to describe how to encode this class for JSON
     *
     * @return array
     */
    public function JsonSerialize()
    {
        // if formatting is requested, well then format baby!
        $config = $this->di->get('config');
        if ($config['application']['propertyFormatTo'] == 'none') {
            $attributes = $this->attributes;
        } else {
            $inflector = $this->di->get('inflector');
            $attributes = [];
            foreach ($this->attributes as $key => $value) {
                $attributes[$inflector->normalize($key,
                    $config['application']['propertyFormatTo'])] = $value;
            }
        }

        $result = [
            'id' => $this->id,
            'type' => $this->type,
            'attributes' => $attributes
        ];

        if ($this->relationships) {
            $result['relationships'] = $this->relationships;
        }

        return $result;
    }

    /**
     * add related record to an existing data object
     * this function assumes a list of relations are registered with the result object
     *
     * @throws HTTPException
     * @param $relationshipName string the singular/plural to match the defined relationship
     * @param $id integer the value this data relates to
     * @param bool $type string that maps to the required json api property (seems to always be the plural version of the resource)
     */
    public function addRelationship($relationshipName, $id, $type = false)
    {
        if (!$type) {
            $type = $relationshipName;
        }

        $config = $this->di->get('config');
        $inflector = $this->di->get('inflector');
        $result = $this->di->get('result');

        // this value tells data whether to store related values as array or single object
        $relationship = $result->getRelationshipDefinition($relationshipName);

        $relationshipName = $inflector->normalize($relationshipName, $config['application']['propertyFormatTo']);
        $type = $inflector->normalize($type, $config['application']['propertyFormatTo']);

        if (isset($this->relationships[$relationshipName])) {
            if ($relationship->getType() == PhalconRelation::HAS_ONE OR $relationship->getType() == PhalconRelation::BELONGS_TO) {
                // this is a problem, attempting to load multiple records into a relationship designed for one record
                throw new HTTPException("Attempting to load multiple records into a relationships defined for a single record!", 500, array(
                    'code' => '3894646846313546467974974'
                ));
            }
            $this->relationships[$relationshipName]['data'][] = ['id' => $id, 'type' => $type];
        } else {
            if ($relationship->getType() == PhalconRelation::HAS_ONE OR $relationship->getType() == PhalconRelation::BELONGS_TO) {
                $this->relationships[$relationshipName]['data'] = ['id' => $id, 'type' => $type];
            } else {
                // process for multiple records
                $this->relationships[$relationshipName]['data'] = [];
                $this->relationships[$relationshipName]['data'][] = ['id' => $id, 'type' => $type];
            }
        }
    }

    /*
     * simple getters and setters
     */
    public function getId()
    {
        return $this->id;
    }

    public function getType()
    {
        return $this->type;
    }
}
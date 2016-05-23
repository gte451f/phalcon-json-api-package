<?php
namespace PhalconRest\Result;

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
        return [
            'id' => $this->id,
            'type' => $this->type,
            'attributes' => $this->attributes,
            'relationships' => $this->relationships
        ];
    }

    /**
     * @param $tableName string the plural table name
     * @param $id integer the value this data relates to
     * @param bool $type mixed the singular value of the table name
     */
    public function addRelationship($tableName, $id, $type = false)
    {
        if (!$type) {
            $type = $tableName;
        }
        $this->relationships[$tableName] = ['id' => $id, 'type' => $type];
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
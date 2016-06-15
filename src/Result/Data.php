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
     * @param $relationship string the singular/plural to match the defined relationship
     * @param $id integer the value this data relates to
     * @param bool $type mixed seems to always be the plural version
     */
    public function addRelationship($relationship, $id, $type = false)
    {
        if (!$type) {
            $type = $relationship;
        }

        $config = $this->di->get('config');
        $inflector = $this->di->get('inflector');

        $relationship = $inflector->normalize($relationship, $config['application']['propertyFormatTo']);
        $type = $inflector->normalize($type, $config['application']['propertyFormatTo']);

        if (isset($this->relationships[$relationship])) {
            if (isset($this->relationships[$relationship]['data']['id'])) {
                // this must be a single record
                $newRelationships = [];
                $newRelationships[] = $this->relationships[$relationship]['data'];
                $newRelationships[] = ['id' => $id, 'type' => $type];
                $this->relationships[$relationship]['data'] = $newRelationships;
            } else {
                $this->relationships[$relationship]['data'][] = ['id' => $id, 'type' => $type];
            }
        } else {
            $this->relationships[$relationship]['data'] = [];
            $this->relationships[$relationship]['data'][] = ['id' => $id, 'type' => $type];
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
<?php
namespace PhalconRest\Result\Adapters\ActiveModel;

use PhalconRest\Exception\HTTPException;
use Phalcon\Mvc\Model\Relation as PhalconRelation;

class Data extends \PhalconRest\Result\Data
{

    /**
     * hook to describe how to encode this class for ActiveModel
     *
     * @return array
     */
    public function JsonSerialize()
    {
        // if formatting is requested, well then format baby!
        $config = $this->di->get('config');
        $formatTo = $config['application']['propertyFormatTo'];

        if ($formatTo == 'none') {
            $result = $this->attributes;
        } else {
            $inflector = $this->di->get('inflector');
            $result = [];
            foreach ($this->attributes as $key => $value) {
                $result[$inflector->normalize($key, $formatTo)] = $value;
            }
        }

        // TODO fix this hack
        $result['id'] = $this->getId();

        if ($this->relationships) {
            foreach ($this->relationships as $name => $keys) {
                $result[$name] = array_keys($keys);
            }
            // $result['relationships'] = $this->relationships;
        }

        return $result;
    }


    /**
     * add related record to an existing data object
     * this function assumes a list of relations are registered with the result object
     *
     * this particular version is tailored to support the special needs of active model formatting
     *
     * store id's in keys to provide built-in duplicate detection
     *
     * @throws HTTPException
     * @param $relationshipName string the singular/plural to match the defined relationship
     * @param $id integer the value this data relates to
     * @param bool $type string that maps to the required json api property (seems to always be SINGULAR_ids)
     */
    public function addRelationship($relationshipName, $id, $type = false)
    {
        $result = $this->di->get('result');
        // this value tells data whether to store related values as array or single object
        $relationship = $result->getRelationshipDefinition($relationshipName);

        switch ($relationship->getType()) {
            case PhalconRelation::BELONGS_TO:
            case PhalconRelation::HAS_ONE:
                $relationshipName = $relationship->getTableName('singular') . '_id';
                break;
            case PhalconRelation::HAS_MANY_THROUGH:
            case PhalconRelation::HAS_MANY:
            default:
                $relationshipName = $relationship->getTableName('singular') . '_ids';
                break;
        }

        if (isset($this->relationships[$relationshipName])) {
            $this->relationships[$relationshipName][$id] = null;
        } else {
            if ($relationship->getType() == PhalconRelation::HAS_ONE OR $relationship->getType() == PhalconRelation::BELONGS_TO) {
                $this->relationships[$relationshipName][$id] = null;
            } else {
                // process for multiple records
                $this->relationships[$relationshipName] = [];
                $this->relationships[$relationshipName][$id] = null;
            }
        }
    }

}
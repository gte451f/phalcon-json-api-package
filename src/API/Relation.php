<?php
namespace PhalconRest\API;

/**
 * decorate a relation with additional properties and methods
 * attempt to pass property and method requests to the underlying relationship model
 *
 * @author jjenkins
 *        
 */
class Relation
{

    private $tableName = null;

    private $modelName = null;

    private $relation;

    function __construct(\Phalcon\Mvc\Model\Relation $relation)
    {
        $this->relation = $relation;
    }

    /**
     * pass unknown functions down to $relation
     *
     * @param mixed $name            
     * @param mixed $arguments            
     */
    function __call($name, $arguments)
    {
        return $this->relation->$name($arguments);
    }

    /**
     * get the tableName?
     */
    public function getTableName()
    {
        if (! isset($this->tableName)) {
            $name = $this->relation->getReferencedModel();
            $model = new $name();
            $this->tableName = $model->getTableName();
        }
        return $this->tableName;
    }

    /**
     * Get the primary model for a relationship
     *
     * @return string
     */
    public function getModelName()
    {
        if (isset($this->modelName)) {
            return $this->modelName;
        }
        $name = $this->relation->getReferencedModel();
        $pieces = explode('\\', $name);
        $slot = count($pieces) - 1;
        $this->modelName = $pieces[$slot];
        return $pieces[$slot];
    }
}

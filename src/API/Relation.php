<?php
namespace PhalconRest\API;

/**
 * decorate a relation with additional properties and methods
 * attempt to pass property and method requests to the underlying relationship model
 *
 * since we need to process "parent" relationships, sniff for those as well
 *
 *
 * @author jjenkins
 *        
 */
class Relation
{

    /**
     * singular name in sql database
     *
     * @var string
     */
    private $singularTableName = null;

    /**
     * plural name in sql database
     *
     * @var string
     */
    private $pluralTableName = null;

    /**
     * singular name of model that represents table
     *
     * @var string
     */
    private $singularModelName = null;

    /**
     * plural name of model that represents table
     *
     * @var string
     */
    private $pluralModelName = null;

    /**
     * the user defined alias for the relationship
     *
     * @string
     */
    private $alias;

    /**
     * The core relation object
     *
     * @var \Phalcon\Mvc\Model\Relation
     */
    private $relation;

    /**
     * store the parent model of the model described in the model
     *
     * @var string
     */
    private $parentModelName;

    private $parentTableName;

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
     * get the table name by passing this along to the underlying model
     */
    public function getTableName($type = 'plural')
    {
        $property = $type . 'TableName';
        if ($this->$property == null) {
            $name = $this->relation->getReferencedModel();
            $model = new $name();
            $this->$property = $model->getTableName($type);
        }
        return $this->$property;
    }

    /**
     * Get the singular/plural model name for a relationship
     *
     * @return string
     */
    public function getModelName($type = 'plural')
    {
        $property = $type . 'ModelName';
        if ($this->$property == null) {
            $name = $this->relation->getReferencedModel();
            $model = new $name();
            $this->$property = $model->getModelName($type);
        }
        return $this->$property;
    }

    /**
     * Get the primary model for a relationship
     *
     * @return string
     */
    public function getAlias()
    {
        if (! isset($this->alias)) {
            $options = $this->getOptions();
            if (isset($options['alias'])) {
                $this->alias = $options['alias'];
            } else {
                $this->alias = NULL;
            }
        }
        
        return $this->alias;
    }

    /**
     * get the name of the parent model (w/o namespace)
     *
     * @return string or false
     */
    public function getParent()
    {
        $name = $this->relation->getReferencedModel();
        return $name::$parentModel;
    }
}

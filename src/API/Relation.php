<?php
namespace PhalconRest\API;

use Phalcon\Mvc\Model\ManagerInterface;

/**
 * decorate a relation with additional properties and methods
 * attempt to pass property and method requests to the underlying relationship model
 *
 * since we need to process "parent" and hasOne relationships, sniff for those as well
 *
 * Relationship Type Numbers
 * BELONGS_TO = 0;
 * HAS_ONE = 1;
 * HAS_MANY = 2;
 * HAS_ONE_THROUGH = 3;
 * HAS_MANY_THROUGH = 4;
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
     * store the model's primary key
     *
     * @var string
     */
    private $primaryKey = null;

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
     * some of this classes funcitonality depends on models manager
     * injected in at runtime
     *
     * @var \Phalcon\Mvc\Model\Manager
     */
    private $modelManager;

    /**
     * store the parent model of the model described in the model
     *
     * @var string
     */
    private $parentModelName;

    /**
     * store the parent table name
     *
     * @var string
     */
    private $parentTableName;

    /**
     * store a copy of the model if it's been called
     *
     * @var unknown
     */
    private $model = null;

    /**
     * inject dependencies
     *
     * @param \Phalcon\Mvc\Model\Relation $relation
     * @param ManagerInterface $modelManager
     */
    function __construct(\Phalcon\Mvc\Model\Relation $relation, ManagerInterface $modelManager)
    {
        $this->relation = $relation;
        $this->modelManager = $modelManager;
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
     * @param string $type
     * @return
     */
    public function getTableName($type = 'plural')
    {
        $property = $type . 'TableName';
        if ($this->$property == null) {
            $this->model = $this->getModel();
            $this->$property = $this->model->getTableName($type);
        }
        return $this->$property;
    }

    /**
     * Get the singular/plural model name for a relationship
     *
     * @param string $type
     * @return string
     */
    public function getModelName($type = 'plural')
    {
        $property = $type . 'ModelName';
        if ($this->$property == null) {
            $model = $this->getModel();
            $this->$property = $model->getModelName($type);
        }
        return $this->$property;
    }

    /**
     * Get the singular/plural model name for a relationship
     *
     * @return string
     */
    public function getPrimaryKeyName()
    {
        if ($this->primaryKey == null) {
            $model = $this->getModel();
            $this->primaryKey = $model->getPrimaryKeyName();
        }
        return $this->primaryKey;
    }

    /**
     * Get the primary model for a relationship
     *
     * @return string
     */
    public function getAlias()
    {
        if (!isset($this->alias)) {
            $options = $this->getOptions();
            if (isset($options['alias'])) {
                $this->alias = $options['alias'];
            } else {
                $this->alias = null;
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

    /**
     * get a list of hasOne tables, similar to getParent but more inclusive
     *
     * @return string or false
     */
    public function getHasOnes()
    {
        $modelNameSpace = $this->relation->getReferencedModel();
        $list = [];
        $relationships = $this->modelManager->getRelations($modelNameSpace);
        foreach ($relationships as $relation) {
            $refType = $relation->getType();
            if ($refType == 1) {
                $list[] = $relation->getReferencedModel();
            }
        }

        return $list;
    }

    /**
     * ez access to the "foreign" model depicted by the relationship
     */
    public function getModel()
    {
        if ($this->model == null) {
            $name = $this->relation->getReferencedModel();
            $this->model = new $name();
        }
        return $this->model;
    }

    /**
     * provide explicit access to what would otherwise be a private property of the relationship
     *
     * @param $model
     */
    public function setModel($model)
    {
        $this->model = $model;
    }
}
<?php
namespace PhalconRest\API;

use \PhalconRest\Exception\ValidationException;

/**
 * placeholder for future work
 *
 * @author jjenkins
 *
 */
class BaseModel extends \Phalcon\Mvc\Model
{

    /**
     * singular name of the model
     *
     * @var string|null
     */
    protected $singularName = NULL;

    /**
     * essentially the name of the model
     *
     * @var string|null
     */
    protected $pluralName = NULL;

    /**
     * store a models primary key name in this property
     *
     * @var string
     */
    public $primaryKeyName;

    /**
     * store the actual pkid value as soon as we get a lock on it
     *
     * @var mixed probably an int
     */
    public $primaryKeyValue;

    /**
     * the underlying table name
     * as singular
     *
     * @var string
     */
    public $singularTableName;

    /**
     * the underlyting table name as plural
     *
     * @var string
     */
    public $pluralTableName;

    /**
     * store the full path to this model
     *
     * @var string|null
     */
    private $modelNameSpace = NULL;

    /**
     * list of relationships?
     *
     * @var array
     */
    private $relationships = null;

    /**
     * hold a list of MODEL columns that can be published to the api
     * this array is not directly modified but rather inferred
     * should work, even when side-loading data
     * !should not store parent columns!
     *
     * start as null to detect and only load once
     * all columns - block columns = allow columns
     *
     * @var array
     */
    private $allowColumns = NULL;

    /**
     * hold a list of MODEL columns that are to be blocked by the api
     * modify this list to prevent sensitive columns from being displayed
     *
     * a null value means block columns haven't been loaded yet
     * an array represents loaded blockColumns
     *
     * @var mixed
     */
    private $blockColumns = null;

    /**
     * The table this model depends on for it's existence
     * A give away is when the PKID for this model references the parent PKID
     * in the parent model
     *
     * a parent model effectively merges this table into the child table
     * as a consequence, parent table columns are displayed when requesting a child end point
     *
     * child models should not block these fields from displaying,
     * instead go to the parent model and block them from there
     *
     * @var boolean|string
     */
    public static $parentModel = FALSE;

    /**
     * store one or more parent models that this entity
     * should merge into the final resource
     *
     * this basically caches the list of models this model should merge in,
     * include a grand-parent model and grand-grand parent
     * stores basic model names, not name spaces
     *
     * @var boolean|array
     */
    private $parentModels = null;

    /**
     * BaseModel property that allows for two different behaviors on {@link save()} calls:
     * When true, a {@link ValidationException} will be thrown on errors.
     * When false, a boolean false will be returned - the original {@link \Phalcon\Mvc\Model::save()} behavior.
     * @see save()
     * @see throwOnNextSave
     * @var bool
     */
    public static $throwOnSave = false;

    /**
     * Instance counterpart of {@link $throwOnSave}. Resets after one save() call.
     * If this is true, on save errors an exception will be thrown.
     * If false, errors will be returned instead (original Phalcon behavior).
     * If it's null, it'll obey the global {@link $throwOnSave} flag.
     * @see $throwOnSave
     * @see throwOnNextSave()
     * @see save()
     * @var bool|null
     */
    public $throwOnNextSave = null;

    /**
     * auto populate a few key values
     */
    public function initialize()
    {
        $this->loadBlockColumns();
    }

    /**
     * provided to lazy load the model's name
     *
     * @param string $type singular|plural
     * @return string
     */
    public function getModelName($type = 'plural')
    {
        if ($type == 'plural') {
            if (isset($this->pluralName)) {
                return $this->pluralName;
            } else {
                $config = $this->getDI()->get('config');
                $modelNameSpace = $config['namespaces']['models'];

                $name = get_class($this);
                $name = str_replace($modelNameSpace, '', $name);
                $this->pluralName = $name;
                return $this->pluralName;
            }
        }

        if ($type == 'singular') {
            if (!isset($this->singularName)) {
                $this->singularName = substr($this->getPluralName(), 0, strlen($this->getPluralName()) - 1);
            }
            return $this->singularName;
        }

        // todo throw an error here?
        return false;
    }

    /**
     * simple function to return the model's full name space
     * relies on getModelName
     * lazy load and cache result
     */
    public function getModelNameSpace()
    {
        if (!isset($this->modelNameSpace)) {
            $config = $this->getDI()->get('config');
            $nameSpace = $config['namespaces']['models'];
            $this->modelNameSpace = $nameSpace . $this->getModelName();
        }

        return $this->modelNameSpace;
    }

    /**
     * will return the primary key name for a given model
     *
     * @return string
     */
    public function getPrimaryKeyName()
    {
        if (!isset($this->primaryKeyName)) {
            // lazy load
            $memory = $this->getDI()->get('memory');
            $attributes = $memory->getPrimaryKeyAttributes($this);
            $attributeKey = $attributes[0];

            // adjust for colMaps if any are provided
            $colMap = $memory->getColumnMap($this);
            if (is_null($colMap)) {
                $this->primaryKeyName = $attributeKey;
            } else {
                $this->primaryKeyName = $colMap[$attributeKey];
            }
        }
        return $this->primaryKeyName;
    }

    /**
     * default behavior is to expect plural table names in schema
     *
     * @todo maybe we should be on the safe side and verify the argument, or return an error in the end if nothing happens?
     * @param string $type
     * @return string
     */
    public function getTableName($type = 'plural')
    {
        if ($type == 'plural') {
            if (isset($this->pluralTableName)) {
                return $this->pluralTableName;
            } else {
                $this->pluralTableName = $this->getSource();
                return $this->pluralTableName;
            }
        }

        if ($type == 'singular') {
            if (isset($this->singularTableName)) {
                return $this->singularTableName;
            } else {
                $tableName = $this->getTableName('plural');
                // not the smartest way to make a value singular
                $this->singularTableName = substr($tableName, 0, strlen($tableName) - 1);
                return $this->singularTableName;
            }
        }
    }

    /**
     * return the model's current primary key value
     * this is designed to work for a single model "record" and not a collection
     */
    public function getPrimaryKeyValue()
    {
        $key = $this->getPrimaryKeyName();
        return isset($this->$key) ? $this->$key : null;
    }

    /**
     * return all configured relations for a given model
     * use the supplied Relation library
     * @return Relation[]
     */
    public function getRelations()
    {
        if (!isset($this->relationships)) {
            $this->relationships = array();
            // load them manually
            $mm = $this->getModelsManager();
            $relationships = $mm->getRelations(get_class($this));
            // Temporary fix because $mm->getRelations() doesn't support hasManyToMany relations right now.
            // get them separately and merge them
            $mtmRelationships = $mm->getHasManyToMany($this);

            $relationships = array_merge($relationships, $mtmRelationships);

            foreach ($relationships as $relation) {
                // todo load custom relationship
                $this->relationships[] = new Relation($relation, $mm);
            }
        }
        return $this->relationships;
    }

    /**
     * get a particular relationship configured for this model
     *
     * @param $name
     * @return mixed either a relationship object or false
     */
    public function getRelation($name)
    {
        if (!isset($this->relationships)) {
            $relations = $this->getRelations();
        } else {
            $relations = $this->relationships;
        }

        foreach ($relations as $relation) {
            if ($relation->getAlias() == $name) {
                return $relation;
            }
        }
        return false;
    }

    /**
     * a hook to be run when initializing a model
     * write logic here to block columns
     *
     * could be a static list or something more dynamic
     */
    public function loadBlockColumns()
    {
        $blockColumns = [];
        $class = get_class($this);
        $parentModelName = $class::$parentModel;

        if ($parentModelName) {
            /** @var BaseModel $parentModel */
            $parentModelNameSpace = "\\PhalconRest\\Models\\" . $parentModelName;
            $parentModel = new $parentModelNameSpace();
            $blockColumns = $parentModel->getBlockColumns();

            // the parent model may return null, let's catch and change to an empty array
            // thus indicated that block columns have been "loaded" even if they are blank
            if ($blockColumns == null) {
                $blockColumns = [];
            }
        }
        $this->setBlockColumns($blockColumns, true);
    }

    /**
     * for a given array of column names, add them to the block list
     *
     * @param array $columnList
     *            a list of columns to block for this model
     * @param boolean $clear
     *            should the existing list of blockColums be cleared to an array
     *            this has the affect of initializing the list
     */
    public function setBlockColumns($columnList, $clear = false)
    {
        // reset it requested
        if ($clear) {
            $this->blockColumns = [];
        }

        foreach ($columnList as $column) {
            $this->blockColumns[] = $column;
        }
    }

    /**
     * basic getter for private property
     *
     * @param $includeParent boolean - Include all parent block columns?
     * @return mixed
     */
    public function getBlockColumns($includeParent = true)
    {
        // load columns if they haven't been loaded yet
        if ($this->blockColumns === null) {
            $this->loadBlockColumns();

        }
        $blockColumns = $this->blockColumns;


        // also load parent(s) columns if requested
        if ($includeParent) {
            $parentModel = $this->getParentModel(true);
            if ($parentModel) {
                /** @var BaseModel $parentModel */
                $parentModel = new $parentModel();
                $parentColumns = $parentModel->getBlockColumns(true);

                // the parent model may return null, let's catch and change to an empty array
                // thus indicating that block columns have been "loaded" even if they are blank
                if ($parentColumns == null) {
                    $parentColumns = [];
                }
                $blockColumns = array_merge($blockColumns, $parentColumns);
            }
        }

        // return block columns
        return $blockColumns;
    }

    /**
     * get the private notifyColumns property
     */
    public function getNotifyColumns()
    {
        return null;
    }

    /**
     * - return fields to be included when building a resource
     * - to be used from an entity
     * - works when side loading!
     * - will exclude any fields listed in $this->blockFields
     * - can also work with parent models
     *
     * @param boolean $nameSpace should the resulting array have a nameSpace prefix?
     * @param boolean $includeParent - should this function also include parent columns?
     * @return array
     */
    public function getAllowedColumns($nameSpace = true, $includeParent = true)
    {
        $allowColumns = [];

        // cache allowColumns to save us the work in subsequent calls
        if ($this->allowColumns == NULL) {
            // load block columns if uninitialized
            if ($this->blockColumns == null) {
                $this->loadBlockColumns();
            }

            // prefix namespace if requested
            if ($nameSpace) {
                $modelNameSpace = $this->getModelNameSpace() . '.';
            } else {
                $modelNameSpace = null;
            }

            $colMap = $this->getAllColumns(false);

            foreach ($colMap as $key => $value) {
                if (array_search($value, $this->blockColumns) === false) {
                    $allowColumns[] = $modelNameSpace . $value;
                }
            }
            $this->allowColumns = $allowColumns;
        }
        // give function the chance to re-merge in parent columns
        if ($includeParent) {
            $parentModel = $this->getParentModel(true);
            if ($parentModel) {
                /** @var BaseModel $parentModel */
                $parentModel = new $parentModel();
                $parentColumns = $parentModel->getAllowedColumns(false, $includeParent);

                // the parent model may return null, let's catch and change to an empty array
                // thus indicating that block columns have been "loaded" even if they are blank
                if ($parentColumns == null) {
                    $parentColumns = [];
                }
                $allowColumns = array_merge($allowColumns, $parentColumns);
            }
        }
        return $allowColumns;
    }

    /**
     * return what should be a full set of columns for the model
     * if requested, return parent columns as well
     *
     * @todo sounds similar to getAllowedColumns() and loadBlockColumns()... maybe both could be merged and get DRY?
     * @param bool $includeParent - should the list include parent columns?
     * @return array
     */
    public function getAllColumns($includeParent = true)
    {
        // build a list of columns for this model
        $metaData = $this->getDI()->get('memory');
        $colMap = $metaData->getColumnMap($this);
        if (is_null($colMap)) {
            // but if it isn't present, fall back to attributes
            $colMap = $metaData->getAttributes($this);
        }

        if ($includeParent) {
            $parentModel = $this->getParentModel(true);
            if ($parentModel) {
                /** @var BaseModel $parentModel */
                $parentModel = new $parentModel();
                $parentColumns = $parentModel->getAllColumns(true);

                // the parent model may return null, let's catch and change to an empty array
                // thus indicating that block columns have been "loaded" even if they are blank
                if ($parentColumns == null) {
                    $parentColumns = [];
                }
                $colMap = array_merge($colMap, $parentColumns);
            }
        }
        return $colMap;
    }

    /**
     * ask this entity for all parents from the model and up the chain
     * lazy load and cache
     *
     * @param bool $withNamespace
     * should the parent names be formatted as a full namespace?
     *
     * @return array|boolean list of parent models or false
     */
    public function getParentModels($withNamespace = false)
    {
        $modelNameSpace = null;

        // first load parentModels
        if (!isset($parentModels)) {
            /** @var BaseModel $path */
            $config = $this->getDI()->get('config');
            $modelNameSpace = $config['namespaces']['models'];
            $path = $modelNameSpace . $this->getModelName();
            $parents = array();

            $currentParent = $path::$parentModel;

            while ($currentParent) :
                $parents[] = $currentParent;
                $path = $modelNameSpace . $currentParent;
                $currentParent = $path::$parentModel;
            endwhile;
            $this->parentModels = $parents;
        }

        if (count($this->parentModels) == 0) {
            return false;
        }

        // reset name space if it was not asked for
        if (!$withNamespace) {
            $modelNameSpace = null;
        }

        $parents = [];
        foreach ($this->parentModels as $parent) {
            $parents[] = $modelNameSpace . $parent;
        }

        return $parents;
    }

    /**
     * get the model name or full namespace
     *
     * @param boolean $withNamespace return the namespace or just the name
     * @return mixed either a model namespace or model name, false if not defined
     */
    public function getParentModel($withNamespace = false)
    {
        /** @var BaseModel $path */
        $config = $this->getDI()->get('config');
        $modelNameSpace = $config['namespaces']['models'];
        $path = $modelNameSpace . $this->getModelName();
        $parentModelName = $path::$parentModel;

        if (!$parentModelName) {
            return false;
        }

        return $withNamespace ? $modelNameSpace . $parentModelName : $parentModelName;
    }

    /**
     * Overrides the original save method by throwing a ValidationException on save failures.
     * This behavior can be skipped all the times or once by using {@link $throwOnSave} and {@link $throwOnNextSave}.
     * @see $throwOnSave
     * @see $throwOnNextSave
     * @see \Phalcon\Mvc\Model::save()
     * @param null $data
     * @param null $whiteList
     * @return int|bool Returns false on failures (if throw behavior is disabled) and the PKID on success calls.
     *                  May return true if the PKID cannot be found (on {@link getPrimaryKeyValue()),
     *                  but the save worked nonetheless.
     * @throws ValidationException
     */
    public function save($data = null, $whiteList = null)
    {
        $result = parent::save($data, $whiteList);
        // if the save failed, gather errors and return a validation failure if enabled

        if (!$result) {
            //default behavior is: return the value.
            //throwOnNextSave has higher priority. if it's truthy, lets throw.
            //if it's not set, the global flag takes precedence.
            $throw = false;
            if ($this->throwOnNextSave) {
                $throw = true;
                $this->throwOnNextSave = null;
            } elseif (is_null($this->throwOnNextSave) && self::$throwOnSave) {
                $throw = true;
            }

            if ($throw) {
                throw new ValidationException('Validation Errors Encountered', [
                    'code' => '50986904809',
                    'dev' => get_called_class() . '::save() failed'
                ], $this->getMessages());
            }
        } else { //it worked! let's return something more useful than a boolean: the ID, if possible
            //an ID might not be found if there's something odd with the model's PK (hidden, for instance)
            return $this->getPrimaryKeyValue() ?: true;
        }

        return $result;
    }

    /**
     * Simple chainable method to make {@link save()} calls a bit easier.
     * @see $throwOnNextSave
     * @param $bool
     * @return $this
     */
    public function throwOnNextSave($bool = true)
    {
        $this->throwOnNextSave = $bool;
        return $this;
    }

}
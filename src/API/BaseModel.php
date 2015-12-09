<?php
namespace PhalconRest\API;

use \PhalconRest\Util\HTTPException;

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
     *
     * @var string
     */
    public $tableName;

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
     * hold a list of columns that can be published to the api
     * this array is not directly modifed but rather inferred
     * should work, even when sideloading data
     *
     * start as null to detect and only load once
     * all columns - block columns = allow columns
     *
     * @var array
     */
    private $allowColumns = NULL;

    /**
     * hold a list of columns that are to be blocked by the api
     * modify this list to prevent sensitive columns from being displayed
     *
     * a null value means block columns haven't been loaded yet
     * an array represents loaded blockColumns
     *
     * @var mixed
     */
    private $blockColumns = null;

    /**
     * auto populate a few key values
     */
    public function initialize()
    {
        $this->loadBlockColumns();
    }

    /**
     * The table this model depends on for it's existance
     * A give away is when the PKID for this model references the parent PKID
     * in the parent model
     *
     * a parent model effectively merges this table into the child table
     * as a consequence, parent table columns are displayed when requesting a child end point
     * child models cannot block these fields from displaying,
     * instead go to the parent model and block them from there
     *
     * @var boolean|string
     */
    public static $parentModel = FALSE;

    /**
     * for a provided model name, return that model's parent
     *
     * @param string $name            
     */
    public static function getParentModel($name)
    {
        $config = $this->getDI()->get('config');
        $modelNameSpace = $config['namespaces']['models'];
        $path = $modelNameSpace . $name;
        return $path::$parentModel;
    }

    /**
     * provided to lazy load the model's name
     *
     * @param
     *            string type singular|plural
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
            if (! isset($this->singularName)) {
                $this->singularName = substr($this->getPluralName(), 0, strlen($this->getPluralName()) - 1);
            }
            return $this->singularName;
        }
        
        // todo throw and error here?
        return false;
    }

    /**
     * simple function to return the model's full name space
     * relies on getModelName
     * lazy load and cache result
     */
    public function getModelNameSpace()
    {
        if (! isset($this->modelNameSpace)) {
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
        if (! isset($this->primaryKeyName)) {
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
     *
     * @param string $type            
     * @return string
     */
    public function getTableName($type = 'plural')
    {
        $tableName = $this->getSource();
        if ($type == 'plural') {
            return $this->getSource();
        }
        
        if ($type == 'singular') {
            // not the smartest way to make a value singular
            return substr($tableName, 0, strlen($tableName) - 1);
        }
    }

    /**
     * return the model's current primary key value
     * this is designed to work for a single model "record" and not a collection
     */
    public function getPrimaryKeyValue()
    {
        $key = $this->getPrimaryKeyName();
        return $this->$key;
    }

    /**
     * return all configured relations for a given model
     * use the supplied Relation library
     */
    public function getRelations()
    {
        if (! isset($this->relationships)) {
            $this->relationships = array();
            // load them manually
            $mm = $this->getModelsManager();
            $relationships = $mm->getRelations(get_class($this));
            foreach ($relationships as $relation) {
                // todo load custom relationship
                $this->relationships[] = new \PhalconRest\API\Relation($relation);
            }
        }
        return $this->relationships;
    }

    /**
     * a hook to be run when initializing a model
     * write logic here to block columns
     *
     * could be a static list or something more dynamic
     */
    public function loadBlockColumns()
    {
        $this->setBlockColumns([], true);
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
     */
    public function getBlockColumns()
    {
        // load columns if they haven't been loaded yet
        if ($this->blockColumns === null) {
            $this->loadBlockColumns();
        }
        // return block columns
        return $this->blockColumns;
    }

    /**
     * - return fields to be included when building a resource
     * - to be used from an entity
     * - works when side loading!
     * - will exclude any fields listed in $this->blockFields
     *
     * @param
     *            should the resulting array have a nameSpace prefix?
     * @return array
     */
    public function getAllowedColumns($nameSpace = true)
    {
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
            
            $allowColumns = array();
            
            // build a list of columns for this model
            $metaData = $this->getDI()->get('memory');
            $colMap = $metaData->getColumnMap($this);
            if (is_null($colMap)) {
                // but if it isn't present, fall back to attributes
                $colMap = $metaData->getAttributes($this);
            }
            
            foreach ($colMap as $key => $value) {
                if (array_search($value, $this->blockColumns) === false) {
                    $allowColumns[] = $modelNameSpace . $value;
                }
            }
            $this->allowColumns = $allowColumns;
        }
        
        return $this->allowColumns;
    }
}
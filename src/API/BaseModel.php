<?php
namespace PhalconRest\API;

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
     * @var unknown
     */
    protected $singularName = NULL;

    /**
     * essentially the name of the model
     *
     * @var unknown
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
     * the child model if any
     *
     * @var unknown
     */
    public $childModel;

    private $relationships = null;

    /**
     * auto populate a few key values
     */
    public function initialize()
    {
        // $this->pluralName = $this->getSource();
        // remove the s for a singular name
        // this is a shortcut that could be improved on
        // $this->singularName = substr($this->pluralName, 0, strlen($this->pluralName) - 1);
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
                $this->relationships[] = new \PhalconRest\Libraries\REST\Relation($relation);
            }
        }
        return $this->relationships;
    }
}
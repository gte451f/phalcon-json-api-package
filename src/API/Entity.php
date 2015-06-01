<?php
namespace PhalconRest\API;

use \PhalconRest\Util\HTTPException;
use \PhalconRest\Util\ValidationException;
use \PhalconRest\Util\Inflector;

/**
 * Pulls together one or more models to represent the REST resource(s)
 * Early work revolves around supporting API calls like...Model w/ Related Records
 * Loosely follows the Phalcon Model api...that is when entity performs a function
 * similar to a model, it attempts to mimic the function name and signatures
 */
class Entity extends \Phalcon\DI\Injectable
{

    /**
     * store a list of all active relationships
     * not just a list of all possible relationships
     *
     * @var array
     */
    public $activeRelations = null;

    /**
     * store the final JSON representation going to the server
     */
    public $restResponse;

    /**
     * store phalon lib for use throughout the class
     *
     * @var Phalcon\Mvc\Model\MetaData\Memory
     */
    protected $metaData;

    /**
     * a searchHelper object used for when queries originate from HTTP requests
     *
     * @var Phalcon\Libraries\REST\SearchHelper
     */
    public $searchHelper = null;

    /**
     * store the total records found in a search (before limit)
     *
     * @var unknown
     */
    private $recordCount = null;

    /**
     * relevant only for save function
     *
     * @var string insert | update
     */
    protected $saveMode = null;
    
    // testing
    private $relatedQueries = array();

    /**
     * process injected model
     *
     * @param \PhalconRest\API\BaseModel $model            
     */
    function __construct(\PhalconRest\API\BaseModel $model, \PhalconRest\API\SearchHelper $searchHelper)
    {
        $di = \Phalcon\DI::getDefault();
        $this->setDI($di);
        
        // the primary model associated with with entity
        $this->model = $model;
        
        // a searchHelper, needed anytime we load an entity
        $this->searchHelper = $searchHelper;
        
        // where to store the final results?
        $this->restResponse = array();
        
        // hook to configure entity determined searchHelper defaults
        $this->configureSearchHelper();
        
        // load since it is nearly always needed
        $this->loadActiveRelationships();
    }

    /**
     * empty function intended to be replaced by a child function
     */
    public function configureSearchHelper()
    {}

    /**
     * for a given search query, perform find + load related records for each!
     */
    public function find($suppliedParameters = null)
    {
        $baseRecords = $this->runSearch($suppliedParameters);
        
        // if we don't find a record, terminate with false
        if ($baseRecords == false) {
            return false;
        }
        
        // prep before processing records
        $this->restResponse[$this->model->getTableName()] = array();
        
        $foundSet = 0;
        foreach ($baseRecords as $baseRecord) {
            // set primaryKeyValue
            
            $this->restResponse[$this->model->getTableName()][] = $this->processRelationships($baseRecord);
            $foundSet ++;
        }
        
        $this->appendMeta($foundSet);
        
        return $this->restResponse;
    }

    /**
     * for a given ID, load a record including any related tables
     * such as employee+user, user addresses and user phones
     *
     * @param mixed $id
     *            The PKID for the record
     *            
     * @return mixed $baseRecord
     *         an array record otherwise false
     */
    public function findFirst($id)
    {
        // store for future reference
        $this->primaryKeyValue = $id;
        
        // start by loading the base record, we MAY append related data
        // $baseRecord = $this->model->findFirst($id);
        
        // prep for a special kind of search
        $this->searchHelper->entityLimit = 1;
        $searchField = $this->model->getPrimaryKeyName();
        $this->searchHelper->entitySearchFields = array(
            $searchField => $id
        );
        
        $baseRecords = $this->runSearch();
        
        // if we don't find a record, terminate with false
        if ($baseRecords == false) {
            return false;
        }
        
        $foundSet = 0;
        foreach ($baseRecords as $baseRecord) {
            $this->restResponse[$this->model->getTableName('singular')] = $this->processRelationships($baseRecord);
            $foundSet ++;
        }
        
        // no records found on a findFirst?
        // json api calls for a 404
        if ($foundSet == 0) {
            return false;
        }
        
        $this->appendMeta($foundSet);
        
        return $this->restResponse;
    }

    /**
     */
    private function appendMeta($foundSet)
    {
        
        // should we load pager information?
        if ($this->searchHelper->isPager) {
            if (! isset($this->restResponse['meta'])) {
                $this->restResponse['meta'] = array();
            }
            // calculate the number of "paged" records in total
            $this->restResponse['meta']['total_pages'] = ceil($this->recordCount / $this->searchHelper->getLimit());
            $this->restResponse['meta']['total_record_count'] = $this->recordCount;
            $this->restResponse['meta']['returned_record_count'] = $foundSet;
        }
    }

    /**
     * will run a search, but forks to either do a PHQL based query
     * or simple query depending on suppliedParameters
     *
     * @param string $suppliedParameters            
     * @return A model object?
     */
    public function runSearch($suppliedParameters = null)
    {
        // run a simple search if parameters are supplied,
        // this would only happen if another part of the app was calling this entity directly
        // not sure we need this, it might be better to work directly on the searchHelper?
        if (is_null($suppliedParameters)) {
            // construct using PHQL
            
            // run this once for the count
            $query = $this->queryBuilder('count');
            $result = $query->getQuery()->getSingleResult();
            $this->recordCount = intval($result->count);
            
            // now run the real query
            $query = $this->queryBuilder();
            $result = $query->getQuery()->execute();
            return $result;
        } else {
            // strip out colum filter since phalcon doesn't return a full object then
            if (isset($suppliedParameters['columns'])) {
                $this->partialFields = $suppliedParameters['columns'];
                unset($suppliedParameters['columns']);
            }
            
            // send back the search results
            return $this->model->find($suppliedParameters);
        }
    }

    /**
     * build a PHQL based query to be executed by the runSearch method
     * broken up into helpers so extending this function duplicates less code
     *
     * @param boolean $count
     *            should we only gather a count of the query?
     */
    public function queryBuilder($count = false)
    {
        $config = $this->getDI()->get('config');
        $nameSpace = $config['namespaces']['models'];
        $modelNameSpace = $nameSpace . $this->model->getModelName();
        $mm = $this->getDI()->get('modelsManager');
        
        $query = $mm->createBuilder()->from($modelNameSpace);
        $columns = array(
            "$modelNameSpace.*"
        );
        
        // process hasOne Joins
        $this->queryJoinHelper($query, $nameSpace);
        $this->querySearcheHelper($query, $modelNameSpace);
        $this->querySortHelper($query);
        
        if ($count) {
            $query->columns('count(*) as count');
        } else {
            // preseve any columns added through joins
            $existingColumns = $query->getColumns();
            $allColumns = array_merge($columns, $existingColumns);
            $query->columns($allColumns);
        }
        // skip limit if returning a count
        if (! $count) {
            $this->queryLimitHelper($query);
        }
        
        // todo build fields feature into PHQL instead of doing in PHP
        return $query;
    }

    /**
     * help $this->queryBuilder to construct a PHQL object
     * apply join conditions and return query object
     *
     *
     * @param \Phalcon\Mvc\Model\Query\BuilderInterface $query            
     * @param string $nameSpace            
     * @return \Phalcon\Mvc\Model\Query\BuilderInterface
     */
    public function queryJoinHelper(\Phalcon\Mvc\Model\Query\BuilderInterface $query, $nameSpace)
    {
        $columns = [];
        // join all active hasOne's instead of just the parent
        foreach ($this->activeRelations as $relation) {
            if ($relation->getType() == 1) {
                $refModelNameSpace = $nameSpace . $relation->getModelName();
                $query->join($refModelNameSpace);
                $columns[] = "$refModelNameSpace.*";
            }
        }
        $query->columns($columns);
        
        return $query;
    }

    /**
     * help $this->queryBuilder to construct a PHQL object
     * apply search rules based on the searchHelper conditions and return query
     *
     * @param \Phalcon\Mvc\Model\Query\BuilderInterface $query            
     * @param string $modelNameSpace            
     * @return \Phalcon\Mvc\Model\Query\BuilderInterface $query
     */
    public function querySearcheHelper(\Phalcon\Mvc\Model\Query\BuilderInterface $query, $modelNameSpace)
    {
        // assume all searches as AND
        $searchFields = $this->searchHelper->getSearchFields();
        if ($searchFields) {
            $metaData = $this->getDI()->get('memory');
            
            // use a colMap to prepare for save
            $colMap = $metaData->getColumnMap($this->model);
            if (is_null($colMap)) {
                // but if it isn't present, fall back to attributes
                // $model
                $colMap = $metaData->getAttributes($this->model);
            }
            
            foreach ($searchFields as $key => $value) {
                $searchName = $key;
                // prepend modelNameSpace if the field is detected in the primary model
                foreach ($colMap as $fieldName => $label) {
                    if ($key == $label) {
                        $searchName = "$modelNameSpace.$key";
                    }
                }
                
                // check for whether we need to deal with wild cards
                $firstChar = substr($value, 0, 1);
                $lastChar = substr($value, - 1, 1);
                $wildcard = "%";
                
                if (($firstChar == "*") || ($lastChar == "*")) {
                    if ($firstChar == "*") {
                        $value = substr_replace($value, "%", 0, 1);
                        $value = $wildcard . $value;
                    }
                    if ($lastChar == "*") {
                        $value = substr_replace($value, "%", - 1, 1);
                        $value = $value . $wildcard;
                    }
                    
                    $query->andWhere("$searchName LIKE \"$value\"");
                } else {
                    $query->andWhere("$searchName = \"$value\"");
                }
            }
        }
        return $query;
    }

    /**
     * help $this->queryBuilder to construct a PHQL object
     * apply specified limit condition and return query object
     *
     * @param \Phalcon\Mvc\Model\Query\BuilderInterface $query            
     * @throws HTTPException
     * @return \Phalcon\Mvc\Model\Query\BuilderInterface $query
     */
    public function queryLimitHelper(\Phalcon\Mvc\Model\Query\BuilderInterface $query)
    {
        // only apply limit if we are NOT checking the count
        $limit = $this->searchHelper->getLimit();
        $offset = $this->searchHelper->getOffset();
        if ($offset and $limit) {
            $query->limit($limit, $offset);
        } elseif ($limit) {
            $query->limit($limit);
        } else {
            // can't have an offset w/o an limit
            throw new HTTPException("A bad query was attempted.", 500, array(
                'dev' => "Encountered an offset clause w/o a limit which is a no-no.",
                'internalCode' => '894791981'
            ));
        }
        
        return $query;
    }

    /**
     * help $this->queryBuilder to construct a PHQL object
     * apply sort params and return query object
     *
     * @param \Phalcon\Mvc\Model\Query\BuilderInterface $query            
     * @return \Phalcon\Mvc\Model\Query\BuilderInterface $query
     */
    public function querySortHelper(\Phalcon\Mvc\Model\Query\BuilderInterface $query)
    {
        // process sort
        $sortString = $this->searchHelper->getSort('sql');
        if ($sortString != false) {
            $query->orderBy($sortString);
        }
        return $query;
    }

    /**
     * for a given record, load any related values
     * called from both find and findFirst
     *
     * @param array $baseRecord
     *            the base record to decorate
     * @return array $baseRecord
     *         the base record, but decorated
     */
    public function processRelationships($baseRecord)
    {
        // prep some basic values
        $class = get_class($baseRecord);
        $config = $this->getDI()->get('config');
        $modelNameSpace = $config['namespaces']['models'] . $this->model->getModelName();
        
        // determine if we have a parent or other hasOne
        // then move into an array
        
        // indirect way to sniff for a record that depends on a parent table
        // $baseRecord would be an object w/ mulitple properties
        if (strstr($class, '\\Row')) {
            $count = count($baseRecord);
            $baseArray = array();
            
            // additional logic process scalar and object types differently
            foreach ($baseRecord as $key => $value) {
                if (gettype($value) == 'object') {
                    $baseArray = array_merge($baseArray, $value->toArray());
                    
                    $class = get_class($value);
                    
                    // primary class found
                    // then rebuild baseRecord as just the real baseRecord
                    if ($modelNameSpace == $class) {
                        $realBaseRecord = $value;
                    }
                } else {
                    $baseArray[$key] = $value;
                }
            }
            $baseRecord = $realBaseRecord;
        } else {
            // load a base array to return
            $baseArray = $baseRecord->toArray();
        }
        
        // load primaryKeyValue
        $this->primaryKeyValue = $baseArray[$this->model->getPrimaryKeyName()];
        
        // process all loaded relationships by fetching related data
        foreach ($this->activeRelations as $relation) {
            
            $refType = $relation->getType();
            
            // store a copy of all related record (PKIDs)
            // this must be attached w/ the parent records for joining purposes
            $relatedRecordIds = null;
            
            $refModelNameSpace = $relation->getReferencedModel();
            // $attributes = $this->metaData->getPrimaryKeyAttributes(new $refModelName());
            // $primaryKeyName = $attributes[0];
            
            $refModel = new $refModelNameSpace();
            $primaryKeyName = $refModel->getPrimaryKeyName();
            
            // figure out if we have a preferred alias
            $options = $relation->getOptions();
            if (isset($options['alias'])) {
                $refModelName = $options['alias'];
            }
            
            // don't process parent relationship since that is handled in PHQL query Builder
            if ($refModelName == $this->model->getParentModel()) {
                continue;
            }
            
            // don't process hasOne since they will be handled in the PHQL query Builder
            // TODO err...it will be
            if ($refType == 1) {
                continue;
            }
            
            // attempt to store the name similar to the table name
            $property = preg_replace('/(?<=\\w)(?=[A-Z])/', "_$1", $refModelName);
            $property = strtolower($property);
            
            // Check for a bad reference
            if (! isset($baseRecord->$refModelName)) {
                
                // TODO throw error here
                throw new HTTPException("A bad model->relatedModel reference was encountered.", 500, array(
                    'dev' => "Bad reference was: {$this->model->getModelName()} -> $refModelName",
                    'internalCode' => '654981091519894'
                ));
            } else {
                // auto load related records or pull manually if a parent is present
                // or if processing a belongsTo
                if ($relation->getParent() == false) {
                    // optional foreign keys will turn up false here so skip
                    if ($baseRecord->$refModelName) {
                        $relatedRecords = $baseRecord->$refModelName->toArray();
                    } else {
                        $relatedRecords = array();
                    }
                } else {
                    if ($refType == 0) {
                        $relatedRecords = $this->getBelongsToRecord($relation, $baseArray);
                    } else {
                        $relatedRecords = $this->getHasManyRecords($relation);
                    }
                }
                
                // save the PKID for each record returned
                if (count($relatedRecords) > 0) {
                    // special handling for hasOne relationship types
                    // 1 = hasOne 0 = belongsTo 2 = hasMany
                    switch ($refType) {
                        case 0:
                            // this doesn't seem right, why are they occaisionally showing up inside an array?
                            if (isset($relatedRecords[$primaryKeyName])) {
                                $relatedRecordIds = $relatedRecords[$primaryKeyName];
                                // wrap in array so we can store multiple hasOnes from many different main records
                                $relatedRecords = array(
                                    $relatedRecords
                                );
                            } else {
                                $relatedRecordIds = $relatedRecords[0][$primaryKeyName];
                            }
                            break;
                        
                        default:
                            $relatedRecordIds = array();
                            foreach ($relatedRecords as $rec) {
                                $relatedRecordIds[] = $rec[$primaryKeyName];
                            }
                            break;
                    }
                } else {
                    $relatedRecordIds = null;
                }
                
                // populate the linked property or merge in additional records
                if (! isset($this->restResponse[$property])) {
                    $this->restResponse[$property] = $relatedRecords;
                } else {
                    $a = $this->restResponse[$property];
                    $b = array_merge($a, $relatedRecords);
                    $this->restResponse[$property] = $b;
                }
                
                // will save nothing, a single value or an array
                if ($relatedRecordIds !== null) {
                    if (is_array($relatedRecordIds)) {
                        $suffix = '_ids';
                    } else {
                        $suffix = '_id';
                    }
                    
                    // TODO shortcut here, do better
                    $name = substr($property, 0, strlen($property) - 1);
                    $baseArray[$name . $suffix] = $relatedRecordIds;
                }
            }
        }
        
        return $this->filterFields($baseArray);
    }

    /**
     * built for hasMany relationships
     * in cases where the related record itself refers to a parent record,
     * write a custom query to load the related record including it's parent
     *
     * depends on the existance of a primaryKeyValue
     *
     * @return \PhalconRest\API\Relation $relation
     */
    private function getHasManyRecords(\PhalconRest\API\Relation $relation)
    {
        $query = $this->buildRelationQuery($relation);
        $query->where("{$relation->getReferencedFields()} = \"$this->primaryKeyValue\"");
        $result = $query->getQuery()->execute();
        return $this->loadRelationRecords($result);
    }

    /**
     * built for belongsTo relationships
     * in cases where the related record itself refers to a parent record,
     * write a custom query to load the related record including it's parent
     *
     * @param \PhalconRest\API\Relation $relation            
     * @param array $baseArray            
     * @return multitype:array
     */
    private function getBelongsToRecord(\PhalconRest\API\Relation $relation, $baseArray)
    {
        $query = $this->buildRelationQuery($relation);
        $foreignKey = $relation->getFields();
        $query->where("{$relation->getReferencedFields()} = \"{$baseArray[$foreignKey]}\"");
        $result = $query->getQuery()->execute();
        return $this->loadRelationRecords($result);
    }

    /**
     * utility shared between getBelongsToRecord and getHasManyRecords
     *
     * @param \PhalconRest\API\Relation $relation            
     * @return object
     */
    private function buildRelationQuery(\PhalconRest\API\Relation $relation)
    {
        $refModelNameSpace = $relation->getReferencedModel();
        
        if (isset($this->relatedQueries[$refModelNameSpace])) {
            $query = $this->relatedQueries[$refModelNameSpace];
        } else {
            $config = $this->getDI()->get('config');
            $mm = $this->getDI()->get('modelsManager');
            
            $query = $mm->createBuilder()->from($refModelNameSpace);
            $query->columns(array(
                $refModelNameSpace . ".*",
                $config['namespaces']['models'] . $relation->getParent() . '.*'
            ));
            $query->join($config['namespaces']['models'] . $relation->getParent());
            $this->relatedQueries[$refModelNameSpace] = $query;
        }
        return $query;
    }

    /**
     * utility shared between getBelongsToRecord and getHasManyRecords
     *
     * @param array $result            
     * @return multitype:array
     */
    private function loadRelationRecords($result)
    {
        $relatedRecords = array();
        foreach ($result as $relatedRecord) {
            // kept to demonstrate how to reference result row
            // $name = 'phalconRest\\Models\\Owners';
            // $rel = $relatedRecord->$name;
            $relatedRecArray = array(); // reset for each run
            foreach ($relatedRecord as $rec) {
                $relatedRecArray = array_merge($relatedRecArray, $rec->toArray());
            }
            $relatedRecords[] = $relatedRecArray;
        }
        return $relatedRecords;
    }

    /**
     * before we return a records, strip out blocked fields
     * this is also a good place to perform other last minute adjustments to a records results
     *
     * @param array $baseArray            
     * @return array
     */
    protected function filterFields($baseArray)
    {
        // remove any fields that aren't in partial fields...what about merged tables?
        $allowedFields = $this->searchHelper->getAllowedFields();
        if ($allowedFields == 'all') {
            // allow all fields, proceed
        } elseif ($allowedFields == false) {
            // uh oh...throw an error?
        } else {
            $allAvailableFields = array_keys($baseArray);
            $blockFields = array_diff($allAvailableFields, $allowedFields);
            foreach ($blockFields as $key) {
                unset($baseArray[$key]);
            }
        }
        
        // always block these fields
        $blockFields = $this->searchHelper->entityBlockFields;
        foreach ($blockFields as $key) {
            unset($baseArray[$key]);
        }
        
        return $baseArray;
    }

    /**
     * for a given set of relationships,
     * load them into the entity so find* functions return all requested related data
     *
     * make this a getter? It doesn't actually return the array, so keeping as load
     *
     * auto = do nothing
     * all = load all possible relationships
     * csv,list = load only these relationships
     *
     *
     * @param string $relationships            
     * @return void
     */
    final public function loadActiveRelationships()
    {
        // no need to run this multiple times
        if (! is_null($this->activeRelations)) {
            return;
        }
        
        $this->activeRelations = array();
        $requestedRelationships = $this->searchHelper->getWith();
        $modelRelationships = $this->model->getRelations();
        
        $all = false; // load all relationships?
                      
        // process the private array of relationships
        switch ($requestedRelationships) {
            case 'none':
                $all = false;
                $requestedRelationships = array();
                break;
            case 'all':
                $all = true;
                break;
            // expect & process a csv string
            default:
                // expect csv list or simple string
                // user_addrs,user_phones
                $requestedRelationships = explode(',', strtolower($requestedRelationships));
                break;
        }
        
        foreach ($modelRelationships as $relation) {
            // make sure the relationship is approved
            if ($all or in_array($relation->getTableName(), $requestedRelationships)) {
                $this->activeRelations[$relation->getTableName()] = $relation;
            } else {
                
                // well, maybe load parent since it's "active" but make sure we don't process it
                // no longer load parent relationship in active since we auto join in queryBuilder
                // also load if it is the parent relationship
                if ($relation->getModelName() == $this->model->getParentModel()) {
                    $this->activeRelations[$relation->getTableName()] = $relation;
                } else {
                    
                    // well, maybe load parent since it's "active" but make sure we don't process it
                    // no longer load parent relationship in active since we auto join in queryBuilder
                    // also load if it is the parent relationship
                    if ($relation->getModelName() == $this->model->getParentModel()) {
                        $this->activeRelations[$relation->getTableName()] = $relation;
                    }
                }
            }
        }
        return true;
    }

    /**
     * remove a complete entity based on a supplied primary key
     * TODO how to handle deleting from a leaf node, check this->parentModel
     *
     * @param int $id            
     */
    public function delete($id)
    {
        // $inflector = new Inflector();
        $config = $this->getDI()->get('config');
        $primaryModelName = $config['namespaces']['models'] . $this->model->getModelName();
        // $primaryModelName = $inflector->camelize($primaryModelName);
        // $primaryModelName = "\\PhalconRest\\Models\\" . $primaryModelName;
        
        $modelToDelete = $primaryModelName::findFirst($id);
        
        $this->beforeDelete($modelToDelete);
        
        if ($modelToDelete != false) {
            // attempt delete run gold leader!
            if ($modelToDelete->delete() == false) {
                // store error messages
                $messageBag = $this->getDI()->get('messageBag');
                foreach ($this->model->getMessages() as $message) {
                    $messageBag->set($message->getMessage());
                }
                throw new HTTPException("Error deleting record #$id.", 500, array(
                    'internalCode' => '66498419846816'
                ));
            }
        } else {
            // no record found to delete
            throw new HTTPException("Could not find record #$id to delete.", 404, array(
                'dev' => "No record was found to delete",
                'internalCode' => '2343467699'
            )); // Could have link to documentation here.
        }
        
        $this->afterDelete($modelToDelete);
        
        return true;
    }

    /**
     * hook to be run before an entity is deleted
     * make it easy to extend default delete logic
     *
     * @param $model the
     *            record to be deleted
     */
    public function beforeDelete($model)
    {
        // extend me in child class
    }

    /**
     * hook to be run after an entity is deleted
     * make it easy to extend default delete logic
     *
     * @param $model the
     *            record that was just removed
     */
    public function afterDelete($model)
    {
        // extend me in child class
    }

    /**
     * hook to be run before an entity is saved
     * make it easy to extend default save logic
     *
     * @param $object the
     *            data submitted to the server
     * @param int|null $id
     *            the pkid of the record to be updated, otherwise null on inserts
     */
    public function beforeSave($object, $id)
    {
        // extend me in child class
        return $object;
    }

    /**
     * hook to be run after an entity is saved
     * make it easy to extend default save logic
     *
     * @param $object the
     *            data submitted to the server
     * @param int|null $id
     *            the pkid of the record to be updated or inserted
     */
    public function afterSave($object, $id)
    {
        // extend me in child class
    }

    /**
     * hook to be run after an entity is saved
     * and relationships have been processed
     *
     * @param $object the
     *            data submitted to the server
     * @param int|null $id
     *            the pkid of the record to be updated or inserted
     */
    public function afterSaveRelations($object, $id)
    {
        // extend me in child class
    }

    /**
     * attempt to add/update a new entity
     * watch $id to determine if update or insert
     *
     * @param $object the
     *            data submitted to the server
     * @param int $id
     *            the pkid of the record to be updated, otherwise null on inserts
     * @return boolean true on success otherwise false
     */
    public function save($object, $id = NULL)
    {
        $inflector = new Inflector();
        $config = $this->getDI()->get('config');
        $primaryModelName = get_class($this->model);
        
        // check if inserting a new record and account for any parent records
        if (is_null($id)) {
            $this->saveMode = 'insert';
            
            // pre-save hook placed after saveMode
            $object = $this->beforeSave($object, $id);
            
            /**
             * Disable parent save, not sure this is even needed for rest api's
             * Ember data for sure dislikes this
             */
            $primaryModel = new $primaryModelName();
            
            // if there is a parent table, save to that record first
            // if ($this->model->getParentModel()) {
            // $parentModelName = $config['namespaces']['models'] . $this->model->getParentModel();
            // $parentModel = new $parentModelName();
            // $this->primaryKeyValue = $result = $id = $this->simpleSave($parentModel, $object);
            
            // $primaryModel = new $primaryModelName();
            
            // // now pull the parent relationship
            // $parentTableName = $inflector->underscore($this->model->getParentModel());
            // $parentRelationship = $this->activeRelations[$parentTableName];
            // $foreignKeyField = $parentRelationship->getFields();
            // $primaryModel->$foreignKeyField = $id;
            // } else {
            // $primaryModel = new $primaryModelName();
            // }
        } else {
            // update existing record
            $this->saveMode = 'update';
            
            // pre-save hook placed after saveMode
            $object = $this->beforeSave($object, $id);
            
            $this->primaryKeyValue = $id;
            $primaryModel = $primaryModelName::findFirst($id);
        }
        $result = $this->simpleSave($primaryModel, $object);
        
        // if still blank, pull from recently created $result
        if (is_null($id)) {
            $this->primaryKeyValue = $id = $result;
        }
        
        // post save hook that is called before relationships have been saved
        $this->afterSave($object, $id);
        
        // save related tables
        foreach ($this->activeRelations as $relation) {
            $refType = $relation->getType();
            
            // strip string down to model name
            $relatedModelName = $relation->getReferencedModel();
            $relatedModelName = str_ireplace('PhalconREST\\Models\\', '', $relatedModelName);
            
            // 1 = hasOne 0 = belongsTo 2 = hasMany
            switch ($refType) {
                case 0:
                    // not sure what to do here
                    break;
                
                case 1:
                    // massage name a bit
                    $relatedModelName = $inflector->underscore($relatedModelName);
                    
                    // assume it was merged
                    $relatedModel = $primaryModel->$relatedModelName;
                    $result = $this->simpleSave($relatedModel, $object);
                    break;
                
                case 2:
                    // re-associate and include new records
                    // loop through existing records and compare against what the server sent us
                    
                    // get a list of existing relatedRecords
                    $relatedRecords = $primaryModel->$relatedModelName;
                    
                    $suppliedIDs = $inflector->underscore($relatedModelName);
                    $inflector = new Inflector();
                    $suppliedIDName = $inflector->singularize($inflector->underscore($relatedModelName)) . '_ids';
                    if (isset($object->$suppliedIDName)) {
                        $suppliedRecordIDs = $object->$suppliedIDName;
                    } else {
                        $suppliedRecordIDs = array();
                    }
                    
                    // update the ones that need it, remove the ones that need removing, leave the rest alone
                    foreach ($relatedRecords as $relatedRecord) {
                        $id = $relatedRecord->getPrimaryKeyName();
                        $found = array_search($relatedRecord->$id, $suppliedRecordIDs);
                        if ($found) {
                            // do nothing, the record is where it needs to be
                            // $keep_list[] = $relatedRecord;
                            unset($suppliedRecordIDs[$found]);
                        } else {
                            // existing related record is not in supplied list, remove it
                            // $result = $relatedRecord->delete();
                        }
                    }
                    
                    // whatever remains in suppliedRecordIDs needs to be updated with the current parent primary key
                    foreach ($suppliedRecordIDs as $relatedId) {
                        $relatedModelName = $relation->getReferencedModel();
                        $relatedRecord = $relatedModelName::findFirst($relatedId);
                        if ($relatedRecord) {
                            $relatedForeignKey = $relation->getReferencedFields();
                            // echo $relatedForeignKey
                            $relatedRecord->$relatedForeignKey = $id;
                            $result = $relatedRecord->save();
                        } else {
                            // uh oh, didn't find the related record....
                        }
                    }
                    break;
                
                default:
                    ;
                    break;
            }
        }
        // post save hook that is called after all relations have been saved as well
        $this->afterSaveRelations($object, $id);
        
        $this->saveMode = null; // revert since save is finished
        return $this->primaryKeyValue;
    }

    /**
     *
     * filter through the object looking for variables to persist via the model
     *
     * @param PhalconRest\Models $model            
     * @param $object A
     *            client submitted JSON object
     * @throws HTTPException
     * @return boolean
     */
    function simpleSave($model, $object)
    {
        // loop through all known fields and save matches
        $metaData = $this->getDI()->get('memory');
        // use a colMap to prepare for save
        $colMap = $metaData->getColumnMap($model);
        if (is_null($colMap)) {
            // but if it isn't present, fall back to attributes
            $colMap = $metaData->getAttributes($model);
        }
        foreach ($colMap as $key => $label) {
            if (isset($object->$label)) {
                // odd because $key shows up on model while $label doesn't
                // but $label WORKS and $key doesn't
                // must be some magic method property stuff
                // $model->$key = $object->$label;
                $model->$label = $object->$label;
            }
        }
        $result = $model->save();
        
        // if the save failed, gather errors and return a validation failure
        if ($result == false) {
            $list = array();
            foreach ($model->getMessages() as $message) {
                $list[] = $message->getMessage();
            }
            
            throw new ValidationException("Validation Errors Encountered", array(
                'internalCode' => '7894181864684'
            ), $model->getMessages());
        }
        return $model->getPrimaryKeyValue();
    }
}

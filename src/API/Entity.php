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
     * temporary value used to store the currently loaded database record
     *
     * @var array
     */
    protected $baseRecord = array();

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

    /**
     * store one or more parent models that this entity
     * should merge into the final resource
     *
     * stores basic model names, not name spaces
     *
     * @var boolean|array
     */
    protected $parentModels = null;

    /*
     * ask this entity for all parents from the model and up the chain
     * lazy load and cache
     *
     * @param bool $nameSpace
     * should the parent names be formatted as a full namespace?
     *
     * @return array $parents
     */
    public function getParentModels($nameSpace = false)
    {
        // first load parentModels
        if (! isset($parentModels)) {
            $config = $this->getDI()->get('config');
            $modelNameSpace = $config['namespaces']['models'];
            $path = $modelNameSpace . $this->model->getModelName();
            $parents = array();
            
            $currentParent = $path::$parentModel;
            
            while ($currentParent) :
                $parents[] = $currentParent;
                $path = $modelNameSpace . $currentParent;
                $currentParent = $path::$parentModel;
            endwhile
            ;
            $this->parentModels = $parents;
        }
        
        if (count($this->parentModels) == 0) {
            return false;
        }
        
        // reset name space if it was not asked for
        if (! $nameSpace) {
            $modelNameSpace = null;
        }
        
        $parents = array();
        foreach ($this->parentModels as $parent) {
            $parents[] = $modelNameSpace . $parent;
        }
        
        return $parents;
    }

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
        foreach ($baseRecords as $base) {
            // normalize results, pull out join fields
            $base = $this->extractMainRow($base);
            // store related records in restResponse or load for optimized DB queries
            $this->processRelationships($base);
            $this->restResponse[$this->model->getTableName()][] = $this->baseRecord;
            $foundSet ++;
        }
        
        // TODO single DB query for records related to main query
        
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
            // normalize results, pull out join fields
            $baseRecord = $this->extractMainRow($baseRecord);
            // store related records in restResponse or load for optimized DB queries
            $this->processRelationships($baseRecord);
            $this->restResponse[$this->model->getTableName('singular')][] = $this->baseRecord;
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
            
            $registry = $this->getDI()->get('registry');
            $this->restResponse['meta']['database_query_count'] = $registry->dbCount;
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
        $modelNameSpace = $this->model->getModelNameSpace();
        $mm = $this->getDI()->get('modelsManager');
        $query = $mm->createBuilder()->from($modelNameSpace);
        
        // $columns = $this->model->getAllowedColumns(true);
        $columns = array(
            "$modelNameSpace.*"
        );
        
        // hook to allow for custom work to be done on the $query object before it is process by the queryBuilder method
        $this->beforeQueryBuilderHook($query);
        
        // process hasOne Joins
        $this->queryJoinHelper($query);
        $this->querySearcheHelper($query);
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
        
        // hook to allow for custom work to be done on the $query object before returning it.
        $this->afterQueryBuilderHook($query);
        
        // todo build fields feature into PHQL instead of doing in PHP
        return $query;
    }

    /**
     * hook to do custom work on the $query object before it is processed by the queryBuilder method
     */
    public function beforeQueryBuilderHook($query)
    {
        return true;
    }

    /**
     * hook to allow for custom work to be done on the $query object before returning it
     */
    public function afterQueryBuilderHook($query)
    {
        return true;
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
    public function queryJoinHelper(\Phalcon\Mvc\Model\Query\BuilderInterface $query)
    {
        $config = $this->getDI()->get('config');
        $modelNameSpace = $config['namespaces']['models'];
        
        $parentModels = $this->getParentModels(true);
        
        $columns = [];
        // join all active hasOne's instead of just the parent
        foreach ($this->activeRelations as $relation) {
            if ($relation->getType() == 1) {
                $refModelNameSpace = $modelNameSpace . $relation->getModelName();
                $query->join($refModelNameSpace);
                // add all parent joins to the column list
                if ($parentModels and in_array($refModelNameSpace, $parentModels)) {
                    $columns[] = "$refModelNameSpace.*";
                }
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
    public function querySearcheHelper(\Phalcon\Mvc\Model\Query\BuilderInterface $query)
    {
        $searchFields = $this->searchHelper->getSearchFields();
        if ($searchFields) {
            // preprocess the search fields to see if any of the search names require preprocessing
            // mostly just looking for || or type syntax otherwise process as default (and) WHERE clause
            $processedSearchFields = array();
            foreach ($searchFields as $fieldName => $fieldValue) {
                $processedFieldName = $this->processSearchFields($fieldName);
                $processedFieldValue = $this->processSearchFields($fieldValue);
                $processedFieldQueryType = $this->processSearchFieldQueryType($processedFieldName, $processedFieldValue);
                $processedSearchFields[] = array(
                    'queryType' => $processedFieldQueryType,
                    'fieldName' => $processedFieldName,
                    'fieldValue' => $processedFieldValue
                );
            }
            
            foreach ($processedSearchFields as $processedSearchField) {
                switch ($processedSearchField['queryType']) {
                    case 'and':
                        $fieldName = $this->prependFieldNameNamespace($processedSearchField['fieldName']);
                        $fieldValue = $processedSearchField['fieldValue'];
                        $newFieldValue = $this->processFieldValueWildcards($fieldValue);
                        $operator = $this->determineQueryWhereOperator($newFieldValue);
                        $query->andWhere("$fieldName $operator \"$newFieldValue\"");
                        break;
                    
                    case 'or':
                        // make sure the field name is an array so we can use the same logic below for either circumstance
                        if (! is_array($processedSearchField['fieldName'])) {
                            $fieldNameArray = array(
                                $processedSearchField['fieldName']
                            );
                        } else {
                            $fieldNameArray = $processedSearchField['fieldName'];
                        }
                        
                        // make sure the field value is an array so we can use the same logic below for either circumstance
                        if (! is_array($processedSearchField['fieldValue'])) {
                            $fieldValueArray = array(
                                $processedSearchField['fieldValue']
                            );
                        } else {
                            $fieldValueArray = $processedSearchField['fieldValue'];
                        }
                        
                        foreach ($fieldNameArray as $fieldName) {
                            $fieldName = $this->prependFieldNameNamespace($fieldName);
                            foreach ($fieldValueArray as $fieldValue) {
                                $newFieldValue = $this->processFieldValueWildcards($fieldValue);
                                $operator = $this->determineQueryWhereOperator($newFieldValue);
                                $query->orWhere("$fieldName $operator \"$newFieldValue\"");
                            }
                        }
                        break;
                }
            }
        }
        return $query;
    }

    /**
     * This method looks for the existence of syntax extentions to the api and attempts to
     * adjust search inputs before subjecting them to the queryBuilder
     *
     * The 'or' operator || explodes the given parameter on that operator if found
     * This is done for both the field name and values for each query parameter encountered
     * first_name=jim||john
     * first_name||last_name=jim
     *
     * @param string $fieldParam            
     * @return mixed return an array if || is found, otherwise a string
     */
    private function processSearchFields($fieldParam)
    {
        if (strpos($fieldParam, '||') !== false) {
            return explode('||', $fieldParam);
        } else {
            return $fieldParam;
        }
    }

    /**
     * This method determines whether the clause should be processed as an 'and' clause or an 'or' clause.
     * This
     * is determined based on the results from the \PhalconRest\API\Entity::processSearchFields() method. If that
     * method returns a string, we are dealing with an 'and' clause, if not, we are dealing with an 'or' clause.
     *
     * @param
     *            string or array $processedFieldName
     * @param
     *            string or array $processedFieldValue
     * @return string
     */
    private function processSearchFieldQueryType($processedFieldName, $processedFieldValue)
    {
        $result = 'and';
        
        if (is_array($processedFieldName) || is_array($processedFieldValue)) {
            $result = 'or';
        }
        
        return $result;
    }

    /**
     * Given a particular fieldName, look through the current model's column map and see if that
     * particular fieldName appears in it, if it does, then prepend the appropriate namespace to this
     * fieldName
     *
     *
     * Columns from related tables can be searched via a colon such as related_table_name:column_name
     * A concrete example is
     * resources:matters_id=123
     * resources:child_table=note||subject
     *
     * A rather obscure feature of this implementation is that providing no table prefix often works correctly
     *
     *
     * @param string $fieldName            
     * @return string
     */
    private function prependFieldNameNamespace($fieldName)
    {
        // $metaData = $this->getDI()->get('memory');
        $searchBits = explode(':', $fieldName);
        
        // if a related table is referenced, then search related model column maps instead of the primary model
        if (count($searchBits) == 2) {
            $matchFound = false;
            $fieldName = $searchBits[1];
            foreach ($this->activeRelations as $item) {
                if ($searchBits[0] == $item->getTableName()) {
                    $modelNameSpace = $item->getReferencedModel();
                    $relatedModel = new $modelNameSpace();
                    $colMap = $relatedModel->getAllowedColumns(false);
                    $fieldName = $searchBits[1];
                    $matchFound = true;
                    break;
                }
            }
            
            // if we made it this far, than a prefix was supplied but it did not match any known hasOne relationship
            if ($matchFound == false) {
                throw new HTTPException("Unkown table prefix supplied in filter.", 500, array(
                    'dev' => "Encountered a table prefix that did not match any known hasOne relationships in the model.",
                    'code' => '891488651361948131461849'
                ));
            }
        } else {
            $modelNameSpace = $this->model->getModelNameSpace();
            // $colMap = $metaData->getColumnMap($this->model);
            $colMap = $this->model->getAllowedColumns(false);
        }
        
        // prepend modelNameSpace if the field is detected in the selected model's column map
        foreach ($colMap as $field) {
            if ($fieldName == $field) {
                return "$modelNameSpace.$fieldName";
            }
        }
        
        return $fieldName;
    }

    /**
     * Given a fieldValue, search for the wildcard character and replace with an SQL specific wildcard
     * character
     *
     * @param string $fieldValue            
     * @return string
     */
    private function processFieldValueWildcards($fieldValue)
    {
        // check for whether we need to deal with wild cards
        $firstChar = substr($fieldValue, 0, 1);
        $lastChar = substr($fieldValue, - 1, 1);
        $wildcard = "%";
        
        if (($firstChar == "*") || ($lastChar == "*")) {
            if ($firstChar == "*") {
                $fieldValue = substr_replace($fieldValue, "%", 0, 1);
            }
            if ($lastChar == "*") {
                $fieldValue = substr_replace($fieldValue, "%", - 1, 1);
            }
        }
        
        return $fieldValue;
    }

    /**
     * Determine whether a clause should be processed with and '=' operator or with a 'LIKE' operatoer.
     * This is determined by the presence of the SQL wildcard character in the fieldValue string
     *
     * @param string $value            
     * @return string
     */
    private function determineQueryWhereOperator($value)
    {
        if (strpos($value, '%') !== false) {
            return 'LIKE';
        } else {
            return '=';
        }
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
                'code' => '894791981'
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
        $rawSort = $this->searchHelper->getSort('sql');
        
        // by default, use the local namespace
        $preparedSort = $this->prependFieldNameNamespace($rawSort);
        if ($preparedSort != false) {
            $query->orderBy($preparedSort);
        }
        return $query;
    }

    /**
     * for a given base record, build an array to represent a single row including merged tables
     * strip out extra merge rows and return a single result record
     *
     * @param mixed $baseRecord            
     * @return Phalcon Result record
     */
    public function extractMainRow($baseRecord)
    {
        $class = get_class($baseRecord);
        
        // basically check for parent records and pull them out
        if ($class == 'Phalcon\Mvc\Model\Row') {
            $newBase = false;
            $baseArray = array();
            
            foreach ($baseRecord as $record) {
                $class = get_class($record);
                $primaryModel = $this->model->getModelNameSpace();
                
                if ($primaryModel === $class) {
                    $newBase = $record;
                }
                $baseArray = array_merge($this->loadAllowedColumns($record), $baseArray);
            }
            
            $this->baseRecord = $baseArray;
            return $newBase;
        } else {
            $this->baseRecord = $this->loadAllowedColumns($baseRecord);
            return $baseRecord;
        }
    }

    /**
     * for a given record, load any related values
     * called from both find and findFirst
     *
     *
     * @param array $baseRecord
     *            the base record to decorate
     * @return array $baseRecord
     *         the base record, but decorated
     */
    public function processRelationships($baseRecord)
    {
        // load primaryKeyValue
        $this->primaryKeyValue = $this->baseRecord[$this->model->getPrimaryKeyName()];
        // store parentModels for later use
        $parentModels = $this->getParentModels(true);
        
        // process all loaded relationships by fetching related data
        foreach ($this->activeRelations as $relation) {
            // skip any parent relationships because they are merged into the main record
            $refModelNameSpace = $relation->getReferencedModel();
            if ($parentModels and in_array($refModelNameSpace, $parentModels)) {
                continue;
            }
            
            $refType = $relation->getType();
            
            // store a copy of all related record (PKIDs)
            // this must be attached w/ the parent records for joining purposes
            $relatedRecordIds = null;
            $refModel = new $refModelNameSpace();
            $primaryKeyName = $refModel->getPrimaryKeyName();
            
            // figure out if we have a preferred alias
            $alias = $relation->getAlias();
            if (isset($alias)) {
                $refModelName = $alias;
            } else {
                $refModelName = $relation->getModelName();
            }
            
            // Check for a bad reference
            if (! isset($baseRecord->$refModelName)) {
                // TODO throw error here
                throw new HTTPException("A bad relationship reference was encountered.", 500, array(
                    'dev' => "Bad reference was: {$this->model->getModelName()} -> $refModelName",
                    'code' => '654981091519894'
                ));
            } else {
                // harmonize relatedRecords
                if ($refType == 0) {
                    $relatedRecords = $this->getBelongsToRecord($relation);
                } elseif ($refType == 1) {
                    // all hasOne relationships would be loaded in the initial query right?
                    // if the hasOne itself has a parent, then treat it more like a belongsTO so
                    // merged columns are loaded
                    $relatedParent = $refModelNameSpace::$parentModel;
                    if ($relatedParent) {
                        $relatedRecords = $this->getBelongsToRecord($relation);
                    } else {
                        $relatedRecords = $this->loadAllowedColumns($baseRecord->$refModelName);
                    }
                } else {
                    $relatedRecords = $this->getHasManyRecords($relation);
                }
                
                // save the PKID for each record returned
                if (count($relatedRecords) > 0) {
                    // 1 = hasOne 0 = belongsTo 2 = hasMany
                    switch ($refType) {
                        // process hasOne records as well
                        case 1:
                        case 0:
                            // this doesn't seem right, why are they occasionally showing up inside an array?
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
                
                // we map table names to end point resource names and vice versa
                // regardless of relationship, the related records are returned as part of the end point resource name
                $this->updateRestResponse($relation->getTableName(), $relatedRecords);
                
                // add related record ids to the baseArray
                // this is how JSON API suggests that you related resources
                // will save nothing, a single value or an array
                
                // does this only run when working with hasMany?
                // belongsTo and hasOne are already in place, yes?
                if ($relatedRecordIds !== null and $refType == 2) {
                    $suffix = '_ids';
                    // populate the linked property or merge in additional records
                    // attempt to store the name similar to the table name
                    $name = $relation->getTableName('singular');
                    $this->baseRecord[$name . $suffix] = $relatedRecordIds;
                }
            }
        }
        return true;
    }

    /**
     * load an array of records into the restResponse
     *
     * @param string $table
     *            the table name where the records originated
     * @param array $records
     *            usually related records, but could side load just about any records to an api response
     * @return void
     */
    private function updateRestResponse($table, $records)
    {
        if (! isset($this->restResponse[$table])) {
            $this->restResponse[$table] = $records;
        } else {
            $a = $this->restResponse[$table];
            $b = array_merge($a, $records);
            $this->restResponse[$table] = $b;
        }
    }

    /**
     * extract only approved fields from a resultset
     *
     * @param \PhalconRest\API\Model $resultSet            
     */
    protected function loadAllowedColumns($resultSet)
    {
        $record = array();
        // $allowedFields = $model->getAllowedColumns(false);
        $allowedFields = $resultSet->getAllowedColumns(false);
        foreach ($allowedFields as $field) {
            $record[$field] = $resultSet->$field;
        }
        return $record;
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
        return $this->loadRelationRecords($result, $relation);
    }

    /**
     * built for belongsTo relationships
     * in cases where the related record itself refers to a parent record,
     * write a custom query to load the related record including it's parent
     *
     * @param \PhalconRest\API\Relation $relation            
     * @return multitype:array
     */
    private function getBelongsToRecord(\PhalconRest\API\Relation $relation)
    {
        $query = $this->buildRelationQuery($relation);
        $referencedField = $relation->getReferencedFields();
        $foreignKey = $relation->getFields();
        
        // can take a shortcut here,
        // if the related record has already been loaded, than return empty array
        $tableName = $relation->getTableName();
        $foreignKeyValue = $this->baseRecord[$foreignKey];
        
        if (isset($this->restResponse[$tableName])) {
            foreach ($this->restResponse[$tableName] as $row) {
                if ($row[$referencedField] == $foreignKeyValue) {
                    return array();
                }
            }
        }
        
        $query->where("{$referencedField} = \"{$this->baseRecord[$foreignKey]}\"");
        $result = $query->getQuery()->execute();
        return $this->loadRelationRecords($result, $relation);
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
        
        $config = $this->getDI()->get('config');
        $modelNameSpace = $config['namespaces']['models'];
        $mm = $this->getDI()->get('modelsManager');
        
        $query = $mm->createBuilder()->from($refModelNameSpace);
        
        $columns = array(
            $refModelNameSpace . ".*"
        );
        
        $foo = $relation->getParent();
        
        // join in parent record if specified
        if ($relation->getParent()) {
            $columns[] = $modelNameSpace . $relation->getParent() . '.*';
            $query->join($modelNameSpace . $relation->getParent());
        }
        $query->columns($columns);
        
        return $query;
    }

    /**
     * utility shared between getBelongsToRecord and getHasManyRecords
     *
     * @param array $result            
     * @return multitype:array
     */
    private function loadRelationRecords($result, \PhalconRest\API\Relation $relation)
    {
        $relatedRecords = array(); // store all related records
        foreach ($result as $relatedRecord) {
            $relatedRecArray = array(); // reset for each run
                                        
            // load parent record into restResponse in passing
            $parent = $relation->getParent();
            if ($parent) {
                // process records that include joined in parent records
                foreach ($relatedRecord as $rec) {
                    $relatedRecArray = array_merge($relatedRecArray, $this->loadAllowedColumns($rec));
                }
            } else {
                // return just the related record, not a joined in parent record as well
                $relatedRecArray = $this->loadAllowedColumns($relatedRecord);
            }
            $relatedRecords[] = $relatedRecArray;
        }
        return $relatedRecords;
    }

    /**
     * for a given set of relationships,
     * load them into the entity so find* functions return all requested related data
     *
     * make this a getter? It doesn't actually return the array, so keeping as load
     *
     * always load parent model(s)
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
        $parentModels = $this->getParentModels(false);
        $modelRelationships = $this->model->getRelations();
        
        $all = false; // load all relationships?
                      
        // process the private array of relationships
        switch ($requestedRelationships) {
            case 'none':
                $all = false;
                // gotta load parents if there are any
                if ($parentModels) {
                    $requestedRelationships = $parentModels;
                } else {
                    $requestedRelationships = array();
                }
                break;
            case 'all':
                $all = true;
                break;
            // expect & process a csv string
            default:
                // expect csv list or simple string
                // user_addrs,user_phones
                $requestedRelationships = explode(',', strtolower($requestedRelationships));
                // include parents if there are any
                if ($parentModels) {
                    $requestedRelationships = array_merge($parentModels, $requestedRelationships);
                }
                break;
        }
        
        // load all active relationships as defined by searchHelper
        foreach ($modelRelationships as $relation) {
            $tableName = $relation->getTableName();
            $modelName = $relation->getModelName();
            $aliasName = $relation->getAlias();
            
            // make sure the relationship is approved either as the table name, model name or ALL
            // table names because end point resources = table names
            // model name because some auto generated relationships use this name instead
            // alias is used to STORE the active relationship in case multiple relationships point to the same model
            // but it is not a valid way for a client to request data
            if ($all or in_array($tableName, $requestedRelationships) or in_array($modelName, $requestedRelationships)) {
                // figure out if we have a preferred alias
                if ($aliasName) {
                    $this->activeRelations[$aliasName] = $relation;
                } else {
                    $this->activeRelations[$modelName] = $relation;
                }
            }
        }
        return true;
    }

    /**
     * remove a complete entity based on a supplied primary key
     * TODO how to handle deleting from a leaf node, check this->parentModel
     * currently this logic depends on the SQL cascade rule to do the heavy lifting
     *
     * @param int $id            
     * @return boolean
     */
    public function delete($id)
    {
        // $inflector = new Inflector();
        $primaryModelName = $this->model->getModelNameSpace();
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
                    'code' => '66498419846816'
                ));
            }
        } else {
            // no record found to delete
            throw new HTTPException("Could not find record #$id to delete.", 404, array(
                'dev' => "No record was found to delete",
                'code' => '2343467699'
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
     * built to accomdate saving records w/ parent tables (hasOne)
     *
     * @param $formData the
     *            data submitted to the server
     * @param int $id
     *            the pkid of the record to be updated, otherwise null on inserts
     * @return int the PKID of the record in question
     */
    public function save($formData, $id = NULL)
    {
        // $inflector = new Inflector();
        
        // check if inserting a new record and account for any parent records
        if (is_null($id)) {
            $this->saveMode = 'insert';
            // pre-save hook placed after saveMode
            $formData = $this->beforeSave($formData, $id);
            // load a model including potential parents
            $primaryModel = $this->loadParentModel($this->model, $formData);
        } else {
            // update existing record
            $this->saveMode = 'update';
            
            // pre-save hook placed after saveMode
            $formData = $this->beforeSave($formData, $id);
            
            $this->primaryKeyValue = $id;
            
            // need parent logic here
            $model = $this->model;
            $primaryModel = $model::findFirst($id);
            $primaryModel = $this->loadParentModel($primaryModel, $formData);
            
            // // TODO this only works with 1 parent so far....
            // $parentModelName = $model::$parentModel;
            // if ($parentModelName) {
            // $config = $this->getDI()->get('config');
            // $modelNameSpace = $config['namespaces']['models'];
            // $parentNameSpace = $modelNameSpace . $parentModelName;
            // $parentModel = $parentNameSpace::findFirst($id);
            // $primaryModel = $this->loadModelValues($parentModel, $formData);
            // }
        }
        
        $result = $this->simpleSave($primaryModel, $formData);
        
        // if still blank, pull from recently created $result
        if (is_null($id)) {
            $this->primaryKeyValue = $id = $result;
        }
        
        // post save hook that is called before relationships have been saved
        $this->afterSave($formData, $id);
        
        // post save hook that is called after all relations have been saved as well
        $this->afterSaveRelations($formData, $id);
        
        $this->saveMode = null; // revert since save is finished
        return $this->primaryKeyValue;
    }

    /**
     * for a given model, load the parent if it exists
     * return the final definitive parent model
     * along with loading client submitted data into each model
     *
     *
     * @param object $model            
     * @param object $object            
     */
    public function loadParentModel($model, $object)
    {
        if ($model::$parentModel) {
            $config = $this->getDI()->get('config');
            $modelNameSpace = $config['namespaces']['models'];
            $parentNameSpace = $modelNameSpace . $model::$parentModel;
            $parentModel = new $parentNameSpace();
            $finalModel = $this->loadParentModel($parentModel, $object);
            
            if ($this->saveMode == 'update') {
                $primaryKey = $model->getPrimaryKeyName();
                $finalModel = $parentModel::findFirst($model->$primaryKey);
            } else {
                $finalModel = $this->loadParentModel($parentModel, $object);
            }
            
            // don't forget to load the child model values and mount into parent model
            $childModel = $this->loadModelValues($model, $object);
            $childModelName = $model->getModelName();
            $finalModel->$childModelName = $childModel;
        } else {
            $finalModel = $model;
        }
        
        // run object data through the model
        return $this->loadModelValues($finalModel, $object);
    }

    /**
     * load object data into the current model
     *
     * @param PhalconRest\Models $model            
     * @param object $formData            
     *
     * @return a model loaded with all relevant data from the object
     */
    public function loadModelValues($model, $formData)
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
            if (isset($formData->$label)) {
                // odd because $key shows up on model while $label doesn't
                // but $label WORKS and $key doesn't
                // must be some magic method property stuff
                $model->$label = $formData->$label;
            }
        }
        
        return $model;
    }

    /**
     * save a model and collect any error messages that may be returned
     * return the model PKID whether insert or update
     *
     * @param PhalconRest\Models $model            
     * @throws HTTPException
     * @return int
     */
    function simpleSave($model)
    {
        $result = $model->save();
        // if the save failed, gather errors and return a validation failure
        if ($result == false) {
            throw new ValidationException("Validation Errors Encountered", array(
                'code' => '7894181864684',
                'dev' => 'entity->simpleSave failed to save model'
            ), $model->getMessages());
        }
        return $model->getPrimaryKeyValue();
    }
}
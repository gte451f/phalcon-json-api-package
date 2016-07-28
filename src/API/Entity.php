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
     * store the name the endpoint should advertise
     * if nothing is defined, one will be auto detected
     *
     * @var string
     */
    public $endpointName = null;

    /**
     * keep a copy of the entity records PKID
     *
     * @var int
     */
    public $primaryKeyValue = null;

    /**
     * temporary value used to store the currently loaded database record
     * can be accessed from around the entity class
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
        if ($baseRecords === false) {
            return false;
        }
        
        // prep before processing records
        $this->restResponse[$this->model->getTableName()] = array();
        
        $foundSet = 0;
        foreach ($baseRecords as $baseResult) {
            // normalize results, pull out join fields
            $baseResult = $this->extractMainRow($baseResult);
            
            // hook for manipulating the base record before processing relationships
            $baseResult = $this->beforeProcessRelationships($baseResult);
            
            // store related records in restResponse or load for optimized DB queries
            $this->processRelationships($baseResult);
            
            // hook for manipulating the base record after processing relationships
            $this->afterProcessRelationships($baseResult);
            
            $this->restResponse[$this->model->getTableName()][] = $this->baseRecord;
            $foundSet ++;
        }
        
        // TODO single DB query for records related to main query
        
        $this->appendMeta($foundSet);
        return $this->restResponse;
    }

    /**
     * hook for manipulating the base record before processing relatoinships.
     * this method is called from the find and findFirst methods
     *
     * @param mixed $baseResult            
     */
    public function beforeProcessRelationships($baseResult)
    {
        return $baseResult;
    }

    /**
     * hook for manipulating the base record after processing relatoinships.
     * this method is called from the find and findFirst methods
     *
     * will accept baseResult as param but there is no point in passing it back up since the real data is in a class level object
     *
     * @param mixed $baseResult            
     */
    public function afterProcessRelationships($baseResult)
    {}

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
        if ($baseRecords === false) {
            return false;
        }
        
        $foundSet = 0;
        foreach ($baseRecords as $baseResult) {
            // normalize results, pull out join fields
            $baseResult = $this->extractMainRow($baseResult);
            
            // hook for manipulating the base record before processing relationships
            $baseResult = $this->beforeProcessRelationships($baseResult);
            
            // store related records in restResponse or load for optimized DB queries
            $this->processRelationships($baseResult);
            
            // hook for manipulating the base record after processing relationships
            $this->afterProcessRelationships($baseResult);
            
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
     * add a few extra metrics as enabled by the system
     *
     * @param int $foundSet
     *            a count of the records matching api request
     */
    protected function appendMeta($foundSet)
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
            
            $config = $this->getDI()->get('config');
            if ($config['application']['debugApp']) {
                $registry = $this->getDI()->get('registry');
                $this->restResponse['meta']['database_query_count'] = $registry->dbCount;
            }
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
            
            if (! $this->searchHelper->isCount) {
                // now run the real query
                $query = $this->queryBuilder();
                $result = $query->getQuery()->execute();
                return $result;
            } else {
                return array();
            }
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
        // join all active hasOne and belongTo instead of just the parent hasOne
        foreach ($this->activeRelations as $relation) {
            // refer to alias or model path to prefix each relationship
            // prefer alias over model path in case of colisions
            $alias = $relation->getAlias();
            $referencedModel = $relation->getReferencedModel();
            if (! $alias) {
                $alias = $referencedModel;
            }
            
            // what to do about belongsTo? side load would be more performant and allow for better filtering options
            // but can we do this with out breaking everything?
            if ($relation->getType() == 0 or $relation->getType() == 1) {
                // create both sides of the join
                $left = "[$alias]" . '.' . $relation->getReferencedFields();
                $right = $modelNameSpace . $this->model->getModelName() . '.' . $relation->getFields();
                // create and alias join
                $query->leftJoin($referencedModel, "$left = $right", $alias);
            }
            
            // add all parent AND hasOne joins to the column list
            if ($relation->getType() == 1) {
                $columns[] = "[$alias].*";
            }
        }
        $query->columns($columns);
        return $query;
    }

    /**
     * This function can be used to process custom field filters
     * in the concrete endpoint
     *
     * @param $fieldName string the name of the field to be added to the processedSearchFields array
     * @param $fieldValue mixed the value to the query
     * @param \Phalcon\Mvc\Model\Query\BuilderInterface $query *In case we need to join other tables
     *
     * @return array | false
     */
    public function processFilterField($fieldName, $fieldValue, \Phalcon\Mvc\Model\Query\BuilderInterface $query)
    {
        $processedFieldName = $this->processSearchFields($fieldName);
        $processedFieldValue = $this->processSearchFields($fieldValue);
        $processedFieldQueryType = $this->processSearchFieldQueryType($processedFieldName, $processedFieldValue);
        return array(
            'queryType' => $processedFieldQueryType,
            'fieldName' => $processedFieldName,
            'fieldValue' => $processedFieldValue
        );
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
            // pre-process the search fields to see if any of the search names require pre-processing
            // mostly just looking for || or type syntax otherwise process as default (and) WHERE clause
            $processedSearchFields = array();
            foreach ($searchFields as $fieldName => $fieldValue) {
                $processed = $this->processFilterField($fieldName, $fieldValue, $query);
                if ($processed !== false) {
                    $processedSearchFields[] = $processed;
                }
            }
            
            foreach ($processedSearchFields as $processedSearchField) {
                switch ($processedSearchField['queryType']) {
                    case 'and':
                        $fieldName = $this->prependFieldNameNamespace($processedSearchField['fieldName']);
                        $operator = $this->determineWhereOperator($processedSearchField['fieldValue']);
                        $newFieldValue = $this->processFieldValue($processedSearchField['fieldValue'], $operator);
                        // $query->andWhere("$fieldName $operator \"$newFieldValue\"");
                        if ($operator === 'IS NULL') {
                            $query->andWhere("$fieldName $operator");
                        } else {
                            $randomName = 'rand' . rand(1, 1000000);
                            $query->andWhere("$fieldName $operator :$randomName:", array(
                                $randomName => $newFieldValue
                            ));
                        }
                        break;
                    
                    case 'or':
                        // format field name(s) is an array so we can use the same logic below for either circumstance
                        if (! is_array($processedSearchField['fieldName'])) {
                            $fieldNameArray = array(
                                $processedSearchField['fieldName']
                            );
                        } else {
                            $fieldNameArray = $processedSearchField['fieldName'];
                        }
                        
                        // format field value(s) is an array so we can use the same logic below for either circumstance
                        if (! is_array($processedSearchField['fieldValue'])) {
                            $fieldValueArray = array(
                                $processedSearchField['fieldValue']
                            );
                        } else {
                            $fieldValueArray = $processedSearchField['fieldValue'];
                        }
                        
                        // update to bind params instead of using string concatination
                        $queryArr = [];
                        $valueArr = [];
                        $count = 1;
                        foreach ($fieldNameArray as $fieldName) {
                            $fieldName = $this->prependFieldNameNamespace($fieldName);
                            foreach ($fieldValueArray as $fieldValue) {
                                $marker = 'marker' . $count;
                                $operator = $this->determineWhereOperator($fieldValue);
                                $newFieldValue = $this->processFieldValue($fieldValue, $operator);
                                if ($operator === 'IS NULL') {
                                    $queryArr[] = "$fieldName $operator";
                                } else {
                                    $queryArr[] = "$fieldName $operator :$marker:";
                                    $valueArr[$marker] = $newFieldValue;
                                }
                                $count ++;
                            }
                        }
                        $sql = implode(' OR ', $queryArr);
                        $query->andWhere($sql, $valueArr);
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
    protected function processSearchFields($fieldParam)
    {
        if (strpos($fieldParam, '||') !== false) {
            return explode('||', $fieldParam);
        } else {
            return $fieldParam;
        }
    }

    /**
     * This method determines whether the clause should be processed as an 'and' clause or an 'or' clause.
     * This is determined based on the results from the \PhalconRest\API\Entity::processSearchFields() method. If that
     * method returns a string, we are dealing with an 'and' clause, if not, we are dealing with an 'or' clause.
     *
     * @param
     *            string or array $processedFieldName
     * @param
     *            string or array $processedFieldValue
     * @return string
     */
    protected function processSearchFieldQueryType($processedFieldName, $processedFieldValue)
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
    protected function prependFieldNameNamespace($fieldName)
    {
        // $metaData = $this->getDI()->get('memory');
        $searchBits = explode(':', $fieldName);
        
        // if a related table is referenced, then search related model column maps instead of the primary model
        if (count($searchBits) == 2) {
            // build fieldname from 2nd value
            $fieldName = $searchBits[1];
            // start search for a related model
            $matchFound = false;
            foreach ($this->activeRelations as $relation) {
                if ($searchBits[0] == $relation->getTableName()) {
                    
                    // detect the relationship alias
                    $alias = $relation->getAlias();
                    if (! $alias) {
                        $alias = $relation->getReferencedModel();
                    }
                    
                    // set namespace for later pickup
                    $modelNameSpace = $relation->getReferencedModel();
                    $relatedModel = new $modelNameSpace();
                    $colMap = $relatedModel->getAllColumns();
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
            $alias = $this->model->getModelNameSpace();
            $colMap = $this->model->getAllColumns();
        }
        
        // prepend modelNameSpace if the field is detected in the selected model's column map
        foreach ($colMap as $field) {
            if ($fieldName == $field) {
                return "[$alias].$fieldName";
            }
        }
        
        return $fieldName;
    }

    /**
     * Given a fieldValue and operator, filter out the operator from the value
     * ie.
     * search for the wildcard character and replace with an SQL specific wildcard
     *
     * @param string $fieldValue
     *            a search string
     * @param string $operator
     *            the detected field value
     * @return string
     */
    protected function processFieldValue($fieldValue, $operator = '=')
    {
        switch ($operator) {
            case '>':
            case '<':
                return substr($fieldValue, 1);
                break;
            
            case '>=':
            case '<=':
            case '<>':
            case '!=':
                return substr($fieldValue, 2);
                break;
            
            case 'LIKE':
                // process possible wild cards
                $firstChar = substr($fieldValue, 0, 1);
                $lastChar = substr($fieldValue, - 1, 1);
                
                // process wildcards
                if ($firstChar == "*") {
                    $fieldValue = substr_replace($fieldValue, "%", 0, 1);
                }
                if ($lastChar == "*") {
                    $fieldValue = substr_replace($fieldValue, "%", - 1, 1);
                }
                return $fieldValue;
                break;
            
            default:
                return $fieldValue;
                break;
        }
    }

    /**
     * for a given value, figure out what type of operator should be used
     *
     * supported operators are
     *
     * presense of >, <=, >=, <, !=, <> means to use them instead of the default
     * presense of % means use LIKE operator
     * = is the default operator
     *
     * This is determined by the presence of the SQL wildcard character in the fieldValue string
     *
     * @param string $fieldValue            
     * @return string
     */
    protected function determineWhereOperator($fieldValue)
    {
        $defaultOperator = '=';
        
        // process wildcards at start and end
        $firstChar = substr($fieldValue, 0, 1);
        $lastChar = substr($fieldValue, - 1, 1);
        if (($firstChar == "*") || ($lastChar == "*")) {
            return 'LIKE';
        }

        if (strtoupper($fieldValue) === 'NULL') {
            return 'IS NULL';
        }
        
        // process supported comparision operators
        $doubleCharacter = substr($fieldValue, 0, 2);
        // notice how multi character operators are processed first
        $supportedComparisonOperators = [
            '<=',
            '>=',
            '<>',
            '!='
        ];
        foreach ($supportedComparisonOperators as $operator) {
            if ($doubleCharacter === $operator) {
                return $doubleCharacter;
            }
        }
        
        // if nothing else was detected, process single character comparisons
        $supportedComparisonOperators = [
            '>',
            '<'
        ];
        foreach ($supportedComparisonOperators as $operator) {
            if ($firstChar === $operator) {
                return $firstChar;
            }
        }
        
        // nothing special detected, return the standard operator
        return $defaultOperator;
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
        
        // detect the correct name space for sort string
        // notice this might be a fieldname with a sort suffix
        $fieldBits = explode(':', $rawSort);
        if (count($fieldBits) > 1) {
            // isolate just the field name
            $fieldName = $fieldBits[0];
            $suffix = $fieldBits[1];
            $preparedSort = $this->prependFieldNameNamespace($fieldName) . '.' . $suffix;
        } else {
            $preparedSort = $this->prependFieldNameNamespace($rawSort);
        }
        
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
        
        // process all loaded relationships by fetching related data
        foreach ($this->activeRelations as $relation) {
            // check if this relationship has been flagged for custom processing
            $relationOptions = $relation->getOptions();
            if (isset($relationOptions) && (array_key_exists('customProcessing', $relationOptions) && ($relationOptions['customProcessing'] === true))) {
                $this->processCustomRelationships($relation, $baseRecord);
            } else {
                $this->processStandardRelationships($relation, $baseRecord);
            }
        }
        return true;
    }

    /**
     * This method is stubbed out here so that it can be extended and used in local Entity file
     * to do custom processing for certain endpoints
     *
     * @param object $relation            
     * @param array $baseRecord            
     */
    protected function processCustomRelationships($relation, $baseRecord)
    {
        return true;
    }

    /**
     * Standard method for processing relationships
     *
     * @param object $relation            
     * @param array $baseRecord            
     */
    protected function processStandardRelationships($relation, $baseRecord)
    {
        // store parentModels for later use
        $parentModels = $this->getParentModels(true);
        
        // skip any parent relationships because they are merged into the main record
        $refModelNameSpace = $relation->getReferencedModel();
        if ($parentModels and in_array($refModelNameSpace, $parentModels)) {} else {
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
                    // // ignore hasOne since they are processed like a parent relation
                    // // this means current logic will not merg in a parent's record for a hasOne relationship
                    // // it's an edge case but should be supported in the future
                    // continue;
                    
                    // /*
                    // * The following code is not executed
                    // */
                    
                    // // all hasOne relationships would be loaded in the initial query right?
                    // // if the hasOne itself has a parent, then treat it more like a belongsTO so
                    // // merged columns are loaded
                    // $relatedParent = $refModelNameSpace::$parentModel;
                    // if ($relatedParent) {
                    // $relatedRecords = $this->getBelongsToRecord($relation);
                    // } else {
                    // // if a false is detected then load an empty model to takes it's place
                    // // this is likely the result of a left join on a non-existing record
                    // $relatedModel = $baseRecord->$refModelName;
                    // switch ($relatedModel) {
                    // case false:
                    // $relatedModelPath = "\\PhalconRest\\Models\\" . $refModelName;
                    // $emptyModel = new $relatedModelPath();
                    // $relatedRecords = array(
                    // $this->loadAllowedColumns($emptyModel)
                    // );
                    // break;
                    // case null:
                    // // throw error here
                    // break;
                    
                    // default:
                    // $relatedRecords = $this->loadAllowedColumns($relatedModel);
                    // break;
                    // }
                    // }
                } elseif ($refType == 4) {
                    $relatedRecords = $this->getHasManyToManyRecords($relation);
                } else {
                    $relatedRecords = $this->getHasManyRecords($relation);
                }
                
                if (isset($relatedRecords) && $relatedRecords) {
                    return $this->normalizeRelatedRecords($baseRecord, $relatedRecords, $relation);
                }
                
                return true;
            }
        }
    }

    /**
     * Normalize the related records so they can be added into the response object
     *
     * @param unknown $baseRecord            
     * @param unknown $relatedRecords            
     * @param unknown $relation            
     * @return boolean
     */
    protected function normalizeRelatedRecords($baseRecord, $relatedRecords, $relation)
    {
        $refType = $relation->getType();
        
        $refModelNameSpace = $relation->getReferencedModel();
        
        // store a copy of all related record (PKIDs)
        // this must be attached w/ the parent records for joining purposes
        $relatedRecordIds = null;
        $refModel = new $refModelNameSpace();
        $primaryKeyName = $refModel->getPrimaryKeyName();
        
        // save the PKID for each record returned
        if (count($relatedRecords) > 0) {
            // 1 = hasOne 0 = belongsTo 2 = hasMany
            switch ($refType) {
                // process hasOne records as well
                case 1:
                    // do nothin w/ hasOne since these are auto merged into the main record
                    break;
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
        if ($relatedRecordIds !== null) {
            if ($refType == 2 || $refType == 4) {
                $suffix = '_ids';
                // populate the linked property or merge in additional records
                // attempt to store the name similar to the table name
                $name = $relation->getTableName('singular');
                $this->baseRecord[$name . $suffix] = $relatedRecordIds;
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
    protected function updateRestResponse($table, $records)
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
        $allowedFields = $resultSet->getAllowedColumns(false);
        foreach ($allowedFields as $field) {
            if (isset($resultSet->$field)) {
                $record[$field] = $resultSet->$field;
            } else {
                // error, field doesn't exist on resultSet!
                $record[$field] = null;
            }
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
    protected function getHasManyRecords(\PhalconRest\API\Relation $relation)
    {
        $query = $this->buildRelationQuery($relation);
        
        // determine the key to search against
        $field = $relation->getFields();
        if (isset($this->baseRecord[$field])) {
            $fieldValue = $this->baseRecord[$field];
        } else {
            // fall back to using the primaryKeyValue
            $fieldValue = $this->primaryKeyValue;
        }
        
        $query->where("{$relation->getReferencedFields()} = \"$fieldValue\"");
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
        
        if (isset($this->restResponse[$tableName]) and count($this->restResponse[$tableName]) > 0) {
            // figure out how to best refer to the newly loaded field
            // will check for a refence field first, but if that is blocked....
            // try the pkid or even worse, just try "id" as a last resort
            $matchField = 'id';
            
            if (isset($this->restResponse[$tableName][0][$referencedField])) {
                $matchField = $referencedField;
            } elseif (isset($this->restResponse[$tableName][0][$relation->getPrimaryKeyName()])) {
                $matchField = $relation->getPrimaryKeyName();
            }
            
            foreach ($this->restResponse[$tableName] as $row) {
                if ($row[$matchField] == $foreignKeyValue) {
                    return array();
                }
            }
        }
        // query uses model prefix to avoid ambigious queries
        $query->where("{$relation->getReferencedModel()}.{$referencedField} = \"{$this->baseRecord[$foreignKey]}\"");
        $result = $query->getQuery()->execute();
        return $this->loadRelationRecords($result, $relation);
    }

    /**
     * load the query object for a hasManyToMany relationship
     *
     * @param \PhalconRest\API\Relation $relation            
     * @return multitype:array
     */
    protected function getHasManyToManyRecords($relation)
    {
        $refModelNameSpace = $relation->getReferencedModel();
        $intermediateModelNameSpace = $relation->getIntermediateModel();
        
        // determine the key to search against
        $field = $relation->getFields();
        
        $config = $this->getDI()->get('config');
        $modelNameSpace = $config['namespaces']['models'];
        $mm = $this->getDI()->get('modelsManager');
        
        $query = $mm->createBuilder()
            ->from($intermediateModelNameSpace)
            ->join($refModelNameSpace);
        
        $columns = array();
        
        // join in parent record if one is detected
        $parentName = $relation->getParent();
        if ($parentName) {
            $columns[] = "$parentName.*";
            $intField = $relation->getIntermediateReferencedFields();
            $query->join($modelNameSpace . $parentName, "$parentName.$field = $refModelNameSpace.$intField", $parentName);
        }
        
        // Load the main record field at the end, so they are not overwritten
        $columns[] = $refModelNameSpace . ".*, " . $intermediateModelNameSpace . ".*";
        $query->columns($columns);
        
        if (isset($this->baseRecord[$field])) {
            $fieldValue = $this->baseRecord[$field];
        } else {
            // fall back to using the primaryKeyValue
            $fieldValue = $this->primaryKeyValue;
        }
        
        $whereField = $intermediateModelNameSpace . '.' . $relation->getIntermediateFields();
        $query->where("{$whereField} = \"$fieldValue\"");
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
        
        $columns = array();
        
        // hasOnes are auto merged
        // todo should this be controlled by entityWith?
        $list = $relation->getHasOnes();
        foreach ($list as $model) {
            $columns[] = $model . '.*';
            $query->leftJoin($model);
        }
        
        // Load the main record field at the end, so they are not overwritten
        $columns[] = $refModelNameSpace . ".*";
        $query->columns($columns);
        
        return $query;
    }

    /**
     * utility shared between getBelongsToRecord and getHasManyRecords
     * will process a related record result set and return
     * one or more individual record arrays in a larger array
     *
     * @param array $result            
     * @return array
     */
    protected function loadRelationRecords($result, \PhalconRest\API\Relation $relation)
    {
        $relatedRecords = array(); // store all related records
        foreach ($result as $relatedRecord) {
            // reset for each run
            $relatedRecArray = array();
            // when a related record contains hasOne or a parent, merge in those fields as part of side load response
            $parent = $relation->getParent();
            
            if ($parent or get_class($relatedRecord) == 'Phalcon\Mvc\Model\Row') {
                // process records that include joined in parent records
                foreach ($relatedRecord as $rec) {
                    // filter manyHasMany differently than other relationships
                    if ($relation->getType() == 4) {
                        // only intersted in the "end" relationship, not the intermediate
                        $intermediateModelNameSpace = $relation->getIntermediateModel();
                        if ($intermediateModelNameSpace == get_class($rec)) {
                            continue;
                        }
                    }
                    $relatedRecArray = array_merge($relatedRecArray, $this->loadAllowedColumns($rec));
                }
            } else {
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
        
        $this->afterloadActiveRelationships();
        
        return true;
    }

    protected function afterLoadActiveRelationships()
    {
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
     * make it easier to extend default delete logic
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
     * make it easier to extend default delete logic
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
     * make it easier to extend default save logic
     *
     * @param $object the
     *            data submitted to the server
     * @param int|null $id
     *            the pkid of the record to be updated, otherwise null on inserts
     */
    public function beforeSave($object, $id = null)
    {
        // extend me in child class
        return $object;
    }

    /**
     * hook to be run after an entity is saved
     * make it easier to extend default save logic
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
     * built to accommodate saving records w/ parent tables (hasOne)
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
            
            // make sure that the PKID is always stored in the formData
            $name = $this->model->getPrimaryKeyName();
            $formData->$name = $id;
            
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
        
        $result = $this->simpleSave($primaryModel);
        
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
        
        // invalid first param, return false though it won't do much good
        if ($model === false) {
            return false;
        }
        
        if ($model::$parentModel != false) {
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
     * only include specific known fields
     * will also include block fields since it expects there to be blocked at the controller
     *
     * @param PhalconRest\Models $model            
     * @param object $formData            
     *
     * @return a model loaded with all relevant data from the object
     */
    public function loadModelValues($model, $formData)
    {
        // loop through nearly all known fields and save matches
        $metaData = $this->getDI()->get('memory');
        // use a colMap to prepare for save
        $colMap = $metaData->getColumnMap($model);
        if (is_null($colMap)) {
            // but if it isn't present, fall back to attributes
            $colMap = $metaData->getAttributes($model);
        }
        
        foreach ($colMap as $key => $label) {
            if (property_exists($formData, $label)) {
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

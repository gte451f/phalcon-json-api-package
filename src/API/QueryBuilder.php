<?php
namespace PhalconRest\API;

use Phalcon\Di;
use Phalcon\DI\Injectable;
use Phalcon\Mvc\Model\Query\BuilderInterface;
use Phalcon\Mvc\Model\Relation;
use PhalconRest\Exception\HTTPException;


/**
 * helper class to construct a query needed by Entity class
 *
 * Class QueryBuilder
 * @package PhalconRest\API
 */
class QueryBuilder extends Injectable
{

    private $model;
    private $searchHelper;
    private $entity;

    /**
     * process injected model
     *
     * @param BaseModel $model
     * @param SearchHelper $searchHelper
     * @param Entity $entity
     */
    function __construct(BaseModel $model, SearchHelper $searchHelper, Entity $entity)
    {
        $di = Di::getDefault();
        $this->setDI($di);

        // the primary model associated with with entity
        $this->model = $model;

        $this->entity = $entity;

        // a searchHelper, needed anytime we load an entity
        $this->searchHelper = $searchHelper;

    }


    /**
     * build a PHQL based query to be executed by the runSearch method
     * broken up into helpers so extending this function duplicates less code
     *
     * @param boolean $count should we only gather a count of the query?
     */
    public function build($count = false)
    {
        $modelNameSpace = $this->model->getModelNameSpace();
        $mm = $this->getDI()->get('modelsManager');
        $query = $mm->createBuilder()->from($modelNameSpace);

        // $columns = $this->model->getAllowedColumns(true);
        $columns = array(
            "$modelNameSpace.*"
        );

        // process hasOne Joins
        $this->queryJoinHelper($query);
        $this->querySearcheHelper($query);
        $this->querySortHelper($query);

        if ($count) {
            $query->columns('count(*) as count');
        } else {
            // preserve any columns added through joins
            $existingColumns = $query->getColumns();
            $allColumns = array_merge($columns, $existingColumns);
            $query->columns($allColumns);
        }
        // skip limit if returning a count
        if (!$count) {
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
     * @param BuilderInterface $query
     * @return BuilderInterface
     */
    public function queryJoinHelper(BuilderInterface $query)
    {
        $config = $this->getDI()->get('config');
        $modelNameSpace = $config['namespaces']['models'];

        $columns = [];
        // join all active hasOne and belongTo instead of just the parent hasOne
        foreach ($this->entity->activeRelations as $relation) {

            // be sure to skip any relationships that are marked for custom processing
            $relationOptions = $relation->getOptions();
            if (isset($relationOptions) && (array_key_exists('customProcessing',
                        $relationOptions) && ($relationOptions['customProcessing'] === true))
            ) {
                continue;
            }

            // refer to alias or model path to prefix each relationship
            // prefer alias over model path in case of collisions
            $alias = $relation->getAlias();
            $referencedModel = $relation->getReferencedModel();
            if (!$alias) {
                $alias = $referencedModel;
            }

            $type = $relation->getType();
            switch ($type) {
                // structure to always join in belongsTo just in case the query filters by a related field
                case Relation::BELONGS_TO:
                    // create both sides of the join
                    $left = "[$alias]." . $relation->getReferencedFields();
                    $right = $modelNameSpace . $this->model->getModelName() . '.' . $relation->getFields();
                    // create and alias join
                    $query->leftJoin($referencedModel, "$left = $right", $alias);
                    break;

                case Relation::HAS_ONE:
                    // create both sides of the join
                    $left = "[$alias]." . $relation->getReferencedFields();
                    $right = $modelNameSpace . $this->model->getModelName() . '.' . $relation->getFields();
                    // create and alias join
                    $query->leftJoin($referencedModel, "$left = $right", $alias);

                    // add all parent AND hasOne joins to the column list
                    $columns[] = "[$alias].*";
                    break;

                // stop processing these types of joins with the main query.  They might return "n" number of related records
//                case Relation::HAS_MANY_THROUGH:
//                    $alias2 = $alias . '_intermediate';
//                    $left1 = $modelNameSpace . $this->model->getModelName() . '.' . $relation->getFields();
//                    $right1 = "[$alias2]." . $relation->getIntermediateFields();
//                    $query->leftJoin($relation->getIntermediateModel(), "$left1 = $right1", $alias2);
//
//                    $left2 = "[$alias2]." . $relation->getIntermediateReferencedFields();
//                    $right2 = "[$alias]." . $relation->getReferencedFields();
//                    $query->leftJoin($referencedModel, "$left2 = $right2", $alias);
//                    break;

                default:
                    $this->di->get('logger')->warning("Relationship was ignored during join: {$this->model->getModelName()}.$alias, type #$type");
            }

            // attempt to join in side loaded belongsTo records
            // add all parent AND hasOne joins to the column list
            if ($type == Relation::BELONGS_TO) {
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
     * @param BuilderInterface $query *In case we need to join other tables
     *
     * @return array | false
     */
    public function processFilterField($fieldName, $fieldValue, BuilderInterface $query)
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
     * @param BuilderInterface $query
     * @return BuilderInterface $query
     */
    public function querySearcheHelper(BuilderInterface $query)
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
                        if ($operator === 'BETWEEN') {
                            $query->betweenWhere("$fieldName", $newFieldValue[0], $newFieldValue[1]);
                        } else {
                            if ($operator === 'IS NULL' OR $operator === 'IS NOT NULL') {
                                $query->andWhere("$fieldName $operator");
                            } else {
                                $randomName = 'rand' . rand(1, 1000000);
                                $query->andWhere("$fieldName $operator :$randomName:", array(
                                    $randomName => $newFieldValue
                                ));
                            }
                        }
                        break;

                    case 'or':
                        // format field name(s) is an array so we can use the same logic below for either circumstance
                        if (!is_array($processedSearchField['fieldName'])) {
                            $fieldNameArray = array(
                                $processedSearchField['fieldName']
                            );
                        } else {
                            $fieldNameArray = $processedSearchField['fieldName'];
                        }

                        // format field value(s) is an array so we can use the same logic below for either circumstance
                        if (!is_array($processedSearchField['fieldValue'])) {
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

                                if ($operator === 'BETWEEN') {
                                    $queryArr[] = "$fieldName $operator :{$marker}_1: AND :{$marker}_2:";
                                    $valueArr[$marker . '_1'] = $newFieldValue[0];
                                    $valueArr[$marker . '_2'] = $newFieldValue[1];
                                } else {
                                    if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
                                        $queryArr[] = "$fieldName $operator";
                                    } else {
                                        $queryArr[] = "$fieldName $operator :$marker:";
                                        $valueArr[$marker] = $newFieldValue;
                                    }
                                }

                                $count++;
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
     * This method looks for the existence of syntax extensions to the api and attempts to
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
     * this function now checks parent models if not matching column is found in the primary table
     *
     *
     * @param string $fieldName
     * @throws HTTPException
     * @return string
     */
    protected function prependFieldNameNamespace($fieldName)
    {
        $searchBits = explode(':', $fieldName);

        // if a related table is referenced, then search related model column maps instead of the primary model
        if (count($searchBits) == 2) {
            // build field name from 2nd value
            $fieldName = $searchBits[1];
            // start search for a related model
            $matchFound = false;
            foreach ($this->entity->activeRelations as $relation) {
                if ($searchBits[0] == $relation->getTableName()) {

                    // detect the relationship alias
                    $alias = $relation->getAlias();
                    if (!$alias) {
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
                throw new HTTPException("Unknown table prefix supplied in filter.", 500, array(
                    'dev' => "Encountered a table prefix that did not match any known hasOne relationships in the model.",
                    'code' => '891488651361948131461849'
                ));
            }
        } else {
            $alias = $this->model->getModelNameSpace();
            $colMap = $this->model->getAllColumns(false);
        }

        // prepend modelNameSpace if the field is detected in the selected model's column map
        foreach ($colMap as $field) {
            if ($fieldName == $field) {
                return "[$alias].$fieldName";
            }
        }

        // still here?  try the parent model and prepend the parent model alias if the field is detected in that model's column map
        $currentModel = $this->model;
        while ($currentModel) {
            $parentModelNameSpace = $currentModel->getParentModel(true);
            $parentModelName = $currentModel->getParentModel(false);
            // if not parent name specified, skip this part
            if ($parentModelName) {
                $parentModel = new $parentModelNameSpace();
                // loop through all relationships to reference this one by its alias
                foreach ($this->entity->activeRelations as $relation) {
                    $alias = $relation->getAlias();
                    $test = $relation->getModelName();
                    // to detect the parent model we'll compare against either the alias or the full model name
                    if ($parentModelName == $alias || $parentModelName == $relation->getModelName()) {
                        $colMap = $parentModel->getAllColumns(false);
                        foreach ($colMap as $field) {
                            if ($fieldName == $field) {
                                return "[$alias].$fieldName";
                            }
                        }
                    }
                }
                $currentModel = $parentModel;
            } else {
                $currentModel = false;
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
            case 'NOT LIKE':
                // process possible wild cards
                $firstChar = substr($fieldValue, 0, 1);
                $lastChar = substr($fieldValue, -1, 1);

                // process wildcards
                if ($firstChar == "*") {
                    $fieldValue = substr_replace($fieldValue, "%", 0, 1);
                }
                if ($lastChar == "*") {
                    $fieldValue = substr_replace($fieldValue, "%", -1, 1);
                }
                if ($firstChar == "!") {
                    $fieldValue = substr_replace($fieldValue, "%", 0, 1);
                }
                if ($lastChar == "!") {
                    $fieldValue = substr_replace($fieldValue, "%", -1, 1);
                }
                return $fieldValue;
                break;
            case 'BETWEEN':
                $parts = explode("~", $fieldValue);
                if (count($parts) != 3) {
                    throw new HTTPException("A bad filter was attempted.", 500, array(
                        'dev' => "Encountered a between filter without the correct values, please send ~value1~value2",
                        'code' => '975149008326'
                    ));
                }
                $fields[] = $parts[1];
                $fields[] = $parts[2];
                return $fields;
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
        $lastChar = substr($fieldValue, -1, 1);
        if (($firstChar == "*") || ($lastChar == "*")) {
            return 'LIKE';
        }
        if (($firstChar == "!") && ($lastChar == "!")) {
            return 'NOT LIKE';
        }
        if (($firstChar == "~")) {
            return 'BETWEEN';
        }


        if (strtoupper($fieldValue) === 'NULL') {
            return 'IS NULL';
        }

        if (strtoupper($fieldValue) === '!NULL') {
            return 'IS NOT NULL';
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
     * @param BuilderInterface $query
     * @throws HTTPException
     * @return BuilderInterface $query
     */
    public function queryLimitHelper(BuilderInterface $query)
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
     * @param BuilderInterface $query
     * @return BuilderInterface $query
     */
    public function querySortHelper(BuilderInterface $query)
    {
        // process sort
        $rawSort = $this->searchHelper->getSort('sql');

        // detect the correct name space for sort string
        // notice this might be a field name with a sort suffix
        $fieldBits = explode(' ', $rawSort);
        if (count($fieldBits) > 1) {
            // isolate just the field name
            $fieldName = $fieldBits[0];
            $suffix = $fieldBits[1];
            $preparedSort = $this->prependFieldNameNamespace($fieldName) . ' ' . $suffix;
        } else {
            $preparedSort = $this->prependFieldNameNamespace($rawSort);
        }

        if ($preparedSort != false) {
            $query->orderBy($preparedSort);
        }
        return $query;
    }
}

<?php

namespace PhalconRest\API;

use Phalcon\Di;
use Phalcon\DI\Injectable;
use Phalcon\Mvc\Model\Query\BuilderInterface;
use PhalconRest\API\QueryField;
use Phalcon\Mvc\Model\Relation;
use PhalconRest\Exception\HTTPException;
use PhalconRest\Traits\TableNamespace;


/**
 * helper class to construct a query needed by Entity class
 * this class relies heavily on a companion class QueryField which deals with the complexity of
 * which table prefix to use
 * how to escape table or field name
 * and converting supplied inputs into SQL compliant commands
 *
 * Class QueryBuilder
 * @package PhalconRest\API
 */
class QueryBuilder extends Injectable
{

    use TableNamespace;

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
        $this->querySearchHelper($query);
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
     * help $this->queryBuilder to construct a PHQL object
     * apply search rules based on the searchHelper conditions and return query
     *
     * @param BuilderInterface $query
     * @return BuilderInterface $query
     */
    public function querySearchHelper(BuilderInterface $query)
    {
        $searchFields = $this->searchHelper->getSearchFields();
        if ($searchFields) {
            // pre-process the search fields to see if any of the search names require pre-processing
            // mostly just looking for || or type syntax otherwise process as default (and) WHERE clause
            foreach ($searchFields as $fieldName => $fieldValue) {
                $queryField = new QueryField($fieldName, $fieldValue, $this->model, $this->entity);
                if ($queryField->isValid() === true) {
                    $query = $queryField->addWhereClause($query);
                }
            }
        }
        return $query;
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

        if ($rawSort != false) {
            // detect the correct name space for sort string
            // notice this might be a field name with a sort suffix
            $fieldBits = explode(' ', $rawSort);

            // used to harmonize string after stripping out suffix
            $fieldName = $fieldBits[0];

            // assign a default value
            $suffix = '';

            if (count($fieldBits) > 1) {
                // isolate just the field name
                $fieldName = $fieldBits[0];
                // something like DESC/ASC
                $suffix = $fieldBits[1];
            }

            $prefix = $this->getTableNameSpace($fieldName);
            $preparedSort = ($prefix ? $prefix . '.' : '') . "[$fieldName]" . $suffix;
            $query->orderBy($preparedSort);
        } else {
            // no sort requested, nothing to do here
            return $query;
        }
    }
}

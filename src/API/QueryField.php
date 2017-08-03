<?php

namespace PhalconRest\API;

use Phalcon\Di;
use Phalcon\DI\Injectable;
use PhalconRest\Exception\HTTPException;
use \Phalcon\Mvc\Model\Query\Builder;
use PhalconRest\Traits\TableNamespace;


/**
 * helper class that provides utilities for the QueryBuilder when handling database fields
 *
 * Some responsibilities include:
 * determine which table prefix to use
 * escape table or field name
 * convert supplied inputs into PHQL functions which we add to a supplied query
 *
 * a worst case example is as follows
 * user:first_name||user:last_name=*john*||*mary*
 *
 *
 * Class QueryField
 * @package PhalconRest\API
 */
class QueryField extends Injectable
{
    use TableNamespace;

    /**
     * store the originally supplied field name
     * @var null|string
     */
    private $name = null;

    /**
     * store the originally supplied field value
     * @var mixed|null|string
     */
    private $value = null;

    /**
     * supplied model for this query
     * @var null|BaseModel
     */
    private $model = null;
    /**
     * supplied Entity for this query
     * @var null|Entity
     */
    private $entity = null;

    /**
     * QueryField constructor.
     *
     * @param $name string the name of the field to be added to the processedSearchFields array
     * @param $value mixed the value to the query
     * @param BaseModel $model
     * @param Entity $entity
     */
    function __construct(string $name, string $value, BaseModel $model, Entity $entity)
    {
        $di = Di::getDefault();
        $this->setDI($di);

        $this->name = $name;
        $this->value = $value;
        $this->model = $model;
        $this->entity = $entity;
    }


    /**
     * internally valid the field, is it going to work for the query builder?
     * this might not be used in the future, just a placeholder for now
     * @return bool
     */
    public function isValid()
    {
        return true;
    }

    /**
     * This method looks for the existence of syntax extensions to the api and attempts to
     * adjust search input values before subjecting them to the queryBuilder
     *
     * it should handle cases where the fields are prefixed with a :
     * it should also handle cases where a || exists
     * first_name||last_name=*jenkins*
     * or
     * table_name:field_name||table_name:field_name
     *
     * when an array is requested, will return the following:
     *
     * ['table'=>$, 'field'=>]
     *
     *
     * @param bool $forceArray
     * @return array
     */
    public function getName()
    {

        $processedNames = [];
        // process || syntax
        if (strpos($this->name, '||') !== false) {
            $rawNames = explode('||', $this->name);
        } else {
            $rawNames = [$this->name];
        }

        foreach ($rawNames as $name) {
            // process : syntax in case a table prefix is supplied
            if (strpos($name, ':') !== false) {
                $nameBits = explode(':', $name);
                $processedNames[] = ['table' => $nameBits[0], 'field' => $nameBits[1], 'original' => $name];
            } else {
                $processedNames[] = ['field' => $name, 'original' => $name];
            }
        }

        // all that left is to return what you found
        return $processedNames;
    }

    /**
     * This method looks for the existence of syntax extensions to the api and attempts to
     * adjust search input values before subjecting them to the queryBuilder
     *
     * The 'or' operator || explodes the given parameter on that operator if found
     *
     * first_name=jim||john
     * first_name||last_name=jim
     *
     * @param bool $forceArray
     * @return array|string
     */
    public function getValue($forceArray = false)
    {
        // process || syntax
        if (strpos($this->value, '||') !== false) {
            return explode('||', $this->value);
        } else {
            return ($forceArray ? [$this->value] : $this->value);
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
    protected function processSearchFieldQueryType()
    {
        // prep the fields
        $processedFieldName = $this->getName();
        $processedFieldValue = $this->getValue();
        // set a default value
        $result = 'and';

        if (count($processedFieldName) > 1 || is_array($processedFieldValue)) {
            $result = 'or';
        }

        return $result;
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
    protected function determineWhereOperator(string $fieldValue)
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
     * An additional parse function to deal with advanced searches where a comparision operator is supplied
     * Given a fieldValue and operator, filter out the operator from the value
     * ie.
     * search for the wildcard character and replace with an SQL specific wildcard
     *
     *
     * @param string $fieldValue - a search string
     * @param string $operator comparision value
     * @return mixed
     * @throws HTTPException
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
                    throw new HTTPException("A bad filter was attempted.", 500, [
                        'dev' => "Encountered a between filter without the correct values, please send ~value1~value2",
                        'code' => '975149008326'
                    ]);
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
     * when handed a query object, add the derived WHERE clause to the object
     * this is derived from the QueryFields internal state
     *
     * @param Builder $query
     * @return Builder
     */
    public function addWhereClause(Builder $query)
    {

        switch ($this->processSearchFieldQueryType()) {
            case 'and':
                return $this->parseAdd($query);
                break;

            case 'or':
                return $this->parseOr($query);
                break;
        }
        return $query;
    }


    /**
     * compile the correct WHERE clause and add it to the supplied Query object
     * this function expects to be called when BOTH the name and value properties are strings
     * otherwise, a bug will result!
     *
     * @param $query
     * @return mixed
     * @throws HTTPException
     */
    private function parseAdd($query)
    {

        $operator = $this->determineWhereOperator($this->getValue());
        $name = $this->getName();
        $value = $this->getValue();

        $foo = count($name);

        // validate
        if (is_array($value) OR count($name) > 1) {
            // ERROR
            throw new HTTPException("Encountered Array when processing a simple Add request.", 500, [
                'dev' => "parseAdd is built for simple values, but it was run on multiple values.  send this to OR!",
                'code' => '4891319849797'
            ]);
        }

        // this is a safe request since we ruled out possible alternatives
        $prefix = $this->getTableNameSpace($this->name);
        // disentangle the table from the field name

        $fieldName = ($prefix ? $prefix . '.' : '') . "[{$name[0]['field']}]";
        $fieldValue = $this->processFieldValue($value, $operator);

        if ($operator === 'BETWEEN') {
            // expect newFieldValue to be an array
            $query->betweenWhere($fieldName, $fieldValue[0], $fieldValue[1]);
        } else {
            if ($operator === 'IS NULL' OR $operator === 'IS NOT NULL') {
                $query->andWhere("$fieldName $operator");
            } else {
                $randomName = 'rand' . rand(1, 1000000);
                $query->andWhere("$fieldName $operator :$randomName:", [
                    $randomName => $fieldValue
                ]);
            }
        }

        return $query;
    }

    /**
     * compile the correct WHERE clause and add it to the supplied Query object
     *
     * @param $query
     * @return mixed
     */
    private function parseOr($query)
    {

        $nameArray = $this->getName();
        $valueArray = $this->getValue(true);

        // update to bind params instead of using string concatenation
        $queryArr = [];
        $valueArr = [];

        $count = 1;
        foreach ($nameArray as $name) {
            $prefix = $this->getTableNameSpace($name['original']);
            $fieldName = ($prefix ? $prefix . '.' : '') . "[{$name['field']}]";

            foreach ($valueArray as $value) {
                $marker = 'marker' . $count;
                $operator = $this->determineWhereOperator($value);
                $fieldValue = $this->processFieldValue($value, $operator);

                if ($operator === 'BETWEEN') {
                    $queryArr[] = "$fieldName $operator :{$marker}_1: AND :{$marker}_2:";
                    $valueArr[$marker . '_1'] = $fieldValue[0];
                    $valueArr[$marker . '_2'] = $fieldValue[1];
                } else {
                    if ($operator === 'IS NULL' || $operator === 'IS NOT NULL') {
                        $queryArr[] = "$fieldName $operator";
                    } else {
                        $queryArr[] = "$fieldName $operator :$marker:";
                        $valueArr[$marker] = $fieldValue;
                    }
                }

                $count++;
            }
        }
        $sql = implode(' OR ', $queryArr);
        $query->andWhere($sql, $valueArr);

        return $query;
    }
}


<?php

namespace PhalconRest\Rules;

use Phalcon\Di;
use Phalcon\DI\Injectable;
use PhalconRest\Exception\HTTPException;


/**
 * a very simple class to describe a rule that should always be applied to API calls
 * the most common use case is for READ operations but can be applied to Edit or Delete operations
 * due to the nature of this rule it doesn't make sense to use for Delete operations
 *
 * For Read operations, this rule is applied to the $Entity->searchHelper
 * side loading?
 * edit/delete?
 */
class FilterRule
{

    /**
     * store the supplied field name
     * @var null|string
     */
    public $name;

    /**
     * store the supplied field value
     * @var mixed|null|string
     */
    public $value;

    /**
     * original field value .... for the moment
     * @var string
     */
    public $field;

    /**
     * store the supplied filter operator
     * @var null|string
     */
    public $operator;


    /**
     * permissions that describe when to apply this rule
     * @var int
     */
    public $crud = 0;

    /**
     * store the name of the table that is referenced in the rule
     */
    public $parentTable = FALSE;

    /**
     * RelationFilter constructor.
     *
     * @param string $field
     * @param string $value
     * @param string $operator
     * @param int $crud
     */
    function __construct(string $field, string $value, $operator = null, int $crud = READMODE)
    {
        $this->crud = $crud;
        $this->field = $field;
        $this->value = $value;
        $this->operator = $operator;

        $fieldBits = explode(':', $field);
        // parent field search detected, register it
        if (count($fieldBits) == 2) {
            $this->parentTable = $fieldBits[0];
        }
    }


}
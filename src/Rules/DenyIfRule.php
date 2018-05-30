<?php

namespace PhalconRest\Rules;

use Phalcon\Di;
use Phalcon\DI\Injectable;
use PhalconRest\Exception\HTTPException;


/**
 * experimenting with a rule to return true/false based on particular record value
 *
 * For Read operations, this rule is applied to ...?
 */
class DenyIfRule
{

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
     * RelationFilter constructor.
     *
     * @param string $field
     * @param string $value
     * @param string $operator
     * @param int $crud
     */
    function __construct(string $field, string $value, $operator = '==', int $crud = DELETERULES)
    {
        $this->crud = $crud;
        $this->field = $field;
        $this->value = $value;
        $this->operator = $operator;
    }


    /**
     * for a supplied value, evaluate it against the defined rule set
     *
     * @param $fieldValue
     * @return bool
     */
    public function evaluateRule($fieldValue)
    {
        // what type of variable are we dealing with?
        // todo maybe pick and choose when to treat variable as string
        $testFunction = "('" . $fieldValue . "' $this->operator  '$this->value' ) ? true : false ;";
        return eval($testFunction);
    }

}
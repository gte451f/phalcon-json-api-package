<?php

namespace PhalconRest\Rules;

use Phalcon\Di;
use Phalcon\DI\Injectable;
use PhalconRest\Exception\HTTPException;

/**
 * a very simple class to manage rules for provided model
 */
class Store
{

    /**
     * store a list of all rules define for this store
     *
     * @var array
     */
    private $rules = [];


    /**
     * the model this rule applies to
     * @var null|BaseModel t
     */
    private $model = null;


    /**
     * Store constructor.
     * @param \PhalconRest\API\BaseModel $model
     */
    function __construct(\PhalconRest\API\BaseModel $model)
    {
        $this->model = $model;

    }


    /**
     * get all rules for the relevant action
     * If no mode is supplied, all rules are returned
     *
     * @param int|NULL $crud
     * @return array
     */
    public function getRules(int $crud = NULL)
    {
        if ($crud == NULL) {
            return $this->rules;
        }

        $ruleSet = [];
        foreach ($this->rules as $rule) {
            if ($rule->crud & $crud) {
                $ruleSet[] = $rule;
            }
        }
        return $ruleSet;
    }


    /**
     * add a rule to the store
     *
     * @param string $field
     * @param string $value
     * @param string $operator
     * @param int $crud
     */
    public function addRule(string $field, string $value, $operator = null, int $crud = READRULES)
    {
        // if an existing rule is detected, over write
        $this->rules[$field] = new \PhalconRest\Rules\Rule($field, $value, $operator, $crud);
    }


    /**
     * clear all rules of particular mode
     * If no mode is supplied, all rules are wiped
     *
     * @param int|NULL $crud
     * @return bool
     */
    public function clearRules(int $crud = NULL)
    {
        if ($crud == NULL) {
            $this->rules = [];
            return true;
        }

        $ruleSet = [];
        foreach ($this->rules as $rule) {
            if (!$rule->crud & $crud) {
                $ruleSet[] = $rule;
            }
        }
        $this->rules = $ruleSet;
        return true;

    }
}
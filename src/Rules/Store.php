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
     * @param string|NULL $type
     * @return array
     * @throws \ReflectionException
     */
    public function getRules(int $crud = NULL, string $type = NULL)
    {
        // first pass to match crud filter
        if ($crud == NULL) {
            $ruleSet = $this->rules;
        } else {
            $ruleSet = [];
            foreach ($this->rules as $rule) {
                if ($rule->crud & $crud) {
                    $ruleSet[] = $rule;
                }
            }

        }

        // first pass to match crud filter
        if ($type == NULL) {
            return $ruleSet;
        } else {
            $filteredRuleSet = [];
            foreach ($ruleSet as $rule) {
                $reflection = new \ReflectionClass($rule);
                $className = $reflection->getShortName();
                if ($className == $type) {
                    $filteredRuleSet[] = $rule;
                }
            }
            return $filteredRuleSet;
        }
    }


    /**
     * add a filter rule into the store
     *
     * @param string $field
     * @param string $value
     * @param string $operator
     * @param int $crud
     */
    public function addFilterRule(string $field, string $value, $operator = null, int $crud = READRULES)
    {
        // if an existing rule is detected, over write
        $this->rules[$field] = new \PhalconRest\Rules\FilterRule($field, $value, $operator, $crud);
    }


    /**
     * load a query rule into the store
     *
     * @param string $rule
     * @param int $crud
     */
    public function addQueryRule(string $rule, int $crud = READRULES)
    {
        // if an existing rule is detected, over write
        $this->rules[rand(1, 9999)] = new \PhalconRest\Rules\QueryRule($rule, $crud);
    }


    /**
     * load a block rule into the store
     *
     * @param string $rule
     * @param int $crud
     */
    public function addDenyRule(int $crud = READRULES)
    {
        // if an existing rule is detected, over write
        $this->rules[rand(1, 9999)] = new \PhalconRest\Rules\DenyRule($crud);
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
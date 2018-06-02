<?php

namespace PhalconRest\Rules;

use Phalcon\Di;
use Phalcon\DI\Injectable;
use PhalconRest\Exception\HTTPException;
use PhalconRest\Rules;

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
     * should the rule store disable its normal operation
     * why would one do this?
     * password reminder
     *
     * @var bool
     */
    private $enforceRules = true;

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
        $this->rules[$field] = new FilterRule($field, $value, $operator, $crud);
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
        $this->rules[rand(1, 99999)] = new QueryRule($rule, $crud);
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
        $this->rules[rand(1, 99999)] = new DenyRule($crud);
    }


    /**
     * load a modelCallBack rule into the store
     *
     * @param \Closure $check
     * @param int $crud
     */
    public function addModelCallbackRule(\Closure $check, $crud = DELETERULES)
    {
        // if an existing rule is detected, over write
        $this->rules[rand(1, 99999)] = new ModelCallbackRule($check, $crud);
    }

    /**
     * load a deny if rule into the store
     * ie.
     * $ruleStore->addDenyIfRule('status', Invoices::ST_BILLED, '=', DELETERULES);
     *
     * @param $field
     * @param $value
     * @param string $operator
     * @param int $crud
     */
    public function addDenyIfRule($field, $value, $operator = '==', $crud = DELETERULES)
    {
        // if an existing rule is detected, over write
        $this->rules[rand(1, 99999)] = new DenyIfRule($field, $value, $operator, $crud);
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

    /**
     * tell the rulestore to ignore it's rules
     */
    final public function disable()
    {
        $this->enforceRules = false;
    }

    /**
     * tell the rulestore to enforce it's rules
     */
    final public function enable()
    {
        $this->enforceRules = true;
    }

    /**
     * is the rule store currently enforcing rules?
     * @return bool
     */
    final public function isEnforcing()
    {
        return $this->enforceRules;
    }
}
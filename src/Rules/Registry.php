<?php
/**
 * Created by PhpStorm.
 * User: jjenkins
 * Date: 6/2/18
 * Time: 12:26 PM
 */

namespace PhalconRest\Rules;

use Phalcon\Di;
use Phalcon\DI\Injectable;
use PhalconRest\Exception\HTTPException;
use PhalconRest\Rules;


class Registry
{

    /**
     * should the rule store disable its normal operation
     * why would one do this?
     * password reminder
     *
     * @var bool
     */
    private $enforceRules = true;


    /**
     * store all rule stores by their model name
     * @var array
     */
    private $store = [];


    /**
     * load rule store and conform to general activation settings
     *
     * @param $key
     * @param $value
     */
    public function update($key, $value)
    {
        //force store to match registry enforcement setting
        if ($this->enforceRules) {
            $value->enable();
        } else {
            $value->disable();
        }
        $this->store[$key] = $value;
    }

    /**
     * @param $key
     * @return mixed|null
     */
    public function get($key)
    {
        if (array_key_exists($key, $this->store)) {
            return $this->store[$key];
        }
        return null;
    }

    /**
     * construct a new Store object configured for enforcement
     *
     * @param $model
     * @return Store
     */
    public function getNewStore(\PhalconRest\API\BaseModel $model): \PhalconRest\Rules\Store
    {
        $newStore = new \PhalconRest\Rules\Store($model);
        //force store to match registry enforcement setting
        if ($this->enforceRules) {
            $newStore->enable();
        } else {
            $newStore->disable();
        }
        return $newStore;
    }

    /**
     * tell the rule register to deactivate
     */
    final public function disable()
    {
        $this->enforceRules = false;
        foreach ($this->store as $store) {
            $store->disable();
        }
    }

    /**
     * tell the rulestore to enforce it's rules
     */
    final public function enable()
    {
        $this->enforceRules = true;
        foreach ($this->store as $store) {
            $store->enable();
        }
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
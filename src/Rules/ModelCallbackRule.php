<?php

namespace PhalconRest\Rules;

use Phalcon\Di;
use Phalcon\DI\Injectable;
use PhalconRest\Exception\HTTPException;


/**
 * experimental rule
 *
 *
 *
 */
class ModelCallbackRule
{


    /**
     * permissions that describe when to apply this rule
     * @var int
     */
    public $crud = 0;

    /**
     * the function to run
     *
     * @var callback
     */
    public $check;

    /**
     * QueryRule constructor.
     * @param \Closure $check
     * @param int $crud
     */
    function __construct(\Closure $check, $crud = DELETERULES)
    {
        $this->crud = $crud;
        $this->check = $check;

        // $check();


    }

    /**
     * for a supplied parameters, run the call back
     * do not return a value, let call back handle what it finds and processes
     *
     * @param \PhalconRest\API\BaseModel $model
     * @param $formData
     * @return void
     */
    public function evaluateCallback(\PhalconRest\API\BaseModel $oldModel, $formData)
    {
        $check = $this->check;
        $check($oldModel, $formData);
    }

}
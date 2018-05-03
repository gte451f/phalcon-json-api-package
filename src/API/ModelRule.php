<?php

namespace PhalconRest\API;

use Phalcon\Di;
use Phalcon\DI\Injectable;
use PhalconRest\Exception\HTTPException;


/**
 * a very simple class to describe a rule that should always be applied to API calls relating to this model
 */
class ModelRule
{

    /**
     * store the supplied field name
     * @var null|string
     */
    public $name = null;

    /**
     * store the supplied field value
     * @var mixed|null|string
     */
    public $value = null;

    /**
     * store the supplied filter operator
     * @var null|string
     */
    public $operator = null;


    private $crud = 0;

    private $model = null;
    private $rule = null;

    /**
     * RelationFilter constructor.
     *
     * @param string $field
     * @param string $value
     * @param BaseModel $model
     * @param string $operator
     * @param int $crud
     */
    function __construct(string $field, string $value, \PhalconRest\API\BaseModel $model, string $operator = '=', int $crud = 1)
    {
        $this->model = $model;
        $this->crud = $crud;
        $this->field = $field;
        $this->value = $value;
        $this->operator = $operator;
    }


}
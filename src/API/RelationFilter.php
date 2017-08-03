<?php

namespace PhalconRest\API;

use Phalcon\Di;
use Phalcon\DI\Injectable;
use PhalconRest\Exception\HTTPException;


/**
 * a very simple class to describe how to filter relations describe in a Model
 */
class RelationFilter
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

    /**
     * RelationFilter constructor.
     * @param string $name
     * @param string $value
     * @param string $operator
     */
    function __construct(string $name, string $value, string $operator = '=')
    {
        $this->operator = $operator;
        $this->value = $value;
        $this->name = $name;
    }
}
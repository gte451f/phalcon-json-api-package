<?php

namespace PhalconRest\Rules;

use Phalcon\Di;
use Phalcon\DI\Injectable;
use PhalconRest\Exception\HTTPException;


/**
 * specialty class designed to work directly on a query builder object
 *
 * the most common use case is for READ operations but can be applied to Edit or Delete operations
 * due to the nature of this rule it doesn't make sense to use for Delete operations
 *
 *
 *
 */
class QueryRule
{


    /**
     * permissions that describe when to apply this rule
     * @var int
     */
    public $crud = 0;

    /**
     * the PHQL clause to add to each Entity query
     *
     * @var string
     */
    public $clause;

    /**
     * QueryRule constructor.
     * @param string $clause
     * @param int $crud
     */
    function __construct(string $clause, int $crud = READMODE)
    {
        $this->crud = $crud;
        $this->clause = $clause;
    }


}
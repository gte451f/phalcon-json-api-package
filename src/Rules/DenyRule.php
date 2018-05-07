<?php

namespace PhalconRest\Rules;

use Phalcon\Di;
use Phalcon\DI\Injectable;
use PhalconRest\Exception\HTTPException;


/**
 * specialty class designed prevent access to an end point
 * if READ is denied, all other modes are denied as well
 *
 * this rule will deny all access for the specified mode
 * for more nuanced conditions, use other rules
 *
 */
class DenyRule
{

    /**
     * permissions that describe when to apply this rule
     * @var int
     */
    public $crud = 0;

    /**
     * DenyRule constructor.
     * @param int $crud
     */
    function __construct(int $crud = READMODE)
    {
        $this->crud = $crud;
    }


}
<?php

namespace PhalconRest\Result;

/**
 * Basic object to store a single Relationship object
 */
class Relationships extends \Phalcon\DI\Injectable
{

    /**
     * @var int
     */
    private $id;
    /**
     * @var string
     */
    private $type;

    /**
     * @var array
     */

    public function __construct($id, $type)
    {
        $di = \Phalcon\DI::getDefault();
        $this->setDI($di);

        //parse supplied data array and populate object
        $this->id = $id;
        $this->type = $type;
    }
}
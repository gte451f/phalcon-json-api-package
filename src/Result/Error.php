<?php
namespace PhalconRest\Result;

/**
 * Basic object to store a single Data object, one or more data objects are strung together for
 * a complete JSON API response
 */
class Error extends \Phalcon\DI\Injectable
{

    /**
     * @var int
     */
    private $id;
    /**
     * @var string
     */
    private $status;
    /**
     * @var string
     */
    private $code;
    /**
     * @var string
     */
    private $title;

    /**
     * @var string
     */
    private $detail;

    /**
     * store a dynamic object of error properties
     * @var \stdClass
     */
    private $meta;

    public function __construct($id, $status = null, $code = null, $title = null, $detail = null, array $meta = [])
    {
        $di = \Phalcon\DI::getDefault();
        $this->setDI($di);

        //parse supplied error and populate object
        $this->meta = new \stdClass();
        foreach ($meta as $key => $value) {
            $this->meta->$key = $value;
        }

        $this->id = $id;
        $this->status = $status;
        $this->code = $code;
        $this->title = $title;
        $this->detail = $detail;
    }


}
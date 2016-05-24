<?php
namespace PhalconRest\Result;

use PhalconRest\Result\Data;

/**
 * The object used to store intermediate api results before they are sent to the client
 * This result object is designed specifically for use in JSON API and is not intended as a general purpose result collection
 */
class Result extends \Phalcon\DI\Injectable
{

    // a collection of individual data objects
    private $data = [];
    private $meta = false;
    private $errors = [];
    // store a collection of data like items
    private $included = [];

    /**
     * @var string is the result going to output a standard json payload or some sort of error?
     * data | error
     */
    private $outputMode = 'data';

    public function __construct()
    {
        $di = \Phalcon\DI::getDefault();
        $this->setDI($di);
    }


    public function addData(Data $newData)
    {
        $this->data[] = $newData;
    }

    public function addErrors($id, $status = null, $code = null, $title = null, $detail = null, array $meta = [])
    {
        $this->outputMode = 'error';
        $this->errors[] = new Error($id, $status, $code, $title, $detail, $meta);
    }

    public function addIncluded(Data $newData)
    {
        $this->included[] = $newData;
    }

    public function addMeta($key, $value)
    {
        // flag for initial use
        if (!$this->meta) {
            $this->meta = new \stdClass();
        }
        $this->meta->$key = $value;
    }

    public function outputJSON()
    {
        $result = new \stdClass();
        if ($this->outputMode == 'data') {
            $result->data = $this->data;
            if (count($this->included) > 0) {
                $result->included = $this->included;
            }
        } else {
            $result->errors = $this->errors;
        }
        // only include if it has valid data
        if ($this->meta) {
            $result->meta = $this->meta;
        }

        // return json_encode($result);
        return $result;
    }

    /**
     * for a supplied primary id and relate id, create a relationship
     * @param $id
     * @param $tableName
     * @param $related_id
     * @return boolean
     */
    public function addRelationship($tableName, $id, $related_id)
    {
        foreach ($this->data as $key => $data) {
            if ($data->getId() == $id) {
                $this->data[$key]->addRelationship($tableName, $related_id);
            }
            return true;
        }
        return false;
    }

}
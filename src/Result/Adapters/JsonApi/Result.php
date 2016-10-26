<?php
namespace PhalconRest\Result\Adapters\JsonApi;

use PhalconRest\Exception\HTTPException;

class Result extends \PhalconRest\Result\Result
{

    public function outputJSON()
    {
        $result = new \stdClass();
        if ($this->outputMode != 'error') {
            if ($this->outputMode == 'single') {
                $result->data = $this->data[0];
            } elseif ($this->outputMode == 'multiple') {
                $result->data = $this->data;
            } elseif ('other') {
                // do nothing for this output mode
            } else {
                throw new HTTPException("Error generating output.  Could not match output mode with data set.", 500,
                    array(
                        'code' => '894684684646846816161'
                    ));
            }

            // process included records
            if (count($this->included) > 0) {
                $result->included = $this->included;
            }
        } else {
            $result->errors = $this->errors;
        }

        // include plain non-namespaced data
        foreach ($this->plain as $key => $value) {
            $result->$key = $value;
        }

        // only include if it has valid data
        if ($this->meta) {
            $result->meta = $this->meta;
        }

        // return json_encode($result);
        return $result;
    }

}
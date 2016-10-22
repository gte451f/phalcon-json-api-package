<?php
namespace PhalconRest\Result\Adapters\JsonApi;

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
            } else {
                throw new HTTPException("Error generating output.  Unknown output mode submitted.", 500, array(
                    'code' => '894684684646846816161'
                ));
            }

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

}
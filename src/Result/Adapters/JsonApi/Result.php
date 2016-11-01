<?php
namespace PhalconRest\Result\Adapters\JsonApi;

use PhalconRest\Exception\HTTPException;

class Result extends \PhalconRest\Result\Result
{

    public function outputJSON()
    {
        $result = new \stdClass();

        if ($this->outputMode != self::MODE_ERROR) {
            switch ($this->outputMode) {
                case self::MODE_SINGLE:
                    $result->data = $this->data[0];
                    break;

                case self::MODE_MULTIPLE:
                    $result->data = $this->data;
                    break;

                case self::MODE_OTHER:
                    // do nothing for this output mode
                    break;

                default:
                    throw new HTTPException('Error generating output.  Cannot match output mode with data set.', 500, [
                        'code' => '894684684646846816161'
                    ]);
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
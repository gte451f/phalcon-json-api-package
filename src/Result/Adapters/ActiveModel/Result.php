<?php
namespace PhalconRest\Result\Adapters\ActiveModel;

use PhalconRest\Exception\HTTPException;

class Result extends \PhalconRest\Result\Result
{

    /**
     * will convert the intermediate data result data into ActiveModel compatible result object
     *
     * @return \stdClass
     */
    public function outputJSON()
    {
        $result = new \stdClass();

        // this is used to ensure there is at least a blank array when requesting records
        if ($this->type !== false) {
            $result->{$this->type} = [];
        }

        if ($this->outputMode != 'error') {
            if ($this->outputMode == 'single' AND count($this->data) > 0) {
                $data = $this->data[0];
                $type = $data->getType();
                $result->$type = $data;
            } elseif ($this->outputMode == 'multiple') {
                // push all data records into the result set
                foreach ($this->data as $data) {
                    $type = $data->getType();
                    if (!isset($result->$type)) {
                        $result->$type = [];
                    }
                    array_push($result->$type, $data);
                }
            } elseif ('other') {
                // do nothing for this output mode
            } else {
                throw new HTTPException("Error generating output.  Could not match output mode with data set.", 500,
                    array(
                        'code' => '894684684646846816161'
                    ));
            }

            // push all includes into the result set
            // for the purpose of active model, they look just like data records
            foreach ($this->included as $data) {
                $type = $data->getType();
                if (!isset($result->$type)) {
                    $result->$type = [];
                }
                array_push($result->$type, $data);
            }

            // include plain non-namespaced data
            foreach ($this->plain as $key => $value) {
                $result->$key = $value;
            }
        } else {
            $result->errors = $this->errors;
        }
        // only include if it has valid data
        if ($this->meta) {
            $result->meta = $this->meta;
        }

        return $result;
    }

}
<?php
namespace PhalconRest\Result\Adapters\ActiveModel;

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
        if ($this->outputMode != 'error') {
            if ($this->outputMode == 'single') {
                $data = $this->data[0];
                $type = $data->getType();
                $result->$type = [$data];
            } elseif ($this->outputMode == 'multiple') {
                // push all data records into the result set
                foreach ($this->data as $data) {
                    $type = $data->getType();
                    if (!isset($result->$type)) {
                        $result->$type = [];
                    }
                    array_push($result->$type, $data);
                }
            } else {
                throw new HTTPException("Error generating output.  Unknown output mode submitted.", 500, array(
                    'code' => '894684684646846816161'
                ));
            }

            // push all includes into the result set
            foreach ($this->included as $data) {
                $type = $data->getType();
                if (!isset($result->$type)) {
                    $result->$type = [];
                }
                array_push($result->$type, $data);
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
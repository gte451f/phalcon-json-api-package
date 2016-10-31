<?php
namespace PhalconRest\Result\Adapters\ActiveModel;

use PhalconRest\Exception\HTTPException;

class Result extends \PhalconRest\Result\Result
{

    /**
     * will convert the intermediate data result data into ActiveModel compatible result object
     * @return \stdClass
     * @throws HTTPException
     */
    public function outputJSON()
    {
        $result = new \stdClass();

        // this is used to ensure there is at least a blank array when requesting records
        if ($this->type !== false) {
            $result->{$this->type} = [];
        }

        if ($this->outputMode != self::MODE_ERROR) {
            switch ($this->outputMode) {
                case self::MODE_SINGLE:
                    if (count($this->data)) {
                        $data = $this->data[0];
                        $type = $data->getType();
                        $result->$type = $data;
                    }
                    break;

                case self::MODE_MULTIPLE:
                    // push all data records into the result set
                    foreach ($this->data as $data) {
                        $type = $data->getType();
                        if (!isset($result->$type)) {
                            $result->$type = [];
                        }
                        array_push($result->$type, $data);
                    }
                    break;

                case self::MODE_OTHER:
                    // do nothing for this output mode
                    break;

                default:
                    throw new HTTPException('Error generating output.  Cannot match output mode with data set.', 500, [
                        'code' => '894684684646846816161'
                    ]);
            }

            // push all includes into the result set. for the purpose of active model, they look just like data records
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
<?php
namespace PhalconRest\Result\Adapters\ActiveModel;

use Phalcon\Mvc\Model\Message;
use PhalconRest\Exception\HTTPException;

class Result extends \PhalconRest\Result\Result
{

    /**
     * will convert the intermediate data result data into ActiveModel compatible result object
     * @return \stdClass
     * @throws HTTPException
     */
    protected function formatJSON()
    {
        $result = new \stdClass;

        if ($this->outputMode == self::MODE_ERROR) {
            $this->formatFailure($result);
        } else {

            // this is used to ensure there is at least a blank array when requesting records
            if ($this->type !== false) {
                $result->{$this->type} = [];
            }

            $this->formatSuccess($result);
        }

        // only include if it has valid data
        if ($this->meta) {
            $result->meta = $this->meta;
        }

        return $result;
    }

    /**
     * handle situations where an error occurred
     *
     * @param $result
     */
    protected function formatFailure($result)
    {
        $appConfig = $this->di->get('config')['application'];
        $inflector = $this->di->get('inflector');

        $result->errors = [];
        foreach ($this->errors as $error) {
            $details = [
                'title' => $error->title,
                'code' => $error->code,
                'details' => $error->more
            ];

            if ($error->validationList) {
                foreach ($error->validationList as $validation) {
                    $field = $inflector->normalize($validation->getField(), $appConfig['propertyFormatTo']);
                    $details[$field] = $validation->getMessage();
                }
            }

            if ($appConfig['debugApp']) {
                $meta = array_filter([ //clears up empty keys
                    'developer_message' => $error->dev,
                    'file' => $error->file,
                    'line' => $error->line,
                    'stack' => $error->stack,
                    'context' => $error->context,
                ]);

                if ($meta) {
                    $details['meta'] = $meta;
                }
            }

            $result->errors[] = $details;
        }
    }

    /**
     * @param $result
     * @throws HTTPException
     */
    protected function formatSuccess($result)
    {
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
    }

}
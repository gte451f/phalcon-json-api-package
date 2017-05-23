<?php

namespace PhalconRest\Result\Adapters\ActiveModel;

use PhalconRest\Exception\HTTPException;

class Result extends \PhalconRest\Result\Result
{

    /**
     * will convert the intermediate data result data into ActiveModel compatible result object
     *
     * @return \stdClass
     * @throws HTTPException
     */
    protected function formatJSON()
    {
        $result = new \stdClass;

        if ($this->outputMode == self::MODE_ERROR) {
            $this->formatFailure($result);
        } else {
            $this->formatSuccess($result);
        }

        // only include if it has valid data
        if ($this->meta) {
            $result->meta = $this->meta;
        }

        return $result;
    }

    protected function formatSuccess($result)
    {
        switch ($this->outputMode) {
            case self::MODE_SINGLE:
                if (count($this->data)) {
                    $data = current($this->data);
                    $type = $data->getType();
                    $result->$type = $data;
                }
                break;

            case self::MODE_MULTIPLE:
                // this is used to ensure there is at least a blank array when requesting multiple records
                if ($this->type !== false) {
                    $result->{$this->type} = [];
                }

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
        foreach ($this->included as $type => $data) {
            if (!isset($result->$type)) {
                $result->$type = array_values($data);
            } else {
                // not really sure how this would ever be triggered
                $result->$type += array_values($data);
            }
        }

        // include plain non-namespaced data
        foreach ($this->plain as $key => $value) {
            $result->$key = $value;
        }
    }

    protected function formatFailure($result)
    {
        $appConfig = $this->di->get('config')['application'];
        $inflector = $this->di->get('inflector');

        $result->errors = [];
        foreach ($this->errors as $error) {
            $errorBlock = [];
            if ($error->validationList) {
                foreach ($error->validationList as $validation) {
                    $field = $inflector->normalize($validation->getField(), $appConfig['propertyFormatTo']);
                    if (!isset($errorBlock[$field])) {
                        $errorBlock[$field] = [];
                    }
                    $errorBlock[$field][] = $validation->getMessage();
                }
            }

            $details = [
                'title' => $error->title,
                'code' => $error->code,
                'details' => $error->more
            ];

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

            $result->errors[] = $errorBlock + ['additional_info' => $details];
        }
    }

}
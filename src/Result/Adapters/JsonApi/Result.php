<?php
namespace PhalconRest\Result\Adapters\JsonApi;

use Phalcon\Mvc\Model\Message;
use PhalconRest\Exception\HTTPException;

class Result extends \PhalconRest\Result\Result
{

    protected function formatJSON()
    {
        $result = new \stdClass();

        if ($this->outputMode == self::MODE_ERROR) {
            $this->formatFailure($result);
        } else {
            $this->formatSuccess($result);
        }

        // include valid, plain non-namespaced data
        foreach (['jsonapi', 'links'] as $key) {
            if (isset($this->plain[$key])) {
                $result->$key = $this->plain[$key];
            }
        }
        //TODO: better handling of links (self, related, pagination) http://jsonapi.org/format/#document-links

        if ($this->meta) {
            $result->meta = $this->meta;
        }

        return $result;
    }

    protected function formatSuccess($result)
    {
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

        // process included records if there's valid entries only
        if ($this->data && $this->included) {
            $result->included = $this->included;
        }
    }

    protected function formatFailure($result)
    {
        $appConfig = $this->di->get('config')['application'];
        $inflector = $this->di->get('inflector');

        $result->errors = [];

        //for each errorStore, build one or more error objects
        foreach ($this->errors as $error) {

            //if this error includes a list of validation issues, map them into error objects and concat the result
            if ($error->validationList) {
                $validationErrors = array_map(function (Message $validation) use ($error, $appConfig, $inflector) {
                    $field = $inflector->normalize($validation->getField(), $appConfig['propertyFormatTo']);
                    $details = [
                        'code' => $error->code,
                        'title' => $error->title,
                        'detail' => $validation->getMessage(),
                        'source' => ['pointer' => "/data/attributes/$field"],
                        'meta' => ['field' => $field]
                    ];
                    if ($appConfig['debugApp'] && $error->dev) {
                        $details['meta']['developer_message'] = $error->dev;
                    }
                    return $details;
                }, $error->validationList);

                $result->errors = array_merge($result->errors, $validationErrors);

            //however, if it's a plain error, concat an error object with some additional trace information
            } else {
                $details = [
                    'title' => $error->title,
                    'code' => $error->code,
                    'detail' => $error->more,
                ];

                //it doesn't make much sense to add stacktrace info to validation errors, right?
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
    }

}
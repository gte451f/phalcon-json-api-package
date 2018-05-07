<?php

namespace PhalconRest\Exception;

use Phalcon\DI;

/**
 * class to standardize what properties the API stores in each error
 *
 * @author jjenkins
 *
 */
class ErrorStore
{

    /**
     * the basic error message
     *
     * @var string
     */
    public $title = null;

    /**
     * a numerical error code used to track down the error in source code
     * ie. 2837493479739457
     *
     * @var string
     */
    public $code;

    /**
     * additional details over and above the error title
     *
     * @var string
     */
    public $more = null;

    /**
     * internal reporting information when the api is set to debug mode
     * otherwise this information is not reported to the client
     *
     * @var string
     */
    public $dev;

    /**
     * a list of field validation objects
     * this is only used for ValiatinExceptions
     *
     * @var \Phalcon\Mvc\Model\Message[]
     */
    public $validationList = [];

    /**
     * http response code
     *
     * @var int
     */
    public $errorCode;


    /**
     * store the full error stack the error generated
     * @var
     */
    public $stack;

    // not sure what this is
    public $context;
    // store where the error occurred
    public $line;
    public $file;


    /**
     * construct with supplied standard array
     * break list down into smaller properties
     *
     * @param array $errorList
     */
    public function __construct($errorList)
    {
        $this->line = $errorList['line'] ?? '';
        $this->file = $errorList['file'] ?? '';
        $this->stack = $errorList['stack'] ?? '';
        $this->context = $errorList['context'] ?? '';
        $this->more = $errorList['more'] ?? '';
        $this->title = $errorList['title'] ?? 'No Title Supplied';
        $this->code = $errorList['code'] ?? 'XX';
        $this->dev = $errorList['dev'] ?? DI::getDefault()->getMessageBag()->getString();
    }
}
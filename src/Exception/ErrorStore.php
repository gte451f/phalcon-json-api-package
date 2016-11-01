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
    public $title = '';

    /**
     * a numerical error code used to track down the error in source code
     * ie. 2837493479739457
     *
     * @var string
     */
    public $code;

    /**
     * additional details over and above the error title
     * shows up as detail in json response
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
     * a list of phalcon validation objects
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
     * construct with supplied standard array
     * break list down into smaller properites
     *
     * @param array $errorList
     */
    public function __construct($errorList)
    {
        $this->code = $errorList['code'] ?? 'XX';
        $this->more = $errorList['more'] ?? '';
        $this->dev = $errorList['dev'] ?? DI::getDefault()->getMessageBag()->getString();
    }
}
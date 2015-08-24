<?php
namespace PhalconRest\Util;

use Phalcon\Mvc\Model\Message as Message;

/**
 * where caught Validation Exceptions go to die
 *
 *
 * @author jjenkins
 *        
 */
class ValidationException extends \Exception
{

    /**
     * store a copy of the DI
     */
    private $di;

    /**
     * Important
     * * ValidationException will accept a list of validation objects or a simple key->value list in the 3rd param of
     *
     * @param string $title
     *            the basic error message
     * @param array $errorList
     *            key=>value pairs for properites of ErrorStore
     * @param array $validationList
     *            list of phalcon validation objects or key=>value pairs to be converted into validation objects
     */
    public function __construct($title, $errorList, $validationList)
    {
        // store general error data
        $this->errorStore = new \PhalconRest\Util\ErrorStore($errorList);
        $this->errorStore->title = $title;
        
        $mergedValidations = [];
        foreach ($validationList as $key => $validation) {
            // process simple key pair
            if (is_string($validation)) {
                $mergedValidations[] = new Message($validation, $key, 'InvalidValue');
            } else {
                // assume a validation object
                $mergedValidations[] = $validation;
            }
        }
        $this->errorStore->validationList = $mergedValidations;
        
        $this->di = \Phalcon\DI::getDefault();
    }

    /**
     * set a standard HTTP response
     *
     * @return void|boolean
     */
    public function send()
    {
        $output = new \PhalconRest\API\Output();
        $output->setStatusCode('422', 'Unprocessable Entity');
        $output->sendError($this->errorStore);
        return true;
    }
}
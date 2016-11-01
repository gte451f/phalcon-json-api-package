<?php
namespace PhalconRest\Exception;

use Phalcon\Mvc\Model\Message as Message;

/**
 * where caught Validation Exceptions go to die
 * contains helpful functions for dealing with a single Validation exception
 * error kept in $this->errorStore
 * this version also populates a list of one or more validation messages
 *
 * @author jjenkins
 */
class ValidationException extends HTTPException
{

    /**
     * Important: ValidationException will accept a list of validation objects or a simple key=>value list in the 3rd param
     *
     * @param string     $title          the basic error message
     * @param array      $errorList      key=>value pairs for properties of ErrorStore
     * @param array      $validationList list of phalcon validation objects or key=>value pairs to be converted into validation objects
     * @param \Throwable $previous       previous exception, if any
     */
    public function __construct($title, $errorList, $validationList, \Throwable $previous = null)
    {
        parent::__construct($title, 422, $errorList, $previous);

        $mergedValidations = [];
        foreach ($validationList as $key => $validation) {
            // process simple key pair or assume a validation object
            $mergedValidations[] = is_string($validation)? new Message($validation, $key, 'InvalidValue') : $validation;
        }

        $this->errorStore->validationList = $mergedValidations;
    }
}
<?php
namespace PhalconRest\Util;

/**
 * Where caught Database Exceptions go to die, based on HTTP Exceptions
 * Retrieves additional information from the database error in case it's preceded by a PDOException.
 * This class deals only with MySQL error codes.
 *
 * @author igorsantos07
 */
class DatabaseException extends HTTPException
{

    const STATE_INTEGRITY_CONSTRAINT_VIOLATION = 23000;

    const CODE_DUPLICATE_UNIQUE_KEY = 1062;

    protected $details = [];

    public function __construct($title, $code, array $errorList, $previous)
    {
        if ($previous instanceof \PDOException) {
            //gets additional information about the database error
            $details = [
                'state' => $previous->errorInfo[0],
                'code'  => $previous->errorInfo[1],
                'msg'   => $previous->errorInfo[2],
            ];

            //retrieves useful information from the error message
            switch ($details['state']) {
                case self::STATE_INTEGRITY_CONSTRAINT_VIOLATION:
                    switch ($details['code']) {
                        case self::CODE_DUPLICATE_UNIQUE_KEY:
                            if (preg_match('|entry \'([\w\d-]*)\' for key \'([\w\d_]*)\'|', $details['msg'], $pieces)) {
                                $details['entry'] = explode('-', $pieces[1]);
                                $details['key']   = $pieces[2];
                            }
                            break;
                    }
                    break;
            }

            if (isset($errorList['dev'])) {
                $errorList['dev'] = array_merge($details, ['more' => $errorList['dev']]);
            } else {
                $errorList['dev'] = $details;
            }
            $this->details = $details;
        }

        parent::__construct($title, $code, $errorList, $previous);
    }

    public function getDetails() { return $this->details; }

}
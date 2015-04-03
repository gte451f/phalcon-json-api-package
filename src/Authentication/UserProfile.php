<?php
namespace PhalconRest\Authentication;

use Phalcon\DI\Injectable;

/**
 * provide a standard user object fed into the authenticator
 * 
 * @author jjenkins
 *        
 */
class UserProfile
{

    public $username;

    public $email;
}
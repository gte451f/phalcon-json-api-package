<?php
namespace PhalconRest\Authentication;

/**
 * use this as the contract all subsequent adapters should implement
 * deals with common functions the authentication system will used in the course of working with a user
 *
 * @author jjenkins
 *        
 */
interface AuthenticatorInterface
{
    
    // persiste to cache or session?
    
    /**
     * is the current user logged in?
     *
     * @return boolean
     */
    function isLoggedIn();

    /**
     * retun a logged in user object
     */
    function getLoggedInUser();

    /**
     * log a user out
     */
    function logUserOut();

    /**
     * log a user into the system
     */
    function logUserIn($profile);
}
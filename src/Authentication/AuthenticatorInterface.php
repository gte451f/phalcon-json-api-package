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
    // persist to cache or session?
    
    /**
     * is the current user logged in?
     *
     * @return boolean
     */
    function isLoggedIn($token);

    /**
     * log a user out
     */
    function logUserOut($token);

    /**
     * log a user into the system
     */
    function authenticate($userName, $password);

    /*
     * pull the current user profile from memory
     */
    function getProfile();
}
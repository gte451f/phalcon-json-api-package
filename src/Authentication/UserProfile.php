<?php
namespace PhalconRest\Authentication;

use Phalcon\DI\Injectable;

/**
 * provide a standard user object fed into the authenticator
 * provides several functions that are called post authentication
 * thus providing applications with a way to inject their own behavior
 *
 * @author jjenkins
 *        
 */
class UserProfile
{

    public $userName;

    public $email;

    public $token;

    public $expiresOn;

    /**
     * create a fresh token each time a new user profile is created
     */
    function __construct()
    {}

    /**
     * issue a generic token
     * replace with your own logic
     *
     * @return multitype:boolean NULL
     */
    public function generateToken()
    {
        return \Phalcon\Text::random(\Phalcon\Text::RANDOM_ALNUM, 64);
    }

    /**
     * issue an expiration datetime stamp
     * replace with your own logic
     */
    public function generateExpiration()
    {
        $date = new \DateTime();
        $date->add(new \DateInterval('PT1H'));
        return $date->format('Y-m-d H:i:s');
    }

    /**
     * load a full userProfile object based on a provided token
     *
     * @param array $search
     *            $key=>$value pairs
     * @return boolean Was profile loaded?
     */
    public function loadProfile($search)
    {
        // replace me with application specific logic
        return true;
    }

    /**
     * persist the profile to local storage
     * ie.
     * session, database, memcache etc
     *
     * @return boolean
     */
    public function save()
    {
        // replace with application specific logic
        return true;
    }
}
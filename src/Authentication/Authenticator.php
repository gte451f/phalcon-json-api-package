<?php
namespace PhalconRest\Authentication;

use Phalcon\DI\Injectable;

/**
 *
 * This is one example class that implements an interface used to deal with common tasks around
 * user authentication such as log in and log out
 *
 * work is split between a userProfile object and a adapter to authenticate
 * the adapter's role is to compare a set of credentials against an access list (DB, A/D)
 * the profile's role is to load a fully populated user account
 *
 * a few options are specified in the authentication class
 * see inline comments
 *
 * This class makes a few assumptions:
 * adapters rely on a a pair of fields to identify a user
 * - userName
 * - token
 *
 *
 * @author jjenkins
 *
 */
class Authenticator extends Injectable implements AuthenticatorInterface
{

    /**
     * the user profile
     *
     * @var \PhalconRest\Authentication\UserProfile
     */
    private $profile;

    private $adapter;

    /**
     * @var string what is the name of the attribute that stores a users token once they have been authenticated
     */
    public $tokenFieldName = 'token';

    /**
     * @var string what is the name of the attribute of the user identifier?
     */
    public $userNameFieldName = 'user_name';

    /**
     * is the user authenticated?
     *
     * @var boolean
     */
    public $authenticated = false;

    /**
     * inject adapter here
     */
    function __construct(AdapterInterface $adapter, \PhalconRest\Authentication\UserProfile $profile)
    {
        $this->adapter = $adapter;
        $this->profile = $profile;
    }

    /**
     *
     * (non-PHPdoc)
     *
     * @see AuthenticatorInterface::isLoggedIn()
     *
     */
    public function isLoggedIn($token)
    {
        // ignore blank tokens
        if (strlen($token) == 0) {
            false;
        }

        $this->authenticated = $this->profile->loadProfile("$this->tokenFieldName = '$token'");
        return $this->authenticated;
    }

    /**
     * (non-PHPdoc)
     *
     * @see AuthenticatorInterface::logUserOut()
     *
     */
    public function logUserOut($token)
    {
        // ignore blank tokens
        if (strlen($token) == 0) {
            false;
        }

        $result = $this->isLoggedIn($token);
        $this->beforeLogout($token);
        if ($result) {
            $result = $this->profile->resetToken(true);
        }
        $this->afterLogout($token, $result);
        return $result;
    }

    /**
     * run a set of credentials against the adapters internal authenticate function
     * will retain a copy of the adapter provided profile
     *
     * @param string $userName
     * @param string $password
     * @return boolean
     */
    public function authenticate($userName, $password)
    {
        $this->beforeLogin($userName, $password);
        $result = $this->adapter->authenticate($userName, $password);
        if ($result) {
            $this->profile->loadProfile("$this->userNameFieldName = '$userName'");
            $this->profile->resetToken();
        }
        $this->afterLogin($userName, $password, $result);
        return $result;
    }

    /**
     * will return a valid userProfile object for a pre-loaded profile
     * (non-PHPdoc)
     *
     * @see \PhalconRest\Authentication\AuthenticatorInterface::getProfile()
     */
    function getProfile()
    {
        return $this->profile;
    }

    /**
     * will return a valid userProfile object for a pre-loaded profile
     * (non-PHPdoc)
     *
     * @see \PhalconRest\Authentication\AuthenticatorInterface::getProfile()
     */
    function setProfile(\PhalconRest\Authentication\UserProfile $profile)
    {
        return $this->profile = $profile;
    }

    /**
     * hook to call before a login attempt
     *
     * @param string $userName
     * @param string $password
     */
    public function beforeLogin($userName, $password)
    {
    }

    /**
     * hook to call after a login attempt
     *
     * @param string $userName
     * @param string $password
     */
    public function afterLogin($userName, $password, $result)
    {
    }

    /**
     * hook to call before a logout attempt
     *
     * @param string $userName
     * @param string $password
     */
    public function beforeLogout($token)
    {
    }

    /**
     * hook to call after a logout attempt
     *
     * @param string $userName
     * @param string $password
     */
    public function afterLogout($token, $result)
    {
    }
}
<?php
namespace PhalconRest\Authentication;

use Phalcon\DI\Injectable;

/**
 * Use one interface to perform work split between a userProfile object and a adapter to authenticate
 *
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
     * @var \stdClass
     */
    private $profile;

    private $adapter;

    public $tokenFieldName = 'token';

    public $userNameFieldName = 'user_name';

    /**
     * is the user authenticated?
     *
     * @var unknown
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
     * will return a valid userProfile object for a pre-loaded profile or for a supplied userName
     * (non-PHPdoc)
     *
     * @see \PhalconRest\Authentication\AuthenticatorInterface::getProfile()
     */
    function getProfile($userName = false)
    {
        return $this->profile;
    }

    /**
     * hook to call before a login attempt
     *
     * @param string $userName            
     * @param string $password            
     */
    public function beforeLogin($userName, $password)
    {}

    /**
     * hook to call after a login attempt
     *
     * @param string $userName            
     * @param string $password            
     */
    public function afterLogin($userName, $password, $result)
    {}

    /**
     * hook to call before a logout attempt
     *
     * @param string $userName            
     * @param string $password            
     */
    public function beforeLogout($token)
    {}

    /**
     * hook to call after a logout attempt
     *
     * @param string $userName            
     * @param string $password            
     */
    public function afterLogout($token, $result)
    {}
}
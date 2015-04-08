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
final class Authenticator extends Injectable implements AuthenticatorInterface
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
        $result = $this->isLoggedIn($token);
        if ($result) {
            $result = $this->profile->resetToken(true);
        }
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
        $result = $this->adapter->authenticate($userName, $password);
        if ($result) {
            $this->profile->loadProfile("$this->userNameFieldName = '$userName'");
            $this->profile->resetToken();
        }
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
}
<?php
namespace PhalconRest\Authentication;

use Phalcon\DI\Injectable;

/**
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
    private $profile = false;

    private $adapter;

    /**
     * inject adapter here
     */
    function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    /**
     * (non-PHPdoc)
     *
     * @see AuthenticatorInterface::isLoggedIn()
     *
     */
    public function isLoggedIn()
    {
        return true;
    }

    /**
     * (non-PHPdoc)
     *
     * @see AuthenticatorInterface::logUserOut()
     *
     */
    public function logUserOut()
    {
        return true;
    }

    /**
     * (non-PHPdoc)
     *
     * @see AuthenticatorInterface::logUserIn()
     *
     */
    public function logUserIn($user)
    {
        return true;
    }

    public function authenticate($userName, $password)
    {
        $result = $this->adapter->authenticate($userName, $password);
        
        if ($result != false) {
            $this->setProfile($this->adapter->getProfile($userName, $password));
            return true;
        } else {
            return false;
        }
    }

    /**
     * (non-PHPdoc)
     *
     * @see \PhalconRest\Authentication\AuthenticatorInterface::getProfile()
     */
    function getProfile()
    {
        return $this->profile;
    }

    /**
     * (non-PHPdoc)
     *
     * @see \PhalconRest\Authentication\AuthenticatorInterface::setProfile()
     */
    function setProfile(\PhalconRest\Authentication\UserProfile $profile)
    {
        $this->profile = $profile;
    }
}
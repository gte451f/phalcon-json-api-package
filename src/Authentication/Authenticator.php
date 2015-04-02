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
    private $profile;

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
     * @see AuthenticatorInterface::getLoggedInUser()
     *
     */
    public function getLoggedInUser()
    {
        $user = $this->adapter->getUser('test');
        return $user;
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
        return $result;
    }
}
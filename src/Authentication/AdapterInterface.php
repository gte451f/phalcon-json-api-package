<?php
namespace PhalconRest\Authentication;

/**
 * what should the adapter be forced to provide?
 *
 * @author jjenkins
 *        
 */
interface AdapterInterface
{

    /**
     * each adapter must provide a way to authenticate a user
     *
     * @param string $userName            
     * @param string $password            
     * @return boolean
     */
    function authenticate($userName, $password);
}

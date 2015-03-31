<?php
namespace PhalconRest\Authentication;

use Phalcon\DI\Injectable;

/**
 *
 * @author jjenkins
 *        
 */
final class Dummy extends Injectable implements AdapterInterface
{

    /**
     * the user profile
     *
     * @var \stdClass
     */
    private $profile;
}
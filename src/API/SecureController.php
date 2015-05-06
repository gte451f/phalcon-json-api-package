<?php
namespace PhalconRest\API;

use \PhalconRest\Util\HTTPException;

/**
 * Same base controller but checks for a valid token if security is enabled
 * otherwise it proceeds to the baseController
 */
class SecureController extends BaseController
{

    public function __construct($parseQueryString = true)
    {
        $config = $this->getDI()->get('config');
        
        if(!isset($config['security'])){
            throw new HTTPException("Configuration lacks a security value.  Please specify this value in your config array.", 500, array(
                'dev' => "Set security value in config array",
                'internalCode' => '342534565408971'
            ));
        }
        
        switch ($config['security']) {
            case true:
                $token = $this->request->getHeader("X_AUTHORIZATION");
                $token = trim(str_ireplace("Token: ", '', $token));
                if (strlen($token) < 30) {
                    throw new HTTPException("Bad token supplied", 401, array(
                        'internalCode' => '0273497957'
                    ));
                }
                
                // TODO Check for a valid session
                // Check if the variable is defined
                if ($this->auth->isLoggedIn($token)) {
                    parent::__construct($parseQueryString);
                } else {
                    throw new HTTPException("Unauthorized, please authenticate first.", 401, array(
                        'dev' => "Must be authenticated to access.",
                        'internalCode' => '30945680384502037'
                    ));
                }
                break;
            
            case false:
                if ($this->auth->isLoggedIn('HACKYHACKERSON')) {
                    parent::__construct($parseQueryString);
                } else {
                    throw new HTTPException("Security False is not loading a valid user.", 401, array(
                        'dev' => "The authenticator isn't loading a valid user.",
                        'internalCode' => '23749873490704'
                    ));
                }
                break;
            
            default:
                throw new HTTPException("Bad security value supplied", 500, array(
                    'internalCode' => '280273409724075'
                ));
                break;
        }
    }
}
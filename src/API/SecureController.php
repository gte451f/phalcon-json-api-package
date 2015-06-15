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
        
        switch ($config['security']) {
            case true:
                
                $headerToken = $this->request->getHeader("X_AUTHORIZATION");
                $queryParamToken = $this->getDI()
                    ->get('request')
                    ->getQuery("token");
                
                // try to read in from header first, otherwise attempt to read in from query param
                if ($headerToken !== "") {
                    $token = $headerToken;
                } elseif (! is_null($queryParamToken)) {
                    $token = $queryParamToken;
                } else {
                    $token = "";
                }
                
                $token = trim(str_ireplace("Token: ", '', $token));
                if (strlen($token) < 30) {
                    throw new HTTPException("Bad token supplied", 401, array(
                        'internalCode' => '0273497957'
                    ));
                }
                
                // check for a valid session
                if ($this->auth->isLoggedIn($token)) {
                    $securityService = $this->getDI()->get('securityService');
                    
                    $this->securityCheck($securityService);                    
                    parent::__construct($parseQueryString);
                } else {
                    throw new HTTPException("Unauthorized, please authenticate first.", 401, array(
                        'dev' => "Must be authenticated to access.",
                        'internalCode' => '30945680384502037'
                    ));
                }
                break;
            
            case false:
                // if security if off, then create a fake user profile
                // todo figure out a way to do this w/o this assumption
                // notice the specific requirement to a client application
                if ($this->auth->isLoggedIn('HACKYHACKERSON')) {
                    $securityService = $this->getDI()->get('securityService');
                    
                    $this->securityCheck($securityService);
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
    
    protected function securityCheck($securityService)
    {
        return true;
    }
}
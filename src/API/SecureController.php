<?php
namespace PhalconRest\API;

use \PhalconRest\Util\HTTPException;

/**
 * Same base controller but checks for a valid session before proceeding
 */
class SecureController extends BaseController
{

    public function __construct($parseQueryString = true)
    {
        $token = $this->request->getHeader("X_AUTHORIZATION");
        $token = str_ireplace("Token: ", '', $token);
        $token = trim($token);
        // $this->auth = $this->getDI()->get('auth');
        // parent::__construct($parseQueryString);
        
        if (strlen($token) < 30) {
            throw new HTTPException("Bad token supplied", 401, array(
                'internalCode' => '0273497957'
            ));
        }
        
        // $token = 'f8o92fTk58c22sD2280bfByn0875TN0fcUq8MKGfhrScd2by6JcceuF783ttFadf';
        // TODO Check for a valid session
        // Check if the variable is defined
        if ($this->auth->isLoggedIn($token)) {
            parent::__construct($parseQueryString);
            // if($profile['token'])
        } else {
            // TODO throw error here
            throw new HTTPException("Unauthorized, please authenticate first.", 401, array(
                'dev' => "Must be authenticated to access.",
                'internalCode' => '30945680384502037'
            ));
        }
    }
}

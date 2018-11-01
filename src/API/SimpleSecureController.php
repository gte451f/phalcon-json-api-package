<?php

namespace PhalconRest\API;

use \PhalconRest\Exception\HTTPException;

/**
 * Same base controller but checks for a valid token if security is enabled
 * otherwise it proceeds to the baseController
 *
 * IMPORTANT - This controller DOES NOT make assumptions about any security service in your client app
 * Rather, this controller is intended to work with the Rules system instead
 */
class SimpleSecureController extends BaseController
{

    /**
     * this function will gather an auth token and proceed or fail
     *
     * @throws HTTPException
     */
    public function onConstruct()
    {
        //early return on OPTIONS calls in dev, so they follow the correct spec and don't die for missing credentials
        if ($this->request->getMethod() == 'OPTIONS' && APPLICATION_ENV != 'production') {
            return;
        }

        $config = $this->getDI()->get('config');
        $auth = $this->getDI()->get('auth');

        switch ($config['security']) {
            case true:
                $token = $this->getAuthToken();

                // check for a valid session
                if ($auth->isLoggedIn($token)) {
                    // continue
                } else {
                    throw new HTTPException('Unauthorized, please authenticate first.', 401, [
                        'dev' => 'Must be authenticated to access.',
                        'code' => '30945680384502037'
                    ]);
                }
                break;

            case false:
                // if security is off, then create a fake user profile to trick the api
                // todo figure out a way to do this w/o this assumption
                // notice the specific requirement to a client application
                if ($auth->isLoggedIn('HACKYHACKERSON')) {
                    // continue
                } else {
                    throw new HTTPException('Security False is not loading a valid user.', 401, [
                        'dev' => 'The authenticator isn\'t loading a valid user.',
                        'code' => '23749873490704'
                    ]);
                }
                break;

            default:
                throw new HTTPException('Bad security value supplied', 500, ['code' => '280273409724075']);
                break;
        }

        // continue after security is worked out
        parent::onConstruct();
    }

    /**
     * Tries to get the Authorization Token in this order:
     * 1. Header: X-Authorization
     * 2. GET "token"
     * 3. POST "token"
     * @throws HTTPException 401 If token is not found
     * @return string
     */
    protected function getAuthToken()
    {
        $token = $this->request->getHeader('X_AUTHORIZATION');
        if (!$token) {
            $request = $this->getDI()->get('request');
            $token = $request->getQuery('token') ?: $request->getPost('token');
        }

        $token = trim(str_ireplace('Token:', '', $token));
        if (strlen($token) < 30) {
            throw new HTTPException('Unauthorized access blocked', 401, [
                'dev' => 'Supplied Token: ' . $token,
                'code' => '98914681681864674'
            ]);
        }
        return $token;
    }
}

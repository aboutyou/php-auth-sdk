<?php

namespace AuthSDK;


use Collins\Sign\JWS\Payload\AuthPayload;
use Collins\Sign\JWS\SignService;
use Collins\Sign\JWS\SignServiceConfig;

class AuthSDK
{

    //------------------------------properties from constructor parameters----------------------------------------------

    private $_clientId;
    private $_clientToken;
    private $_clientSecret;
    public $_loginUrl; //TODO just for examples /apps/list..
    private $_resourceUrl;
    private $_redirectUri;
    private $_scope;
    /**
     * @var bool
     */
    private $_popup;

    //------------------------------properties from server, also stored in session--------------------------------------

    //@var string
    private $_grantCode;
    //currently array [access_token,refresh_token,token_type,scope]
    private $_accessToken;


    private $_userAuthResult;


    /**
     * @var StorageInterface
     */
    protected $_storageStrategy;


    /**
     * @param array $params
     * @param StorageInterface $storageStrategy
     */
    public function __construct(array $params, StorageInterface $storageStrategy)
    {
        $this->checkParams($params);
        $this->_storageStrategy = $storageStrategy;
        $this->_clientId = $params['clientId'];
        $this->_clientToken = $params['clientToken'];
        $this->_clientSecret = $params['clientSecret'];
        $this->_loginUrl = $params['loginUrl'];
        $this->_resourceUrl = $params['resourceUrl'];
        $this->_redirectUri = $params['redirectUri'];
        $this->_scope = $params['scope'];
        $this->_popup = isset($params['popup']) ? $params['popup'] : false;

        $this->_storageStrategy->init($this->_clientId);

    }

    protected function checkParams($params)
    {
        $requiredParams = array('clientId', 'clientToken', 'loginUrl', 'resourceUrl', 'redirectUri', 'scope');
        $missingParams = array();
        foreach ($requiredParams as $testParam) {
            if (!isset($params[$testParam])) {
                $missingParams[] = $testParam;
            }
        }
        if ($missingParams) {
            throw new \Exception('Missing required params: ' . implode(', ', $missingParams));
        }
    }

    /**
     * @return string|null
     */
    protected function getGrantCode()
    {
        if (isset($this->_grantCode)) {
            return $this->_grantCode;
        }
        if ($persistentProperty = $this->_storageStrategy->getPersistentData('grantCode')) {
            $this->_grantCode = $persistentProperty;
            return $persistentProperty;
        }
        return null;
    }

    /**
     * You need to set the code you get after the redirect to your redirectUri (TODO maybe we will have token auth type to!)
     * @param string $grantCode
     */
    protected function setGrantCode($grantCode)
    {
        $this->_grantCode = $grantCode;
        $this->_storageStrategy->setPersistentData('grantCode', $grantCode);
    }

    public function getState($key)
    {
        $states = $this->_storageStrategy->getPersistentData('states');
        if ($states && array_key_exists($key, $states)) {
            return $states[$key];
        }
        return null;
    }

    public function setState($key, $value)
    {
        $states = $this->_storageStrategy->getPersistentData('states');
        if (empty($states)) {
            $this->_storageStrategy->setPersistentData('states', array());
        }
        $states[$key] = $value;
        $this->_storageStrategy->setPersistentData('states', $states);
    }


    protected function buildStateUrlValue()
    {
        return base64_encode(json_encode($this->_storageStrategy->getPersistentData('states')));
    }

    protected function parseStateUrlValue($value)
    {
        return (array)json_decode(base64_decode($value));
    }


    /**
     * Use this function on your redirect page, to parse the result into the sdk.
     *
     * Returns 'success'|'cancel'|false
     *
     * @return string|false
     */
    public function parseRedirectResponse()
    {
        //the sdk requires state was given for auth request..
        if ($this->getState('csrf')) {
            $states = $this->parseStateUrlValue($_GET['state']);
            if (isset($states['csrf']) && $this->getState('csrf') === $states['csrf']) {

                $this->_storageStrategy->setPersistentData('states', $states);

                //version a) if set directly by authserver
                if (isset($_GET['code'])) {
                    $this->setGrantCode($_GET['code']);
                    $this->setAccessToken(null);
                    return 'success';
                }

                //varsion b TODO unused) if "wrapped" by checkout
                if (isset($_GET['result'])) {

                    if ($_GET['result'] == 'success') {
                        $this->setGrantCode($_GET['code']);
                        $this->setAccessToken(null);
                        return $_GET['result'];
                    } else {
                        if ($_GET['result'] == 'cancel') {
                            return $_GET['result'];
                        } else {
                            return false;
                        }
                    }

                }
            }
        }
        return false;

    }

    /**
     * AccessToken-Data [access_token, token_type ...]
     *
     * @return array|null
     */
    protected function getAccessToken()
    {
        if (isset($this->_accessToken)) {
            //TODO ? if($this->_accessToken->expiresDate > time()) return null;
            return $this->_accessToken;
        }
        if ($persistentProperty = $this->_storageStrategy->getPersistentData('accessToken')) {
            $this->_accessToken = $persistentProperty;
            //TODO ? if($this->_accessToken->expiresDate > time()) return null;
            return $persistentProperty;
        }
        return null;
    }

    /**
     * @param array $accessToken
     */
    protected function setAccessToken($accessToken)
    {
        $this->_accessToken = $accessToken;
        $this->_storageStrategy->setPersistentData('accessToken', $accessToken);
    }

    /**
     * Return access_token from storage or fetch from auth-server.
     *
     * Most of the time its easier to use @see api()
     *
     * @return AuthResult
     */
    public function getToken()
    {

        if ($accessToken = $this->getAccessToken()) {
            return new AuthResult($accessToken);
        } else {
            if ($this->getGrantCode()) {

                $curl = $this->createCurl();
                $curl->setBasicAuthentication($this->_clientId, $this->_clientToken);
                $params = [
                    'client_id' => $this->_clientId,
                    'grant_type' => 'authorization_code',
                    'redirect_uri' => $this->_redirectUri,
                    'code' => $this->getGrantCode()
                ];

                $url = rtrim($this->_resourceUrl, '/') . '/oauth/token?' . http_build_query($params);
                $curl->get($url);

                if ($curl->error) {
                    return new AuthResult($curl, array('Could not fetch accessToken'));
                } else {
                    $this->setAccessToken(json_decode($curl->response));
                    $this->setGrantCode(null);
                    return new AuthResult($curl);
                }

            } else {
                return new AuthResult(null, array('not logged in'));
            }
        }

    }

    /**
     * @return string
     */
    public function getLoginUrl()
    {
        $this->setState('csrf', md5(uniqid($this->_clientToken, true)));

        $signService = new SignService(
            new SignServiceConfig(
                $this->_clientId,
                $this->_clientSecret,
                'auth_sdk_'.$this->_clientId
            )
        );
        $payload = $signService->sign(
            new AuthPayload(
                $this->_redirectUri,
                $this->_scope,
                $this->_popup,
                $this->buildStateUrlValue()
            )
        );
        return rtrim($this->_loginUrl, '/') . '?app_id='.$this->_clientId.'&asr='.$payload;
    }

    public function logout()
    {

        //TODO unsetting this should be unneeded if server removes token on logout..
        $this->_grantCode = null;
        $this->_accessToken = null;
        $this->_state = null;
        $this->_userAuthResult = null;
        $this->_storageStrategy->clearAllPersistentData();

        header(
            "Location: " . rtrim($this->_loginUrl, '/') . '/user/logout?' . http_build_query(
                array('redirectUri' => $this->_redirectUri)
            )
        );
        die();

    }

    /**
     * Tries to fetch the api resource
     * //     * If token is not provided (default) will try OAuth2 web-flow, else implicit-flow..
     * @param $resourcePath
    //	 * @param string $token
     * @param string $httpMethod
     * @param array $params
     * @param bool $lastRetry
     * @return AuthResult
     */
    public function api(
        $resourcePath, /* $token=false,*/
        $httpMethod = 'get',
        array $params = array(), /*TODO experimental: */
        $lastRetry = false
    ) {

        $curl = $this->createCurl();

//		if(!$token){ //TODO just to test token authorization (instead of grant_code)
        $tokenResult = $this->getToken();
        if ($tokenResult->hasErrors()) {
            return $tokenResult;
        }
        $curl->setHeader('Authorization', 'Bearer ' . $this->getAccessToken()->access_token);
//		}else{
//			$obj = new \stdClass();
//			$obj->access_token = $token;
//			$this->setAccessToken($obj);
//			$curl->setHeader('Authorization','Bearer '.$this->getAccessToken()->access_token);
//		}

        $url = rtrim($this->_resourceUrl, '/') . '/api' . $resourcePath . '?' . http_build_query($params);
        $curl->$httpMethod(rtrim($this->_resourceUrl, '/') . '/api' . $resourcePath, $params);

        if ($curl->http_status_code == 403 && !$lastRetry) {
            //(Most likely?) invalid token in session! Remove Token and try again.. one time
            $this->setAccessToken(null);
            $this->api($resourcePath, $httpMethod, $params, true);
        }

        if ($curl->error) {
            return new AuthResult($curl, array(
                'url' => $url,
                'error_code' => $curl->error_code,
                'error_message' => $curl->error_message,
                'http_code' => $curl->http_status_code
            ));
        } else {
            return new AuthResult($curl);
        }
    }

    /**
     * @return AuthResult
     */
    public function getUser()
    {
        if ($this->_userAuthResult) {
            return $this->_userAuthResult;
        } else {
            $result = $this->api('/me');
            if (!$result->hasErrors()) {
                $this->_userAuthResult = $result;
            }
            return $result;
        }
    }

    protected function createCurl()
    {
        $curl = new Curl();
        $curl->setOpt(CURLOPT_SSL_VERIFYPEER,0);
        $curl->setOpt(CURLOPT_SSL_VERIFYHOST,0);
        $curl->setUserAgent('AV-OAUTH-CLIENT_' . $this->_clientId);
        return $curl;
    }
}


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
        $this->_redirectUri = $params['redirectUri'];

        $this->_loginUrl = isset($params['loginUrl']) ? $params['loginUrl'] : 'https://checkout.mary-paul.de';
        $this->_resourceUrl = isset($params['resourceUrl']) ? $params['resourceUrl'] : 'https://oauth.collins.kg/oauth';
        $this->_scope = isset($params['scope']) ? $params['scope'] : 'firstname';
        $this->_popup = isset($params['popup']) ? $params['popup'] : true;

        $this->_storageStrategy->init($this->_clientId);
    }

    protected function checkParams($params)
    {
        $requiredParams = array('clientId', 'clientToken', 'clientSecret', 'redirectUri');
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
     * Notice this method resets the csrf-token in case of success (returns true), meaning
     * calls to getLoginUrl() should only be made after this method was called.
     *
     * @return boolean
     */
    public function parseRedirectResponse()
    {
        //the sdk requires state was given for auth request..
        if (isset($_GET['state'],$_GET['code']) && $this->getState('csrf')) {
            $states = $this->parseStateUrlValue($_GET['state']);
            if (isset($states['csrf']) && $this->getState('csrf') === $states['csrf']) {

                unset($states['csrf']);
                $this->_storageStrategy->setPersistentData('states', $states);
                $this->setGrantCode($_GET['code']);
                $this->setAccessToken(null);
                return true;

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
            return $this->_accessToken;
        }
        if ($persistentProperty = $this->_storageStrategy->getPersistentData('accessToken')) {
            $this->_accessToken = $persistentProperty;
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
     * Creates a loginUrl containing all relevant information from the constructor arguments,
     * signs the request and adds a csrf-token. Please notice that the csrf-token will be regenerated if
     * parseRedirectResponse() is called AND returns true (Meaning, if you create some loginUrls before calling
     * parseRedirectResponse() and some after calling it, the ones created before will be invalid.)
     *
     * @return string
     */
    public function getLoginUrl()
    {
        if (!$this->getState('csrf')){
            $this->setState('csrf', md5(uniqid($this->_clientToken, true)));
        }

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

    /**
     * @param string $redirectUrl The url where you want to be redirect back after logout, if none the redirectUri of sdk config will be used
     */
    public function logout($redirectUrl = null)
    {
        header(
            'Location: ' . $this->getLogoutUrl($redirectUrl)
        );
        die();
    }

    /**
     * @param string $redirectUrl The url where you want to be redirect back after logout, if none the redirectUri of sdk config will be used
     */
    public function getLogoutUrl($redirectUrl = null)
    {
        if(!$redirectUrl){
            $redirectUrl = $this->_redirectUri;
        }

        return rtrim($this->_loginUrl, '/') . '/user/logout?' . http_build_query(
            array('redirectUri' => $redirectUrl)
        );
    }

    /**
     * Tries to fetch the api resource.
     * @param $resourcePath
     * @param string $httpMethod
     * @param array $params
     * @param bool $lastRetry
     * @return AuthResult
     */
    public function api(
        $resourcePath,
        $httpMethod = 'get',
        array $params = array(),
        $lastRetry = false
    ) {

        $curl = $this->createCurl();

        $tokenResult = $this->getToken();
        if ($tokenResult->hasErrors()) {
            return $tokenResult;
        }
        $curl->setHeader('Authorization', 'Bearer ' . $this->getAccessToken()->access_token);

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


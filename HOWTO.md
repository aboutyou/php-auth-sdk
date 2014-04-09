# auth-sdk

The auth-sdk is just a simple wrapper around persistent state storage and redirect response parsing for the
[OAuth2 web grant type](http://tools.ietf.org/html/rfc6749#section-4.1).

A simpler explanation is given [http://aaronparecki.com/articles/2012/07/29/1/oauth2-simplified](http://aaronparecki.com/articles/2012/07/29/1/oauth2-simplified). 
You should read it at least once, to have a basic understanding of OAuth2. 
This sdk covers the "Web Server Apps" part mentioned there.

The auth-sdk hides most of the OAuth2 stuff, so you'll basically have to do 3 things:

* Check, if you have permissions (=access token) to call an api method for the user.
	* Call sdk->getUser()
* If not, redirect the user to an external login page. [^1]
	* Redirect to sdk->getLoginUrl()
* Parse the redirect sent back to your site after the login.
	* Call sdk->parseRedirectResponse()

Your app now should have permissions to make an api call on behalf of the user.

You can use the acces token then stored in the auth-sdk for subsequent api calls until the token expires.
(Server-side or until the user logs out)

## Include with composer

(currently only code in development branch, so:)

```
    "repositories": [
        {
            "type": "git",
            "url": "git@codebasehq.com:antevorte/public-sdks-2/php-auth-sdk.git"
        },
        {
            "type": "git",
            "url": "git@codebasehq.com:antevorte/public-sdks-2/php-jws.git"
        }
    ],
    "require": {
        "collins/php-auth-sdk": "0.1.0"
    }
```

## Oauth2 web grant type usage

### Check for permissions or login:

* ./examples/parent_page.php
* Create an instance of the auth-sdk:

```
$authSDK = new AuthSDK(array(
		'clientId'=>'from_dev_center',
		'clientToken'=>'from_dev_center',
		'clientSecret' => 'from_dev_center',
		'redirectUri'=>'entered_in_dev_center',
		'loginUrl'=>'from_dev_center',
		'resourceUrl'=>'from_dev_center',
		'scope'=>'email|TODO',
		'popup'=>'true|false',
	),new StorageService());
```

* Check, if login button|redirect needed,
* Its also possible to set 'state' params (will be returned)

```
$authResult = $authSDK->getUser();
if($authResult->hasErrors()){
	//optional, add values you want to get back on your redirect endpoint
	//but do this before getLoginUrl()
	$authSDK->setState('someKey','someVal');

	$renderLoginButton( $authSDK->getLoginUrl() ); //$renderLoginButton(..) is your method.
}else{
	var_dump($authResult->getResult()->response);
}
```

### Parse the response (login redirected back to your site):

* ./examples/result_page.php
* Create an auth-sdk instance:

```
$authSDK = new AuthSDK(array( .. ) ); //see above
```

* FIRST parse the response with the auth-sdk

```
$state = $authSDK->parseRedirectResponse();
//for $state, see examples
```

* Make an api call:

```
$apiResult = $authSDK->api('/me');
if($apiResult->hasErrors()){
	var_dump($apiResult->getErrors());
}else{
	var_dump($apiResult->getResult()->response);

	//optional get additional values back
	var_dump($authSDK->getState('someKey'));
}
```

## Oauth2 token type usage

* Is not supported by the php auth-sdk.

## Examples

See the sdk-folder:  ./examples/*

### Config

* Copy `./example/common_params.php` to `./example/common_params.local.php`)
* Change the params in ./example/common_params.local.php to match your values (from dev center)
* Set the following values in the auth-sdk constructor config array on every `*_page.php` with your real credentials:
```
	* 'clientId'=>'',
	* 'clientToken'=>'',
	* 'clientSecret'=>'',
	* 'redirectUri'=>''
```
[^1]:* If the user is not logged in there, it will grant the user for its username and password and then redirect back to your site with an access token.
	* If your user however already is logged in, it will just redirect back to your site with an access token.
	* There is one more authorization flow step after those possible grants from the user and really fetching the access token, but the auth-sdk will gently hide that from you.
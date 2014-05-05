<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
require __DIR__.'/../vendor/autoload.php';

use AuthSDK\AuthSDK;
use AuthSDK\SessionStorage;
$configParams = include 'common_params.local.php';
$authSDK = new AuthSDK($configParams, new SessionStorage());
?>


<html>
<body>

<br>
<hr>
<h2>Logged in with app/clientId: <?php echo $configParams['clientId']?> ?</h2>

<?php
$authSDK->parseRedirectResponse();
$loggedInResult = $authSDK->getUser();


if ($loggedInResult->hasErrors()) {

    //Set some additional state to retrieve after login returns (see result_page.php)
    $authSDK->setState('path','my/route');

    //After setting additional states, get the loginUrl
    echo '<h2>NO!</h2><a href="' . $authSDK->getLoginUrl() . '">LOGIN</a><p>Why not? Errors received:</p>';

    var_dump($loggedInResult->getErrors());
} else {

    echo '<h2>YES!</h2>Result is:<br>';
    var_dump($loggedInResult->getResult()->response);
    ?>
    <br>
    <form method="post">
        <input type="submit" name="logout" value="logout"></input>
    </form>
    <a href="<?php echo $authSDK->getLogoutUrl('http://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">Logout
        via
        Url</a>
    <p>.. or check child-apps allowed by current user at:
        <a href="<?php echo $authSDK->_loginUrl . '/apps/list' ?>" target="_blank">My apps</a>
    </p>

<?php

};


if (isset($_POST['logout'])) {
    //try to call api endpoint, should recognize if no access token and redirect to logIn -> redirectUri
    $apiReturn = $authSDK->logout();
    //if we already have an access token, it should return the correct api answer
    echo '<p>Logout Response</p>';
    if (!$apiReturn->hasErrors()) {
//					var_dump($apiReturn->getResult());
    } else {
        var_dump($apiReturn->getErrors());
    }
}
if (isset($_POST['destroy'])) {
    session_destroy();
}

?>

<hr>
<h2>
    <form method="post">
    <span>test api call: <input type="submit" name="doit" value="/api/me"></span>
    </form>
</h2>



<?php

if (isset($_POST['doit'])) {
    //try to call api endpoint, should recognize if no access token and redirect to logIn -> redirectUri
    $apiReturn = $authSDK->api('/me');
    //if we already have an access token, it should return the correct api answer
    if (!$apiReturn->hasErrors()) {
        echo '<h2>Success:</h2>';
        var_dump($apiReturn->getResult()->response);
    } else {
        echo '<h2>Errors:</h2>';
        var_dump($apiReturn->getErrors());
    }
}

?>

<hr>
<h2>auth-sdk args:</h2>

<?php
var_dump($configParams);
?>

<hr>
<h2>session information:</h2>

<?php
@session_start();
var_dump(isset($_SESSION)?$_SESSION:'empty')
?>

<form method="post">
    <span>Clean Oauth2 session: <input type="submit" name="destroy" value="Reset"></span>
</form>

<hr>
</body>
</html>
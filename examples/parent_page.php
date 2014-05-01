<?php
error_reporting(E_ALL);
ini_set('display_errors',1);
require __DIR__.'/../vendor/autoload.php';

use AuthSDK\AuthSDK;
use AuthSDK\SessionStorage;

$authSDK = new AuthSDK(include 'common_params.local.php', new SessionStorage());
?>


<html>
<body>

<br>
<hr>
<h2>Logged in?</h2>

<?php

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

?>

<hr>
<h2>Test api call</h2>

<form method="post">
    <input type="submit" name="doit" value="/me">
</form>

<?php

if (isset($_POST['doit'])) {
    //try to call api endpoint, should recognize if no access token and redirect to logIn -> redirectUri
    $apiReturn = $authSDK->api('/me');
    //if we already have an access token, it should return the correct api answer
    if (!$apiReturn->hasErrors()) {
        echo '<h2>auth-sdk returned success</h2>';
        var_dump($apiReturn->getResult()->response);
    } else {
        echo '<h2>auth-sdk returned erros</h2>';
        var_dump($apiReturn->getErrors());
    }
}

?>

<hr>
<h2>Session information</h2>

<?php @session_start();
var_dump($_SESSION) ?>
<hr>
<br>
</body>
</html>
<br>
<hr>
<h1>RETURN URL INTERCEPTION</h1>
<h2><a href="parent_page.php">Back to parent app</a></h2>
<hr>
<h2>Test api call</h2>
<?php

//----------------------------------test ouath success url----------------------------------------//
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('xdebug.var_display_max_data', -1);

require __DIR__.'/../vendor/autoload.php';

use AuthSDK\AuthSDK;
use AuthSDK\SessionStorage;

$authSDK = new AuthSDK(include 'common_params.local.php', new SessionStorage());
if ($authSDK->parseRedirectResponse()) {

    //test retrieving additional state after redirected back to app:
    echo '<b>additional state info</b>';var_dump($authSDK->getState('path'));

    //test api-call
    $apiReturn = $authSDK->api('/me');
    if (!$apiReturn->hasErrors()) {
        echo '<h2>auth-sdk returned sucess</h2>';
        var_dump($apiReturn->getResult()->response);
    } else {
        echo '<h2>auth-sdk returned errors</h2>';
        var_dump($apiReturn->getErrors());
        var_dump($apiReturn->getResult());
    }

} else {
    echo '<p><b>Returned</b></p>';
    echo "<p>No valid redirect url. Or user just logged out.</p>";
    var_dump($_GET);
}

?>

<br>
<hr>
<h2>SESSION</h2>
<?php @session_start();
var_dump($_SESSION) ?>
<hr>
<br>
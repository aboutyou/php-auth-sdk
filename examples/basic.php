<?php
/**
 * This is a minimal example demonstrating:
 *
 * - Correct order of sdk method calls
 * - Check user login status & adding login/logout functionality
 *
 * PLEASE NOTICE THAT YOU NEED TO ADD {yourdomain}/basic.php
 * TO DEVCENTER CALLBACK URLS AND IN common_params.local.php
 *
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

include __DIR__ . "/../vendor/autoload.php";

use AuthSDK\AuthSDK;
use AuthSDK\SessionStorage;

$configParams = include 'common_params.local.php';

//create sdk
$authSdk = new AuthSDK($configParams, new SessionStorage());

//FIRST: try to parse a possible response:
$authSdk->parseRedirectResponse();

//try to get the user
$oAPIResult = $authSdk->getUser();

//user has not logged in until now
if ($oAPIResult->hasErrors()) {

    echo "
    <body>
        <hr>
        <H1>Login</H1>
        <a href='" . $authSdk->getLoginUrl() . "'>Login</a>
    ";

//user already|just logged in
} else {

    echo "
    <body>
        <hr>
        <H1>Logged in</H1>
        <a href='" . $authSdk->getLogoutUrl() . "'>Logout</a>
    ";
    $aData = $oAPIResult->getResult()->response;
    var_dump($aData);
    var_dump(json_decode($aData));

}

echo "<hr><h3>Session:</h3>";
var_dump($_SESSION);
echo "</body>";
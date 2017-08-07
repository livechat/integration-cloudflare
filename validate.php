<?php

require_once dirname(__FILE__) . '/config.php';


class LC_SSO
{
	protected static $ssoUrl;
	protected static $ssoClientId;
	protected static $ssoSecret;
	
	public static function init()
	{
		if(defined('SSO_URL'))
		{
			self::$ssoUrl = SSO_URL;
		}
		else
		{
			self::$ssoUrl = 'http://sso.livechat';
		}
		if(defined('SSO_CLIENT_ID'))
		{
			self::$ssoClientId = SSO_CLIENT_ID;
		}
		if(defined('SSO_SECRET'))
		{
			self::$ssoSecret = SSO_SECRET;
		}
	}


    public static function getAccessToken($refreshToken)
	{
		self::init();

		$url =  self::$ssoUrl. '/token?integration=cloudflare';

		try {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);
			curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
			
			$post = array(
                'refresh_token' => $refreshToken,
                'client_id' => self::$ssoClientId,
                'client_secret' => self::$ssoSecret,
                'grant_type' => 'refresh_token'
            );
			$query = http_build_query($post);
			curl_setopt($ch, CURLOPT_URL, $url);

			curl_setopt($ch, CURLOPT_POSTFIELDS, $query);
			$response = curl_exec($ch);
            $response = json_decode($response);
		}
		catch (Exception $e) {
			return 0;
		}

		$response_info = curl_getinfo($ch);
		if ($response_info['http_code'] == 200)
		{
			return $response->access_token;
		}
		else
		{
			return 0;
		}
	}

	public static function getInfo($accessToken)
	{
		self::init();

		$url =  self::$ssoUrl. '/info?integration=cloudflare';

		try {
			$ch = curl_init();
			curl_setopt($ch, CURLOPT_URL, $url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
			curl_setopt($ch, CURLOPT_FRESH_CONNECT, 1);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_TIMEOUT, 10);

			$headers = array(
				sprintf('Authorization: Bearer %s', $accessToken)
			);
			curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

			$response = curl_exec($ch);
			$response = json_decode($response, true);
		}
		catch (Exception $e) {
			return 0;
		}

		$response_info = curl_getinfo($ch);
		if ($response_info['http_code'] == 200)
		{
			return $response;
		}
		else
		{
			return 0;
		}
	}
}

class Syslog
{	
	public static $appName = 'integration-cloudflare';
	public static $opened = null;
	public static $params = array
	(
		"severity" => 'Informational', 
		"tagName" => 'request'
	);
	
	private static function openConnection()
	{
		if(self::$opened == null)
		{
			//set app name, options and facility
			self::$opened = openlog(self::$appName, LOG_PID|LOG_ODELAY, LOG_LOCAL1);
		}
	}
	
	public static function logData($message, $params = array())
	{
		$severity = self::$params['severity'];
		$tagName = self::$params['tagName'];
		self::openConnection();
		syslog (LOG_INFO, $severity." ".$tagName." ".$message);
	}
}

function returnJS($response=array())
{
	if(!$response)
	{
		$response = array(
			"proceed" => false
		);
	}
	$ctype = 'text/javascript';
	header('Content-type: ' . $ctype, true);
	header('Access-Control-Allow-Origin: *');

	// Set Expire date
	header('Expires: ' . gmdate('D, d M Y H:i:s', time() - 7200 ) . ' GMT');
	header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
	header("Cache-Control: post-check=0, pre-check=0", false);
	header("Pragma: no-cache"); 

	echo (json_encode($response));
	die();
}

date_default_timezone_set('UTC');


$phpInput = file_get_contents('php://input');

if(!$phpInput)
{
	Syslog::logData("problem with php input: " . print_r(file_get_contents('php://input'), true) ." post: ". print_r($_POST, true) ." get: ". print_r($_GET, true));
	$install = $decodedData->install;

	$install->schema->properties->licenseID = (object) array(
		"title" => "License Number:",
		"type" => "number",
		"value" => "",
		"description" => "Log in to LiveChat in another tab, go to the <a href=\"https://my.livechatinc.com/settings/code\" target=\"_blank\">Code section</a> and copy the license number."
	);

	$install->options->licenseID = "";
	$response = array(
		"proceed" => false,
		"install" => $install
	);
	returnJS($response);
}

$decodedData = json_decode($phpInput);

if(!isset($decodedData->authentications->account->token))
{
	Syslog::logData("problem with token");

	$install = $decodedData->install;

	$install->schema->properties->licenseID = (object) array(
		"title" => "License Number:",
		"type" => "number",
		"value" => "",
		"description" => "Log in to LiveChat in another tab, go to the <a href=\"https://my.livechatinc.com/settings/code\" target=\"_blank\">Code section</a> and copy the license number."
	);

	$install->options->licenseID = "";
	$response = array(
		"proceed" => false,
		"install" => $install
	);
	returnJS($response);
}

$install = $decodedData->install;

$token = $decodedData->authentications->account->token;
$refreshToken = $decodedData->authentications->account->token->refreshToken;

Syslog::logData(json_encode($decodedData->authentications->account->token));

$accessToken = LC_SSO::getAccessToken($refreshToken);
Syslog::logData("accessToken: " . $accessToken);
$userInfo = LC_SSO::getInfo($accessToken);
Syslog::logData("userinfo: " . json_encode($userInfo));

if(!$userInfo)
{
	Syslog::logData("problem with userinfo");
	$response = array(
		"proceed" => false
	);
	returnJS($response);
}

$install->schema->properties->licenseID = (object) array(
	"title" => "License Number:",
	"type" => "number",
	"value" => $userInfo["license_id"],
	"description" => "Log in to LiveChat in another tab, go to the <a href=\"https://my.livechatinc.com/settings/code\" target=\"_blank\">Code section</a> and copy the license number."
);


$install->options->licenseID = $userInfo["license_id"];
$response = array(
	"license_id" => $userInfo["license_id"],
	"proceed" => true,
	"install" => $install
);

returnJS($response);


<?php
require_once "dictionary_constant.php";

require_once "composer/vendor/autoload.php";
$kWAUDecryptor = new \RNCryptor\Decryptor();
$kWAUEncryptor = new \RNCryptor\Encryptor();

$kWAUMysqliHost = "localhost";
$kWAUMysqliUser = "calvinxt_php";
$kWAUMysqliPassword = "ZUe]40Wxq~e}";
$kWAUMysqliDatabase = "calvinxt_pingpong";

$kWAUSystemKey = "4pl5YeFT1MX7QZsND!6v116@7lM1vIxz(SEX*aG)";
$kWAUSystemKeysDirectory = "/home5/calvinxt/doc/pingpong";

try {
	$kWAUMysqli = new mysqli($kWAUMysqliHost, $kWAUMysqliUser, $kWAUMysqliPassword, $kWAUMysqliDatabase);
	$error = $kWAUMysqli->connect_errno;
	if (!empty($error)) throw new Exception("Service Unavailable", 503);
	$kWAUMysqli->autocommit(false);
	$kWAUMysqli->query("SET NAMES 'utf8'");
	
	if (!isset($kWAURequestSignatureNeeded)) $kWAURequestSignatureNeeded = true;
	
	$rawParameterString = file_get_contents("php://input");
	$parameterList = array ();
	
	if (isset($kWAURequiredParameter)) {
		$rawParameter = "";
		try {
			$rawParameter = $kWAUDecryptor->decrypt($rawParameterString, $kWAUSystemKey);
		}
		catch (Exception $e) {
			throw new Exception("Forbidden", 403);
		}
		$parameterList = json_decode($rawParameter, true);
		
		foreach ($kWAURequiredParameter as $requiredParameterInfo) {
			list ($parameterKey, $parameterName) = $requiredParameterInfo;
			
			if ($parameterKey != $kWAUDictionaryKeyDevelopment && !isset($parameterList[$parameterKey])) throw new Exception("Bad Request", 400);
			$$parameterName = $parameterList[$parameterKey];
		}
	}
	
	if ($kWAURequestSignatureNeeded) {
		$headers = apache_request_headers();
		if (!isset($headers["Authorization"]) || !isset($parameterList[$kWAUDictionaryKeyUserId])) throw new Exception("Forbidden", 403);
		preg_match('/^WAUSign (.*)$/', $headers["Authorization"], $signature);
		if (!isset($signature[1])) throw new Exception("Forbidden", 403);
		list ($nonce, $signature) = explode("|", $signature[1], 2);
		
		$sql = "SELECT generatedKey FROM WAUUser WHERE userId = ?";
		$stmt = $kWAUMysqli->prepare($sql);
		if (!$stmt) throw new Exception();
		
		$stmt->bind_param("s", $parameterList[$kWAUDictionaryKeyUserId]);
		if (!$stmt->execute()) throw new Exception();
		$stmt->bind_result($userGeneratedKey);
		if (!$stmt->fetch()) throw new Exception();
		$stmt->close();
		
		try {
			$matchingNonce = $kWAUDecryptor->decrypt($signature, $userGeneratedKey);
			if ($matchingNonce != $nonce) throw new Exception();
		}
		catch (Exception $e) {
			throw new Exception("Forbidden", 403);
		}
	}
}
catch (Exception $e) {
	$code = $e->getCode();
	$message = $e->getMessage();
	if (empty($code)) {
		$code = 500;
		$message = "Internal Server Error";
	}
	
	header("HTTP/1.1 " . $code . " " . $message);
	exit();
}
?>
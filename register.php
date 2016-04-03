<?php
require_once "dictionary_constant.php";

$kWAURequestSignatureNeeded = false;

$kWAURequiredParameter = array ();
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyPlatform, "platform");

require_once "bootstrap.php";

$output = array ();
try {
	$userId = sha1(rand() . time());
	$characters = "0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ!@#$%^&*()";
	$generatedKey = "";
	for($i = 0; $i < 40; $i++) {
		$generatedKey .= $characters[rand(0, strlen($characters) - 1)];
	}
	
	$sql = "INSERT INTO WAUUser (userId, generatedKey, platform) VALUES (?, ?, ?)";
	$stmt = $kWAUMysqli->prepare($sql);
	if (!$stmt) throw new Exception();
	
	$stmt->bind_param("ssi", $userId, $generatedKey, $platform);
	if (!$stmt->execute()) throw new Exception();
	$stmt->close();
	
	$output[$kWAUDictionaryKeyUserId] = $userId;
	$output[$kWAUDictionaryKeyGeneratedKey] = $generatedKey;
}
catch (Exception $e) {
	$kWAUMysqli->close();
	
	header("HTTP/1.1 500 Internal Server Error");
	exit();
}

$kWAUMysqli->commit();
$kWAUMysqli->close();

echo $kWAUEncryptor->encrypt(json_encode($output), $kWAUSystemKey);
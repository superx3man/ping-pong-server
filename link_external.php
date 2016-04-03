<?php
require_once "dictionary_constant.php";

$kWAURequestSignatureNeeded = true;

$kWAURequiredParameter = array ();
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyUserId, "userId");
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyExternalId, "externalId");
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyExternalType, "externalType");

require_once "bootstrap.php";

$output = array ();
try {
	$sql = "SELECT id FROM WAUUser WHERE userId = ?";
	$stmt = $kWAUMysqli->prepare($sql);
	if (!$stmt) throw new Exception();
	
	$stmt->bind_param("s", $userId);
	if (!$stmt->execute()) throw new Exception();
	
	$stmt->bind_result($systemUserId);
	if (!$stmt->fetch()) throw new Exception();
	
	$stmt->close();

	$sql = "REPLACE INTO WAUExternalId (userId, type, externalId) VALUES (?, ?, ?)";
	$stmt = $kWAUMysqli->prepare($sql);
	if (!$stmt) throw new Exception();
	
	$stmt->bind_param("iis", $systemUserId, $externalType, $externalId);
	if (!$stmt->execute()) throw new Exception();
	
	$stmt->close();
}
catch (Exception $e) {
	$kWAUMysqli->close();
	
	header("HTTP/1.1 500 Internal Server Error");
	exit();
}

$kWAUMysqli->commit();
$kWAUMysqli->close();

echo $kWAUEncryptor->encrypt(json_encode($output), $kWAUSystemKey);
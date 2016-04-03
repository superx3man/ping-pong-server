<?php
require_once "dictionary_constant.php";

$kWAURequestSignatureNeeded = true;

$kWAURequiredParameter = array ();
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyUserId, "userId");
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyExternalList, "externalList");
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyExternalType, "externalType");

require_once "bootstrap.php";

$output = array ();
try {
	$output[$kWAUDictionaryKeyContactList] = array ();
	
	$sql = "SELECT u.userId, u.username, u.userColor FROM WAUExternalId e LEFT JOIN WAUUser u ON e.userId = u.id WHERE e.type = 0 AND e.externalId = ?";
	$stmt = $kWAUMysqli->prepare($sql);
	if (!$stmt) throw new Exception();
	
	$stmt->bind_param("s", $externalId);
	$stmt->bind_result($contactUserId, $contactUsername, $contactUserColor);
	foreach ($externalList as $externalId) {
		if (!$stmt->execute()) throw new Exception();
		while ($stmt->fetch()) {
			$output[$kWAUDictionaryKeyContactList][] = array ($kWAUDictionaryKeyUserId => $contactUserId, $kWAUDictionaryKeyUsername => $contactUsername, $kWAUDictionaryKeyUserColor => $contactUserColor);
		}
	}
	
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
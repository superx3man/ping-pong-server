<?php
require_once "dictionary_constant.php";

$kWAURequestSignatureNeeded = true;

$kWAURequiredParameter = array ();
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyDevelopment, "isDev");
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyUserId, "userId");
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyContactId, "contactId");
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyIsNewContact, "isNewContact");

require_once "bootstrap.php";

$output = array ();
try {
	$sql = "SELECT id, username, userColor, userIcon, version, platform, deviceToken FROM WAUUser WHERE userId = ?";
	$stmt = $kWAUMysqli->prepare($sql);
	if (!$stmt) throw new Exception();
	
	$stmt->bind_param("s", $contactId);
	if (!$stmt->execute()) throw new Exception();
	
	$stmt->bind_result($systemContactId, $username, $userColor, $userIconLink, $version, $platform, $deviceToken);
	if (!$stmt->fetch()) throw new Exception();
	
	$stmt->close();
	
	$sql = "DELETE FROM WAURelation WHERE userId IN (SELECT id FROM WAUUser WHERE userId = ?) AND type = 1 AND targetUserId = ?";
	$stmt = $kWAUMysqli->prepare($sql);
	if (!$stmt) throw new Exception();
	
	$stmt->bind_param("ss", $userId, $systemContactId);
	if (!$stmt->execute()) throw new Exception();
	
	$stmt->close();
	
	$output[$kWAUDictionaryKeyUsername] = $username;
	$output[$kWAUDictionaryKeyUserColor] = $userColor;
	
	if (!empty($userIconLink)) {
		$localUserIconLink = "i/" . basename($userIconLink);
		$userIcon = base64_encode(file_get_contents($localUserIconLink));
		
		$output[$kWAUDictionaryKeyUserIcon] = $userIcon;
	}
	
	$output[$kWAUDictionaryKeyVersion] = (int) $version;
}
catch (Exception $e) {
	$kWAUMysqli->close();
	
	header("HTTP/1.1 500 Internal Server Error");
	exit();
}

$kWAUMysqli->commit();
$kWAUMysqli->close();

echo $kWAUEncryptor->encrypt(json_encode($output), $kWAUSystemKey);
<?php
require_once "dictionary_constant.php";

$kWAURequestSignatureNeeded = true;

$kWAURequiredParameter = array ();
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyUserId, "userId");
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyContactId, "contactId");
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyRelationType, "relationType");

require_once "bootstrap.php";

$output = array ();
try {
	$sql = "SELECT u1.id, u2.id FROM WAUUser u1, WAUUser u2 WHERE u1.userId = ? AND u2.userId = ?";
	$stmt = $kWAUMysqli->prepare($sql);
	if (!$stmt) throw new Exception();
	
	$stmt->bind_param("ss", $userId, $contactId);
	if (!$stmt->execute()) throw new Exception();
	
	$stmt->bind_result($userSystemId, $contactSystemId);
	if (!$stmt->fetch()) throw new Exception();
	
	$stmt->close();
	
	if ($relationType >= 0) {
		$sql = "REPLACE INTO WAURelation (userId, type, targetUserId) VALUES (?, ?, ?)";
		$stmt = $kWAUMysqli->prepare($sql);
		if (!$stmt) throw new Exception();
		
		$stmt->bind_param("iii", $userSystemId, $relationType, $contactSystemId);
		if (!$stmt->execute()) throw new Exception();
		
		$stmt->close();
		
		if ($relationType == 1) {
			$sql = "UPDATE WAURequest SET type = -1 WHERE fromUserId = ? AND toUserId = ? AND type >= 0";
			$stmt = $kWAUMysqli->prepare($sql);
			if (!$stmt) throw new Exception();
				
			$stmt->bind_param("ii", $contactSystemId, $userSystemId);
			if (!$stmt->execute()) throw new Exception();
				
			$stmt->close();
		}
	}
	else {
		$relationType = abs($relationType);
		
		$sql = "DELETE FROM WAURelation WHERE userId = ? AND type = ? AND targetUserId = ?";
		$stmt = $kWAUMysqli->prepare($sql);
		if (!$stmt) throw new Exception();
		
		$stmt->bind_param("iii", $userSystemId, $relationType, $contactSystemId);
		if (!$stmt->execute()) throw new Exception();
		
		$stmt->close();
	}
}
catch (Exception $e) {
	$kWAUMysqli->close();
	
	header("HTTP/1.1 500 Internal Server Error");
	exit();
}

$kWAUMysqli->commit();
$kWAUMysqli->close();

echo $kWAUEncryptor->encrypt(json_encode($output), $kWAUSystemKey);
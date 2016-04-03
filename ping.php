<?php
require_once "dictionary_constant.php";

$kWAURequestSignatureNeeded = true;

$kWAURequiredParameter = array ();
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyUserId, "userId");
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyContactId, "contactId");
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyPingType, "pingType");
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyLocationInfo, "locationInfo");

require_once "bootstrap.php";

$output = array ();
try {
	$sql = "SELECT u1.id, u1.username, u1.version, u2.id, u2.platform, u2.deviceToken FROM WAUUser u1, WAUUser u2 WHERE u1.userId = ? AND u2.userId = ?";
	$stmt = $kWAUMysqli->prepare($sql);
	if (!$stmt) throw new Exception();
	
	$stmt->bind_param("ss", $userId, $contactId);
	if (!$stmt->execute()) throw new Exception();
	
	$stmt->bind_result($userSystemId, $username, $userVersion, $contactSystemId, $contactPlatform, $contactDeviceToken);
	if (!$stmt->fetch()) throw new Exception();
	
	$stmt->close();
	
	$sql = "UPDATE WAURequest SET type = -1 WHERE fromUserId = ? AND toUserId = ? AND type >= 0";
	$stmt = $kWAUMysqli->prepare($sql);
	if (!$stmt) throw new Exception();
	
	$stmt->bind_param("ss", $contactSystemId, $userSystemId);
	if (!$stmt->execute()) throw new Exception();
	
	$stmt->close();
	
	$sql = "SELECT COUNT(1) FROM WAURelation WHERE userId = ? AND targetUserId = ? AND type = 1";
	$stmt = $kWAUMysqli->prepare($sql);
	if (!$stmt) throw new Exception();
	
	$stmt->bind_param("ss", $contactSystemId, $userSystemId);
	if (!$stmt->execute()) throw new Exception();
	
	$stmt->bind_result($relationCount);
	if (!$stmt->fetch()) throw new Exception();
	$isUserBlocked = $relationCount != 0;
	
	$stmt->close();
	
	if (!empty($contactDeviceToken) && !$isUserBlocked) {
		list ($latitude, $longitude, $altitude, $accuracy, $lastUpdated) = explode(":", $locationInfo);
		
		$sql = "INSERT INTO WAURequest (fromUserId, toUserId, type, latitude, longitude, altitude, accuracy, lastUpdated) VALUES (?, ?, 0, ?, ?, ?, ?, ?)";
		$stmt = $kWAUMysqli->prepare($sql);
		if (!$stmt) throw new Exception();
		
		$stmt->bind_param("iissssi", $userSystemId, $contactSystemId, $latitude, $longitude, $altitude, $accuracy, $lastUpdated);
		if (!$stmt->execute()) throw new Exception();
		$stmt->close();
		
		$sql = "SELECT COUNT(DISTINCT fromUserId) FROM WAURequest WHERE toUserId = ? AND type = 0";
		$stmt = $kWAUMysqli->prepare($sql);
		if (!$stmt) throw new Exception();
		
		$stmt->bind_param("i", $contactSystemId);
		if (!$stmt->execute()) throw new Exception();
		
		$badgeCount = 0;
		$stmt->bind_result($badgeCount);
		if (!$stmt->fetch()) throw new Exception();
		
		$stmt->close();
		
		if ($contactPlatform == 0 || $contactPlatform == 1) {
			$isDev = $contactPlatform == 1;
			
			$socket = stream_context_create();
			stream_context_set_option($socket, "ssl", "local_cert", $kWAUSystemKeysDirectory . ($isDev ? "/ios_cert_dev" : "/ios_cert"));
			stream_context_set_option($socket, "ssl", "passphrase", trim(file_get_contents($kWAUSystemKeysDirectory . "/ios")));
			
			$fp = stream_socket_client($isDev ? "ssl://gateway.sandbox.push.apple.com:2195" : "ssl://gateway.push.apple.com:2195", $err, $errstr, 60, STREAM_CLIENT_CONNECT | STREAM_CLIENT_PERSISTENT, $socket);
			if (!$fp) throw new Exception();
			
			$payload = array ();
			$textPlaceholder = $pingType == 0 ? "Ping!" : "Pong~";
			$payload["aps"] = array ("content-available" => 1, "alert" => $textPlaceholder . " from " . $username, "sound" => "default", "badge" => $badgeCount);
			if ($pingType == 0) $payload["aps"]["category"] = "kWAUNotificationCategoryIdentifierRequestLocation";
			
			$payload[$kWAUDictionaryKeyContentType] = "ping";
			$payload[$kWAUDictionaryKeyUserId] = $userId;
			$payload[$kWAUDictionaryKeyLocationInfo] = $locationInfo;
			$payload[$kWAUDictionaryKeyVersion] = $userVersion;
			
			$payload = json_encode($payload);
			
			$message = chr(0) . pack("n", 32) . pack("H*", $contactDeviceToken) . pack("n", strlen($payload)) . $payload;
			$result = fwrite($fp, $message, strlen($message));
			
			fclose($fp);
			
			if (!$result) throw new Exception();
		}
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
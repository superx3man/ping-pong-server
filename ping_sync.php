<?php
require_once "dictionary_constant.php";

$kWAURequestSignatureNeeded = true;

$kWAURequiredParameter = array ();
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyUserId, "userId");

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
	
	$pingDictionary = array ();
	
	$sql = "SELECT userId, version, latitude, longitude, altitude, accuracy, lastUpdated FROM WAURequest r LEFT JOIN WAUUser u ON u.id = r.fromUserId WHERE toUserId = ? AND type = 0";
	$stmt = $kWAUMysqli->prepare($sql);
	if (!$stmt) throw new Exception();
	
	$stmt->bind_param("i", $systemUserId);
	if (!$stmt->execute()) throw new Exception();
	
	$stmt->bind_result($fromUserId, $version, $latitude, $longitude, $altitude, $accuracy, $lastUpdated);
	while ($stmt->fetch()) {
		if (!isset($pingDictionary[$fromUserId])) {
			$pingInfo = array ();
			$pingInfo[] = $lastUpdated;
			$pingInfo[] = 1;
			$pingInfo[] = implode(":", array ($latitude, $longitude, $altitude, $accuracy, $lastUpdated));
			$pingInfo[] = $version;
			$pingDictionary[$fromUserId] = $pingInfo;
		}
		else {
			$pingDictionary[$fromUserId][1] += 1;
			if ($lastUpdated >= $pingDictionary[$fromUserId][0]) {
				$pingDictionary[$fromUserId][0] = $lastUpdated;
				$pingDictionary[$fromUserId][2] = implode(":", array ($latitude, $longitude, $altitude, $accuracy, $lastUpdated));
			}
		}
	}
	
	$stmt->close();
	
	$sql = "UPDATE WAURequest SET type = 1 WHERE toUserId = ? AND type = 0";
	$stmt = $kWAUMysqli->prepare($sql);
	if (!$stmt) throw new Exception();
	
	$stmt->bind_param("i", $systemUserId);
	if (!$stmt->execute()) throw new Exception();
	
	$stmt->close();
	
	foreach ($pingDictionary as $fromUserId => $fromUserInfo) {
		list (,$pingCount, $pingLocationInfo, $userVersion) = $fromUserInfo;
		$pingInfo = array ();
		$pingInfo[$kWAUDictionaryKeyUserId] = $fromUserId;
		$pingInfo[$kWAUDictionaryKeyLocationInfo] = $pingLocationInfo;
		$pingInfo[$kWAUDictionaryKeyPingCount] = $pingCount;
		$pingInfo[$kWAUDictionaryKeyVersion] = $userVersion;
		$output[] = $pingInfo;
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
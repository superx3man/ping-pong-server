<?php
require_once "dictionary_constant.php";

$kWAURequestSignatureNeeded = true;

$kWAURequiredParameter = array ();
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyUserId, "userId");
$kWAURequiredParameter[] = array ($kWAUDictionaryKeyModifiedList, "modifiedList");

require_once "bootstrap.php";

$output = array ();
try {
	if (!empty($modifiedList)) {
		$columnNameList = array ();
		$columnTypeList = array ();
		$columnValueList = array ();
		
		foreach ($modifiedList as $modifiedKey => $modifiedValue) {
			if ($modifiedKey == $kWAUDictionaryKeyPlatform) {
				$columnNameList[] = "platform";
				$columnTypeList[] = "i";
				$columnValueList[] = $modifiedValue;
			}
			else if ($modifiedKey == $kWAUDictionaryKeyUsername) {
				$columnNameList[] = "username";
				$columnTypeList[] = "s";
				$columnValueList[] = $modifiedValue;
			}
			else if ($modifiedKey == $kWAUDictionaryKeyUserColor) {
				$columnNameList[] = "userColor";
				$columnTypeList[] = "s";
				$columnValueList[] = $modifiedValue;
			}
			else if ($modifiedKey == $kWAUDictionaryKeyUserIcon) {
				$filename = "i/" . sha1(rand() . time());
				file_put_contents($filename, base64_decode($modifiedValue));
				
				$columnNameList[] = "userIcon";
				$columnTypeList[] = "s";
				$columnValueList[] = "http://" . $_SERVER["SERVER_NAME"] . "/" . $filename;
				
				$sql = "SELECT userIcon FROM WAUUser WHERE userId = ?";
				$stmt = $kWAUMysqli->prepare($sql);
				if (!$stmt) throw new Exception();
				$stmt->bind_param("s", $userId);
				
				if (!$stmt->execute()) throw new Exception();
				$stmt->bind_result($oldUserIconLink);
				if (!$stmt->fetch()) throw new Exception();
				$stmt->close();
				
				if (!empty($oldUserIconLink)) {
					$oldFilename = basename($oldUserIconLink);
					unlink("i/" . $oldFilename);
				}
			}
			else if ($modifiedKey == $kWAUDictionaryKeyNotificationKey) {
				$columnNameList[] = "deviceToken";
				$columnTypeList[] = "s";
				$columnValueList[] = $modifiedValue;
				
				$sql = "SELECT userId FROM WAUUser WHERE deviceToken = ? AND userId <> ?";
				$stmt = $kWAUMysqli->prepare($sql);
				if (!$stmt) throw new Exception();
				$stmt->bind_param("ss", $modifiedValue, $userId);
				
				if (!$stmt->execute()) throw new Exception();
				$stmt->bind_result($oldUserId);
				$oldUserList = array ();
				while ($stmt->fetch()) {
					$oldUserList[] = $oldUserId;
				}
				$stmt->close();
				
				if (!empty($oldUserList)) {
					$sql = "UPDATE WAUUser SET deviceToken = NULL WHERE userId = ?";
					$stmt = $kWAUMysqli->prepare($sql);
					if (!$stmt) throw new Exception();
					$stmt->bind_param("s", $oldUserId);
					
					foreach ($oldUserList as $oldUserId) {
						if (!$stmt->execute()) throw new Exception();
					}
					$stmt->close();
				}
			}
		}
		
		$columnTypeList[] = "s";
		$columnValueList[] = $userId;
		$columnValuePointerList = array ();
		for ($i = 0; $i < count($columnValueList); $i++) {
			$columnValuePointerList[$i] = &$columnValueList[$i];
		}
		
		$setUpdateSql = function ($columnName)
		{
			return $columnName . " = ?";
		};
		$sql = "UPDATE WAUUser SET version = version + 1, " . implode(", ", array_map($setUpdateSql, $columnNameList)) . " WHERE userId = ?";
		$stmt = $kWAUMysqli->prepare($sql);
		if (!$stmt) throw new Exception();
		
		call_user_func_array(array ($stmt, "bind_param"), array_merge(array (implode("", $columnTypeList)), $columnValuePointerList));
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
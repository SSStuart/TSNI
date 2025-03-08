<?php
require_once('./globalVar.inc.php');

function pkProcessing($pk) {
	$signe = substr($pk,3,1);
	if ($signe === "+") {
		$pk = str_replace("+", "", $pk);
		return intval($pk);
	} elseif ($signe === "-") {
		$pk = str_replace("-", "", $pk);
		return -(intval($pk));
	}
}

$sqlRequest .= "SELECT * FROM `LinesNetworks` WHERE `sens` = 'ALIGNEMENT' ORDER BY `nomLigne`, `voie`, `pkd` LIMIT 50;";
$conn = new mysqli(SERVER_NAME, USERNAME, PWD, DB_NAME);
$conn->set_charset("utf8mb4");
$result = $conn->execute_query($sqlRequest);
if ($result->num_rows > 0) {
	while ($row = $result->fetch_assoc()) {
		echo (pkProcessing($row["pkf"]) - pkProcessing($row["pkd"]))." | ". number_format(sqrt(($row["Xf"] - $row["Xd"])**2 + ($row["Zf"] - $row["Zd"])**2), 10, ",")."<br>";
	}
}
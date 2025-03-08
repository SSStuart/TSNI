<?php
require_once('./globalVar.inc.php');
header('Content-Type: application/json');

// Create connection
$conn = new mysqli(SERVER_NAME, USERNAME, PWD, DB_NAME);
mysqli_set_charset($conn, "utf8mb4");

if (isset($_GET["segmentsZone"])) {
	if (isset($_GET["lonLeft"]) && isset($_GET["lonRight"]) && isset($_GET["latBottom"]) && isset($_GET["latTop"]) &&
		is_numeric($_GET["lonLeft"]) && is_numeric($_GET["lonRight"]) && is_numeric($_GET["latBottom"]) && is_numeric($_GET["latTop"])){
		
		$sql = "SELECT codeLigne, nomLigne, pkd, pkf FROM LinesNetworks WHERE (Xd>=".$_GET["lonLeft"]." AND Xd<=".$_GET["lonRight"]." AND Zd>=".$_GET["latBottom"]." AND Zd<=".$_GET["latTop"].") OR (Xf>=".$_GET["lonLeft"]." AND Xf<=".$_GET["lonRight"]." AND Zf>=".$_GET["latBottom"]." AND Zf<=".$_GET["latTop"].") ORDER BY nomLigne, pkd";
		$result = $conn->query($sql);
		
		$linesDatas = [];
		$uniqueLines = [];
		$nbResults = 0;
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				$nbResults++;
				if (!in_array($row["nomLigne"], $uniqueLines)) {
					$uniqueLines[] = $row["nomLigne"];
				}
				$pkDifference = intval(preg_replace("/-|\+/", "", $row["pkf"])) - intval(preg_replace("/-|\+/", "", $row["pkd"]));
				if (isset($linesSum[$row["nomLigne"]])) {
					$linesSum[$row["nomLigne"]] += $pkDifference;
				} else {
					$linesSum[$row["nomLigne"]] = $pkDifference;
				}
				$linesDatas[] = array("codeLigne" => $row["codeLigne"], "nomLigne" => $row["nomLigne"]);
			}
			echo json_encode(array("nbResults" => $nbResults, "uniqueLines" => $uniqueLines, "linesDatas" => $linesDatas, "linesSum" => $linesSum));
		} else {
			echo json_encode("Error: 0 results");
		}
	} else {
		echo json_encode("Error: missing parameters");
	}
} else if (isset($_GET["segmentsLines"])) {
	if (isset($_GET["lines"]) && $_GET["lines"] != ""){
		$lines = explode(",", str_replace("'","\'",substr($_GET["lines"], 0, -1)));
		$linesStr = "";
		foreach ($lines as $line) {
			$linesStr .= "nomLigne = '".$line."' OR ";
		}
		$linesStr = substr($linesStr, 0, -4);

		$sql = "SELECT COUNT(*) as 'nbResults' FROM LinesNetworks WHERE ".$linesStr;
		$result = $conn->query($sql);
		
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				echo json_encode($row["nbResults"]);
			}
		} else {
			echo json_encode("0 results");
		}
	} else {
		echo json_encode("Error: missing parameters");
	}
}
?>
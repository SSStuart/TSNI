<?php
header('Content-Type: image/svg+xml');

$servername = "sql106.byethost31.com";
$username = "b31_26395455";
$password = "kdSbe8pHbQ4r";
$dbname = "b31_26395455_TSNI";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
$conn->set_charset("utf8");

$lineId = '';

$json_str = file_get_contents('php://input');

// Get as an object or array
$json_obj = json_decode($json_str, true);

$lineSqlPart = "";
$lineOutsideSqlPart = "";
$zoneSqlPart = "";
if (isset($json_obj["selectedLines"]) && count($json_obj["selectedLines"]) > 0) {
	$lines = "'".implode("','", str_replace("'","\'",$json_obj["selectedLines"]))."'";
	$lineSqlPart = "nomLigne in (".$lines.")";
}
if (isset($json_obj["outsideLines"]) && count($json_obj["outsideLines"]) > 0) {
	$linesOutside = "'".implode("','", str_replace("'","\'",$json_obj["outsideLines"]))."'";
	$lineOutsideSqlPart = "nomLigne in (".$linesOutside.")";
}
if (isset($json_obj["latTop"]) && $json_obj["latTop"] != "" && isset($json_obj["lonLeft"]) && $json_obj["lonLeft"] != "" && isset($json_obj["latBottom"]) && $json_obj["latBottom"] != "" && isset($json_obj["lonRight"]) && $json_obj["lonRight"] != "") {
	$latTop = $json_obj["latTop"];
	$lonLeft = $json_obj["lonLeft"];
	$latBottom = $json_obj["latBottom"];
	$lonRight = $json_obj["lonRight"];
	$zoneSqlPart = ($lineSqlPart == "" ? "" : " AND ")." ((Xd>=".$lonLeft." AND Xd<=".$lonRight." AND Zd>=".$latBottom." AND Zd<=".$latTop.") OR (Xf>=".$lonLeft." AND Xf<=".$lonRight." AND Zf>=".$latBottom." AND Zf<=".$latTop."))";
}

if ((isset($json_obj["selectedLines"]) && count($json_obj["selectedLines"]) > 0) || (isset($json_obj["latTop"]) && $json_obj["latTop"] != "" && isset($json_obj["lonLeft"]) && $json_obj["lonLeft"] != "" && isset($json_obj["latBottom"]) && $json_obj["latBottom"] != "" && isset($json_obj["lonRight"]) && $json_obj["lonRight"] != "")) {
	$sql = "SELECT codeLigne, nomLigne, pkd, pkf, Xd, Zd, Xf, Zf FROM LinesNetworks WHERE " . ($lineSqlPart != "" ? $lineSqlPart : "") . ($zoneSqlPart != "" ? $zoneSqlPart : "") . ($lineOutsideSqlPart != "" ? (" OR " . $lineOutsideSqlPart) : "" ) ." ORDER BY nomLigne, pkd";

	$result = $conn->query($sql);

	$Xd = array();
	$Zd = array();
	$Xf = array();
	$Zf = array();
	$codeLigneColor = array();
	$nomLigne = array();
	if ($result->num_rows > 0) {
		// output data of each row
		while($row = $result->fetch_assoc()) {
			$Xd[] = $row["Xd"];
			$Zd[] = $row["Zd"];
			$Xf[] = $row["Xf"];
			$Zf[] = $row["Zf"];
			$colorHue = substr(md5($row["nomLigne"]), 0, 2);
			$codeLigneColor[] = hexdec($colorHue) % 360;
			$nomLigne[] = $row["nomLigne"];
		}
	}
	$conn->close();

	// Get the min values in $Xd and $Xf
	$minX = min(min($Xd), min($Xf));
	// Get the max values in $Xd and $Xf
	$maxX = max(max($Xd), max($Xf));
	// Get the min values in $Zd and $Zf
	$minZ = min(min($Zd), min($Zf));
	// Get the max values in $Zd and $Zf
	$maxZ = max(max($Zd), max($Zf));

	$offsetZ = $maxZ;
	$offsetX = $minX;

	$width = $maxX - $minX;
	$height = $maxZ - $minZ;
	$aspectRatio = $width / $height;

	$svgCode = '<svg id="generatedPreview" height="300" width="300" style="aspect-ratio: '.$aspectRatio.';" xmlns="http://www.w3.org/2000/svg">';
	$counter = 0;
	foreach ($Xd as $key => $value) {
		$svgCode .= '<line x1="'.(($Xd[$key]-$offsetX) /$width *100).'%" y1="'.(($maxZ - $Zd[$key]) /$height *100).'%" x2="'.(($Xf[$key]-$offsetX) /$width*100).'%" y2="'.(($maxZ - $Zf[$key]) /$height *100).'%" style="stroke:hsl('.$codeLigneColor[$key].',100%, 50%);stroke-width: min(0.5vh, 5px); stroke-linecap: round;" data-lineName="'.$nomLigne[$key].'"/>';
		$counter = ($counter+1) % 255;
	}
	$svgCode .= '</svg>';
	echo $svgCode;
}
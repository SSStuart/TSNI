<?php
require_once('./globalVar.inc.php');
require_once '/home/crtpxwuz/vendor/autoload.php';

// header("Content-type: text/xml; charset=utf-8");
header("Content-type: application/json;");

use MathPHP\Number\Complex;

$loader = new \Twig\Loader\FilesystemLoader('/home/crtpxwuz/public_html/TSNI/templates');
$twig = new \Twig\Environment($loader, [
    'cache' => '/home/crtpxwuz/vendor/twig/compilation_cache',
]);

define("LOGGING_LEVEL", "ERROR");

// FUNCTION
	function logger($message, $type = "INFO") {
		if (LOGGING_LEVEL == "ALL" || 
			LOGGING_LEVEL == "INFO" && ($type == "INFO" || $type == "ERROR") ||
			LOGGING_LEVEL == "ERROR" && ($type == "ERROR"))
			echo "<br><code>[". date('h:i:s', time()) ."] {$type} | {$message} </code>";
	}

	function decToHex($n) {
		$hexa = array("a","b","c","d","e","f");
		if ($n >= 10 && $n <= 15) {
			$n = $hexa[$n-10];
		}
		return $n;
	}

	function generateID($type = "dec", $lenght = 9) {
		$randomID = "";
		switch ($type) {
			case 'devStr':
				for ($char = 1; $char < 37; $char++) {
					if (in_array($char, [9, 14, 19, 24]))
						$randomID .= "-";
					else
						$randomID .= decToHex(rand(0,15));
				}
				break;
			case 'dec':
			default:
				for ($char = 1; $char <= $lenght; $char++)
					$randomID .= rand(0,9);
				break;
		}
		return $randomID;
	}

	function generateGUID() {
		return [generateID("dec", 19), generateID("dec", 19), generateID("devStr")];
	}

	function convertGeodeticToUTM($long, $lat) {
		global $zoneNumber;

		$long = deg2rad($long);
		$lat = deg2rad($lat);
		$Long0 = 0;
		switch ($zoneNumber) {
			case 30:
				$Long0 = deg2rad(-3);
				break;
			case 31:
				$Long0 = deg2rad(3);
				break;
			case 32:
				$Long0 = deg2rad(9);
				break;
			default:
				break;
		}
		$j = 0.0818192;
		$k0 = 0.9996;
		$a = 6378137;
		$v = 1 / (sqrt( 1 - $j**2 * sin($lat)**2));
		$A = ($long - $Long0) * cos($lat);
		$s = (1 - $j**2 / 4 - 3*$j**4 / 64 - 5*$j**6 / 256) * $lat 
			- (3*$j**2 / 8 + 3*$j**4 / 32 + 45*$j**6 / 1024) * sin(2*$lat) 
			+ (15*$j**4 / 256 + 45*$j**6 / 1024) * sin(4*$lat) 
			- 35*$j**6 / 3072 * sin(6*$lat);
		$T = tan($lat)**2;
		$C = $j**2 / (1 - $j**2) * cos($lat)**2;
		
		$X_UTM = $k0*$a*$v* ($A + (1-$T+$C) * $A**3 / 6 + (5-18*$T+$T**2) * $A**5/120);
		$Z_UTM = $k0*$a* ($s + $v * tan($lat) * ($A**2 / 2 + (5-$T+9*$C+4*$C**2) * $A**4 / 24 + (61-58*$T+$T**2) * $A**6 / 720));

		return ["X" => $X_UTM, "Z" => $Z_UTM];
	}

	function getTileCoordAndSegRelCoord($segDepX, $segDepZ) {
		$tileX = floor($segDepX / 1024);
		$tileZ = floor($segDepZ / 1024);
		$segDepXRel = $segDepX - 1024 * $tileX;
		$segDepZRel = $segDepZ - 1024 * $tileZ;

		return ["tileX" => $tileX, "tileZ" => $tileZ, "segDepXRel" => $segDepXRel, "segDepZRel" => $segDepZRel];
	}

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

	function newTrackTileFile($X, $Z) {
		$XPart = ($X >= 0 ? "+" : "-").str_pad(abs($X), 6, "0", STR_PAD_LEFT);
		$ZPart = ($Z >= 0 ? "+" : "-").str_pad(abs($Z), 6, "0", STR_PAD_LEFT);
		return "{$XPart}{$ZPart}.xml";
	}

// INITIALISATION VARIABLES + CONST
	$routeName = isset($_GET["routeName"]) ? $_GET["routeName"] : "TSNI - Itinéraire généré";
	$latStart = isset($_GET["latStart"]) ? $_GET["latStart"] : 48.750000;
	$lonStart = isset($_GET["lonStart"]) ? $_GET["lonStart"] : 2.300000;
	$latTop = isset($_GET["latTop"]) ? $_GET["latTop"] : 48.800000;
	$lonLeft = isset($_GET["lonLeft"]) ? $_GET["lonLeft"] : 2.250000;
	$latBottom = isset($_GET["latBottom"]) ? $_GET["latBottom"] : 48.700000;
	$lonRight = isset($_GET["lonRight"]) ? $_GET["lonRight"] : 2.350000;
	$selectedLines = isset($_GET["selectedLines"]) ? $_GET["selectedLines"] : []; 
	$selectedOutsideLines = isset($_GET["selectedOutsideLines"]) ? $_GET["selectedOutsideLines"] : []; 
	$segmentPrecedent = null;
	$detailRubans = [];
	$tilesList = [];
	$trackTiles = [];
	$zoneNumber = ($lonStart < 0 ? 30 : ($lonStart < 6 ? 31 : 32));

	$routePropreties = [
		"route" => [
			"ids" => generateGUID(),
			"name" => [
				"fr" => $routeName,
			],
			"origin" => [
				"long" => $lonStart,
				"lat" => $latStart
			],
			"mapOffset" => [
				"X" => round(convertGeodeticToUTM($lonStart, $latStart)["X"]),
				"Z" => round(convertGeodeticToUTM($lonStart, $latStart)["Z"])
			],
			"zoneNumber" => $zoneNumber,
		]
	];

	$trackData = [];
// RECUPERATION DONNEES
	// REQUETE SQL
	$sqlRequest = "SELECT * FROM `LinesNetworks` WHERE (
		((`Xd` >= {$lonLeft} AND `Xd` <= {$lonRight} AND `Zd` >= {$latBottom} AND `Zd` <={$latTop}) 
		OR (`Xf` >= {$lonLeft} AND `Xf` <= {$lonRight} AND `Zf` >= {$latBottom} AND `Zf` <= {$latTop}))";
	
	if (count($selectedLines) > 0) {
		$sqlRequest .= " AND ";
		for ($line = 0; $line <count($selectedLines); $line++) {
			$sqlRequest .= "`nomLigne` = '".iconv("UTF-8", "ISO-8859-1",$selectedLines[$line])."'";
			if ($line+1 < count($selectedLines)) {
				$sqlRequest .= " OR ";
			}
		}
	}
	$sqlRequest .= ")";
	if (count($selectedOutsideLines) > 0) {
		$sqlRequest .= " OR ";
		for ($line = 0; $line <count($selectedOutsideLines); $line++) {
			$sqlRequest .= "`nomLigne` = '".iconv("UTF-8", "ISO-8859-1",$selectedOutsideLines[$line])."'";
			if ($line+1 < count($selectedOutsideLines)) {
				$sqlRequest .= " OR ";
			}
		}
	}

	$sqlRequest .= " ORDER BY `nomLigne`, `voie`, `pkd`;";
	$conn = new mysqli(SERVER_NAME, USERNAME, PWD, DB_NAME);
	$conn->set_charset("utf8mb4");
	$result = $conn->execute_query($sqlRequest);
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			$segments[] = [
				"id" => generateID(),
				"GUIDs" => generateGUID(),
				"voie" => $row["voie"], 
				"codeLigne" => $row["codeLigne"], 
				"nomLigne" => $row["nomLigne"], 
				"sensCourbe" => $row["sens"], 
				"rayonCourbe" => intval($row["rayon"]), 
				"pkd" => pkProcessing($row["pkd"]), 
				"pkf" => pkProcessing($row["pkf"]), 
				"segDepX" => convertGeodeticToUTM(floatval($row["Xd"]), floatval($row["Zd"]))["X"] - $routePropreties["route"]["mapOffset"]["X"], 
				"segDepZ" => convertGeodeticToUTM(floatval($row["Xd"]), floatval($row["Zd"]))["Z"] - $routePropreties["route"]["mapOffset"]["Z"], 
				"segFinX" => convertGeodeticToUTM(floatval($row["Xf"]), floatval($row["Zf"]))["X"] - $routePropreties["route"]["mapOffset"]["X"], 
				"segFinZ" => convertGeodeticToUTM(floatval($row["Xf"]), floatval($row["Zf"]))["Z"] - $routePropreties["route"]["mapOffset"]["Z"], 
				"longueur" => abs(pkProcessing($row["pkf"]) - pkProcessing($row["pkd"])),
			];
		}
	}
	$conn->close();

	$routeDirectory = "./generatedFiles/".$routePropreties["route"]["GUIDs"][2];
	mkdir($routeDirectory, 0777, true);
	mkdir($routeDirectory."/Networks");
	mkdir($routeDirectory."/Networks/Track Tiles");
	mkdir($routeDirectory."/RouteInformation");
	//copy("pictures/thumbnail.png",$routeDirectory."/RouteInformation/image.png");



// GENERATION FICHIER ROUTEPROPERTIES
	//$routePropretiesFile = fopen($routeDirectory."/RouteProperties.xml","w");
	//fwrite($routePropretiesFile, $twig->render('RouteProperties_template.xml', $routePropreties));

// GENERATION FICHIERS TRACK TILES
	foreach ($segments as $key => $segment) {
		if ($key >= 20)
			break;
		if ($segment["rayonCourbe"] === 0)

			$angletp = 0;
			if ($segment["longueur"] > 0) {
				try {
					$angletp = (180 / pi()) * acos(($segment["segFinX"] - $segment["segDepX"]) / $segment["longueur"]);
				} catch (\Throwable $th) {
					logger($th->getMessage() . " - l:".$th->getLine(), "ERROR");
				}
			}
		else {

			$deltaAngle = 0;
			if ($segment["rayonCourbe"] > 0) {
				try {
					$deltaAngle = 360 * $segment["longueur"] / (2 * pi() * $segment["rayonCourbe"]);
				} catch (\Throwable $th) {
					logger($th->getMessage() . " - l:".$th->getLine(), "ERROR");
				}
			}
			$segtpX = $segment["segFinX"] - $segment["segDepX"];
			$segtpZ = $segment["segFinZ"] - $segment["segDepZ"];
			$zF = new Complex($segtpX, $segtpZ);
			$W = new Complex(sqrt($segment["rayonCourbe"]**2 - ($zF->abs() / 2) **2), 0);
			$zU = new Complex($segtpX / 2, $segtpZ / 2);

			if ($segment["sensCourbe"] === "GAUCHE")
				$S = 1;
			else
				$S = -1;

			if ($deltaAngle < 180)
				$T = 1;
			else
				$T = -1;

			if ($zF->abs() > 0) {
				try {
					$angletp = ($segtpZ >= 0 ? 1 : -1) * acos($segtpX / $zF->abs());
				} catch (\Throwable $th) {
					logger($th->getMessage() . " - l:".$th->getLine(), "ERROR");
				}
			}

			$exp_term = new Complex(cos($angletp), sin($angletp));
			$i_complex = new Complex(0, 1);
			$exp_term_i = $exp_term->multiply($i_complex);
			$zC = $zU->add($W->multiply($exp_term_i)->multiply($S)->multiply($T));

			$centreX = $zC->r;
			$centreZ = $zC->i;
			$angletp = ($centreZ >= 0 ? 1 : -1) * acos($centreX / $zC->abs());
			$zA = $exp_term_i->multiply($S);
			$aX = $zA->r;
			$aZ = $zA->i;
			$angletp = ($aZ >= 0 ? 1 : -1) * acos($aX / $zA->abs());
		}

		if ($key > 0) { /* Ignorer si premier segment */
			if (abs($angletp - $segmentPrecedent["angle"]) >= 0.00001 && abs($angletp - $segmentPrecedent["angle"]) <= 0.5)
				logger("Rupture angle : segment n° {$key}, segDepX = {$segment["segDepX"]}, segDepZ = {$segment["segDepZ"]}");
			if (abs($segment["segDepX"] - $segmentPrecedent["X"]) + abs($segment["segDepZ"] - $segmentPrecedent["Z"]) >= 0.01 && 
				abs($segment["segDepX"] - $segmentPrecedent["X"]) + abs($segment["segDepZ"] - $segmentPrecedent["Z"]) <= 1)
				logger("Discontinuité : segment n° {$key}, segDepX = {$segment["segDepX"]}, segDepZ = {$segment["segDepZ"]}");
		}

		$angle = $angletp;
		if ($segment["longueur"] > 500) {
			$nombreCoupes = intdiv($segment["longueur"], 500);
			$reste = $segment["longueur"] % 500;
			if ($nombreCoupes > 0 && $reste === 0)
				$nombreCoupes -= 1;
		} else {
			$nombreCoupes = 0;
			$reste = 0;
		}
		$detailRubans = [];
		for ($decoupe=0; $decoupe <= $nombreCoupes ; $decoupe++) { 
			$coupe = [
				"longueur" => $segment["longueur"],
				"segDepX" => $segment["segDepX"],
				"segDepZ" => $segment["segDepZ"],
				"angle" => $angle
			];

			if ($nombreCoupes > 0) {
				$coupe["longueur"] = 500;
				if ($decoupe == $nombreCoupes)
					$coupe["longueur"] = $reste;
			}

			if ($decoupe > 0) {
				$coupe["segDepX"] = $segmentPrecedent["X"];
				$coupe["segDepZ"] = $segmentPrecedent["Z"];
				$coupe["angle"] = $segmentPrecedent["angle"];
			}

			$tileCoord = getTileCoordAndSegRelCoord($coupe["segDepX"], $coupe["segDepZ"]);
			$X = $tileCoord["tileX"];
			$Z = $tileCoord["tileZ"];
			$coupe["segDepXRel"] = $coupe["segDepX"] - 1024 * $X;
			$coupe["segDepZRel"] = $coupe["segDepZ"] - 1024 * $Z;

			$detailRubans[] = [
				"id" => generateID(),
				"X" => $X,
				"segDepX" => $coupe["segDepXRel"],
				"Z" => $Z,
				"segDepZ" => $coupe["segDepZRel"],
				"longueur" => $coupe["longueur"],
				"angle" => [
					"value" => $angle,
					"cos" => cos($angle),
					"sin" => sin($angle)
				]
			];
			
			foreach ($tilesList as $tile) {
				if ($tile["X"] === $X && $tile["Z"] === $Z)
					$tile["segmentsIds"][] = $segment["id"];
				else
					$tilesList[] = ["X" => $X, "Z" => $Z, "segmentsIds" => [$segment["id"]]];
			}
			
			$segmentPrecedent = ["X" => $coupe["segDepX"], "Z" => $coupe["segDepZ"], "angle" => $angle];

			if ($segment["rayonCourbe"] > 0) {
				$varDir = 360 * $coupe["longueur"] / (2 * pi() * $segment["rayonCourbe"]);
				$angletp = $angle;
				if ($segment["sensCourbe"] === "GAUCHE") {
					$segmentPrecedent["angle"] += $varDir;
					$segmentPrecedent["X"] += (- cos(deg2rad($angletp - 90)) + cos(deg2rad($segmentPrecedent["angle"] - 90))) * $segment["rayonCourbe"];
					$segmentPrecedent["Z"] += (- sin(deg2rad($angletp - 90)) + sin(deg2rad($segmentPrecedent["angle"] - 90))) * $segment["rayonCourbe"];
				} else {
					$segmentPrecedent["angle"] -= $varDir;
					$segmentPrecedent["X"] += (- cos(deg2rad($angletp + 90)) + cos(deg2rad($segmentPrecedent["angle"] + 90))) * $segment["rayonCourbe"];
					$segmentPrecedent["Z"] += (- sin(deg2rad($angletp + 90)) + sin(deg2rad($segmentPrecedent["angle"] + 90))) * $segment["rayonCourbe"];
				}
			} else {
				$segmentPrecedent["X"] += cos($segmentPrecedent["angle"]) * $coupe["longueur"];
				$segmentPrecedent["Z"] += sin($segmentPrecedent["angle"]) * $coupe["longueur"];
			}

			$trackTilesDatas["{$X}|{$Z}"][] = ["id" => generateID(), "GUIDs" => $segment["GUIDs"], "rayonCourbe" => $segment["rayonCourbe"], "sensCourbe" => $segment["sensCourbe"], "segments" => $detailRubans];
		}
	}

	foreach ($trackTilesDatas as $key => $trackTile) {
		$tileX = explode("|", $key)[0];
		$tileZ = explode("|", $key)[1];
		$trackTileFile = fopen($routeDirectory."/Networks/Track Tiles/".newTrackTileFile($tileX, $tileZ),"w");
		$rubans = ["rubans" => $trackTile];
		fwrite($trackTileFile, $twig->render('TrackTile_template.xml', $rubans));
	}

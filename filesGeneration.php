<?php
require_once('./globalVar.inc.php');
require_once '/home/crtpxwuz/vendor/autoload.php';

// header("Content-type: application/json;");

use MathPHP\Number\Complex;

$loader = new \Twig\Loader\FilesystemLoader('/home/crtpxwuz/public_html/TSNI/templates');
$twig = new \Twig\Environment($loader, [
    'cache' => '/home/crtpxwuz/vendor/twig/compilation_cache',
]);

define("LOGGING_LEVEL", "WARN");

// FUNCTION
	function logger($message, $type = "INFO", ...$dumpVar) {
		if (LOGGING_LEVEL == "ALL" || 
			LOGGING_LEVEL == "INFO" && ($type == "INFO" || $type == "WARN" || $type == "ERROR") ||
			LOGGING_LEVEL == "WARN" && ($type == "WARN" || $type == "ERROR") ||
			LOGGING_LEVEL == "ERROR" && ($type == "ERROR")) {
			echo "<br><code style='padding-inline-start: 1em;border-left: 2px dashed #888;'>[". date('h:i:s', time()).":". gettimeofday()["usec"] ."] {$type} | {$message} </code>";
			foreach ($dumpVar as $key => $var) {
				echo "<pre style='padding-inline-start: 2em;margin-block-start: 0;border-left: 2px solid #888;'>";
				print_r($var);
				echo "</pre>";
			}
		}
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
					else {
						if ($char === 1)
							$randomID .= decToHex(rand(1,15));
						else
							$randomID .= decToHex(rand(0,15));
					}
				}
				break;
			case 'dec':
			default:
				for ($char = 1; $char <= $lenght; $char++) {
					if ($char === 1)
						$randomID .= rand(1,9);
					else
						$randomID .= rand(0,9);
				}
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

	function decoupeSegment($segment) {
		// logger("Variable segment", "WARN", $segment);
		global $segmentPrecedent;
		$longueur = $segment["longueur"];
		$segDepX = $segment["segDepX"];
		$segDepZ = $segment["segDepZ"];
		$angle = $segment["angle"];
		$sensCourbe = $segment["sensCourbe"];
		$rayonCourbe = $segment["rayonCourbe"];
		$coupes = [];

		$nbCoupes = floor($longueur / 500);
		$longueurDernCoupe = fmod($longueur, 500);
		if ($longueurDernCoupe > 0)
			$nbCoupes++;
		
		for ($coupe = 1; $coupe <= $nbCoupes; $coupe++) { 
			if ($coupe < $nbCoupes)
				$longueur = 500;
			else
				$longueur = $longueurDernCoupe;

			if ($coupe > 1) {
				$segDepX = $segmentPrecedent["X"];
				$segDepZ = $segmentPrecedent["Z"];
				$angle = $segmentPrecedent["angle"];
			}

			$tileCoord = getTileCoordAndSegRelCoord($segDepX, $segDepZ);
			$tileX = $tileCoord["tileX"];
			$tileZ = $tileCoord["tileZ"];
			$segDepXRel = $tileCoord["segDepXRel"];
			$segDepZRel = $tileCoord["segDepZRel"];

			$segmentId = generateID();

			if ($sensCourbe !== "ALIGNEMENT") {
				$varDir = rad2deg($segment["longueur"] / $segment["rayonCourbe"]);
				$segmentPrecedent["angle"] = $angle + ($sensCourbe === "GAUCHE" ? 1 : -1) * $varDir;
				if ($sensCourbe === "GAUCHE") {
					$segmentPrecedent["X"] = $segDepX + (-cos(deg2rad($angle - 90)) + cos(deg2rad($segmentPrecedent["angle"] - 90))) * $rayonCourbe;
					$segmentPrecedent["Z"] = $segDepZ + (-sin(deg2rad($angle - 90)) + sin(deg2rad($segmentPrecedent["angle"] - 90))) * $rayonCourbe;
				} else {
					$segmentPrecedent["X"] = $segDepX + (-cos(deg2rad($angle + 90)) + cos(deg2rad($segmentPrecedent["angle"] + 90))) * $rayonCourbe;
					$segmentPrecedent["Z"] = $segDepZ + (-sin(deg2rad($angle + 90)) + sin(deg2rad($segmentPrecedent["angle"] + 90))) * $rayonCourbe;
				}
			} else {
				$segmentPrecedent["angle"] = $angle;
				$segmentPrecedent["X"] = $segDepX + cos(deg2rad($angle)) * $longueur;
				$segmentPrecedent["Z"] = $segDepZ + sin(deg2rad($angle)) * $longueur;
			}

			$coupes[] = ["id" => $segmentId, "rubanId" => $segment["rubanId"], "rubanGUIDs" => $segment["rubanGUIDs"], "sensCourbe" => $sensCourbe, "rayonCourbe" => $rayonCourbe, 
				"tileX" => $tileX, "segDepXRel" => $segDepXRel, "tileZ" => $tileZ, "segDepZRel" => $segDepZRel, "longueur" => $longueur, "angle" => $angle, "angleFin" => $segment["angleFin"], 
				"segDepXAbs" => $segDepX, "segDepZAbs" => $segDepZ ];
		}

		// logger("Valeurs retournées", "WARN", $coupes);
		return $coupes;
	}

	function newTrackTileFile($X, $Z) {
		$XPart = ($X >= 0 ? "+" : "-").str_pad(abs($X), 6, "0", STR_PAD_LEFT);
		$ZPart = ($Z >= 0 ? "+" : "-").str_pad(abs($Z), 6, "0", STR_PAD_LEFT);
		return "{$XPart}{$ZPart}.xml";
	}

	function segmentContainsId($var): bool {
		return $var["id"];
	}

// INITIALISATION VARIABLES + CONST
	$routeName = $_GET["routeName"] ?? "TSNI - Itinéraire généré";
	$latStart = $_GET["latStart"] ?? 48.750000;
	$lonStart = $_GET["lonStart"] ?? 2.300000;
	$latTop = $_GET["latTop"] ?? 48.800000;
	$lonLeft = $_GET["lonLeft"] ?? 2.250000;
	$latBottom = $_GET["latBottom"] ?? 48.700000;
	$lonRight = $_GET["lonRight"] ?? 2.350000;
	$selectedLines = $_GET["selectedLines"] ?? []; 
	$selectedOutsideLines = $_GET["selectedOutsideLines"] ?? [];
	$trackRuleBlueprint = [
		"provider" => $_GET["ProviderTrackRule"] ?? "DTG",
		"product" => $_GET["ProductTrackRule"] ?? "Academy",
		"blueprint" => $_GET["trackRule"] ?? "RailNetwork\\TrackRule\\TSA_TrackRuleDE.xml",
	];
	$trackBlueprint = [
		"provider" => $_GET["ProviderTrack"] ?? "DTG",
		"product" => $_GET["ProductTrack"] ?? "Academy",
		"blueprint" => $_GET["track"] ?? "RailNetwork\\Track\\track01_conc.xml",
	];
	
	$segmentPrecedent = [];
	$rubans = [];
	$segments = [];
	$detailRubans = [];
	$tilesList = [];
	$trackTiles = [];
	$zoneNumber = ($lonStart < 0 ? 30 : ($lonStart < 6 ? 31 : 32));


	// \/ DEBUG \/
	$sqlRequest = "SELECT * FROM `LinesNetworks` WHERE `IDGAIA` = 'd161f15c-4e53-11ea-98ff-014c64e0362d' 
													OR `IDGAIA` = 'd161f2d0-4e53-11ea-98ff-014c64e0362d' 
													OR `IDGAIA` = 'd161f448-4e53-11ea-98ff-014c64e0362d' 
													OR `IDGAIA` = 'd161f5aa-4e53-11ea-98ff-014c64e0362d' 
													OR `IDGAIA` = 'd161f71a-4e53-11ea-98ff-014c64e0362d' 
													OR `IDGAIA` = 'd161f880-4e53-11ea-98ff-014c64e0362d'
													ORDER BY `nomLigne`, `nomVoie`, `pkd`;";
	$latStart = 48.367;
	$lonStart = -4.1598;
	$latTop = $latStart + 0.2;
	$lonLeft = $lonStart - 0.2;
	$latBottom = $latStart - 0.2;
	$lonRight = $lonStart + 0.2;
	// /\ DEBUG /\

	$routePropreties = [
		"GUIDs" => generateGUID(),
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
	];

	$trackData = [];
// RECUPERATION DONNEES
	// REQUETE SQL
	// $sqlRequest = "SELECT * FROM `LinesNetworks` WHERE (
	// 	((`Xd` >= {$lonLeft} AND `Xd` <= {$lonRight} AND `Zd` >= {$latBottom} AND `Zd` <={$latTop}) 
	// 	OR (`Xf` >= {$lonLeft} AND `Xf` <= {$lonRight} AND `Zf` >= {$latBottom} AND `Zf` <= {$latTop}))";
	
	// if (count($selectedLines) > 0) {
	// 	$sqlRequest .= " AND ";
	// 	for ($line = 0; $line <count($selectedLines); $line++) {
	// 		$sqlRequest .= "`nomLigne` = '".iconv("UTF-8", "ISO-8859-1",$selectedLines[$line])."'";
	// 		if ($line+1 < count($selectedLines)) {
	// 			$sqlRequest .= " OR ";
	// 		}
	// 	}
	// }
	// $sqlRequest .= ")";
	// if (count($selectedOutsideLines) > 0) {
	// 	$sqlRequest .= " OR ";
	// 	for ($line = 0; $line <count($selectedOutsideLines); $line++) {
	// 		$sqlRequest .= "`nomLigne` = '".iconv("UTF-8", "ISO-8859-1",$selectedOutsideLines[$line])."'";
	// 		if ($line+1 < count($selectedOutsideLines)) {
	// 			$sqlRequest .= " OR ";
	// 		}
	// 	}
	// }

	// $sqlRequest .= " ORDER BY `nomLigne`, `nomVoie`, `pkd`;";
	$conn = new mysqli(SERVER_NAME, USERNAME, PWD, DB_NAME);
	$conn->set_charset("utf8mb4");
	$result = $conn->execute_query($sqlRequest);
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			$segmentsBDD[] = [
				"GUIDs" => generateGUID(),
				"numVoie" => $row["nomVoie"], 
				"codeLigne" => $row["codeLigne"], 
				"nomLigne" => $row["nomLigne"], 
				"sensCourbe" => $row["sens"], 
				"rayonCourbe" => ($row["sens"] !== "ALIGNEMENT" ? intval($row["rayon"]) : -1), 
				"pkd" => pkProcessing($row["pkd"]), 
				"pkf" => pkProcessing($row["pkf"]), 
				"segDepX" => convertGeodeticToUTM(floatval($row["Xd"]), floatval($row["Zd"]))["X"] - $routePropreties["mapOffset"]["X"], 
				"segDepZ" => convertGeodeticToUTM(floatval($row["Xd"]), floatval($row["Zd"]))["Z"] - $routePropreties["mapOffset"]["Z"], 
				"segFinX" => convertGeodeticToUTM(floatval($row["Xf"]), floatval($row["Zf"]))["X"] - $routePropreties["mapOffset"]["X"], 
				"segFinZ" => convertGeodeticToUTM(floatval($row["Xf"]), floatval($row["Zf"]))["Z"] - $routePropreties["mapOffset"]["Z"], 
				"longueur" => ($row["sens"] === "ALIGNEMENT" ? (sqrt(abs($row["Xf_L93"] - $row["Xd_L93"]) ** 2 + abs($row["Zf_L93"] - $row["Zd_L93"]) ** 2)) : abs(pkProcessing($row["pkf"]) - pkProcessing($row["pkd"]))),
			];
		}
	}
	$conn->close();

	$routeDirectory = "./generatedFiles/".$routePropreties["GUIDs"][2];
	mkdir($routeDirectory, 0777, true);
	mkdir($routeDirectory."/Networks");
	mkdir($routeDirectory."/Networks/Track Tiles");
	mkdir($routeDirectory."/RouteInformation");
	copy("pictures/thumbnail.png",$routeDirectory."/RouteInformation/image.png");



// GENERATION FICHIER ROUTEPROPERTIES
	$routePropretiesFile = fopen($routeDirectory."/RouteProperties.xml","w");
	fwrite($routePropretiesFile, $twig->render('RouteProperties_template.xml', ["route" => $routePropreties]));

// GENERATION FICHIERS TRACK TILES
	foreach ($segmentsBDD as $key => $segment) {
		$segmentPrecedent["GUIDs"] = null;

		if ($segment["sensCourbe"] === "ALIGNEMENT") {
			$angle = 0;

			if ($segment["longueur"] > 0)
				$angle = rad2deg(acos(($segment["segFinX"] - $segment["segDepX"]) / $segment["longueur"]));
			else
				logger("Division par 0 (longueur)", "ERROR", $segment);

		}
		else {

			$deltaAngle = 0;
			if ($segment["rayonCourbe"] > 0)
				$deltaAngle = rad2deg($segment["longueur"] / $segment["rayonCourbe"]);
			else 
				logger("Division par 0 (rayonCourbe)", "ERROR", $segment);

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

			if ($zF->abs() > 0)
				$angleTemp = ($segtpZ >= 0 ? 1 : -1) * acos($segtpX / $zF->abs());
			else
				logger("Division par 0 (module(z(F))", "ERROR", $segment);

			$exp_term = new Complex(cos($angleTemp), sin($angleTemp));
			$i_complex = new Complex(0, 1);
			$exp_term_i = $exp_term->multiply($i_complex);
			$zC = $zU->add($W->multiply($exp_term_i)->multiply($S)->multiply($T));

			$centreX = $zC->r;
			$centreZ = $zC->i;
			$angleTemp = ($centreZ >= 0 ? 1 : -1) * acos($centreX / $zC->abs());
			$zA = $exp_term_i->multiply($S);
			$aX = $zA->r;
			$aZ = $zA->i;
			$angle = rad2deg(($aZ >= 0 ? 1 : -1) * acos($aX / $zA->abs()));
		}

		if ($key > 0 && $segment["nomLigne"] === $segmentPrecedent["nomLigne"] && $segment["numVoie"] === $segmentPrecedent["numVoie"]) { /* Ignorer si premier segment */
			if ($segmentPrecedent["pkf"] !== $segment["pkd"])
				logger("Discontinuité : segment n°{$key}, segDepX = {$segment["segDepX"]}, segDepZ = {$segment["segDepZ"]}");
			elseif (abs($angleTemp - $segmentPrecedent["angle"]) >= 0.00001 && abs($angleTemp - $segmentPrecedent["angle"]) <= 0.5)
				logger("Rupture angle : segment n°{$key}, segDepX = {$segment["segDepX"]}, segDepZ = {$segment["segDepZ"]}");
		}

		// if (is_nan($angle))
		// 	logger("⚠ Angle à une valeur NaN. segment #".$key, "ERROR", $segment);

		$tileCoord = getTileCoordAndSegRelCoord($segment["segDepX"], $segment["segDepZ"]);
		$tileX = $tileCoord["tileX"];
		$tileZ = $tileCoord["tileZ"];
		$segment["segDepXRel"] = $segment["segDepX"] - 1024 * $tileX;
		$segment["segDepZRel"] = $segment["segDepZ"] - 1024 * $tileZ;

		if ($segment["sensCourbe"] !== "ALIGNEMENT") {
			$varDir = rad2deg($segment["longueur"] / $segment["rayonCourbe"]);
			$angleFin = $angle + $varDir * $S;
		} else {
			$angleFin = $angle;
		}

		if (isset($segmentPrecedent["GUIDs"])) {
			$rubanPrecedent = $rubans[count($rubans)-1];
			if ($rubanPrecedent["nomLigne"] === $segment["nomLigne"] && $rubanPrecedent["pkf"] === $segment["pkd"] 
				&& $rubanPrecedent["numVoie"] === $segment["numVoie"] && $rubanPrecedent["longueur"] + $segment["longueur"] < 10000 && $rubanPrecedent["angleFin"] >= $angle - 0.1 && $rubanPrecedent["angleFin"] <= $angle + 0.1) {
				$rubans[count($rubans)-1]["longueur"] += $segment["longueur"];
				$rubans[count($rubans)-1]["arrX"] = $segment["segFinX"];
				$rubans[count($rubans)-1]["arrZ"] = $segment["segFinZ"];
				$rubans[count($rubans)-1]["pkf"] = $segment["pkf"];
			}
		} else {
			$rubanId = generateID();
			$rubanGUIDs = generateGUID();
			$segmentPrecedent["GUIDs"] = $rubanGUIDs;
			$rubans[] = ["id" => $rubanId, "GUIDs" => $rubanGUIDs, "tileX" => $tileX, "coordRelX" => $segment["segDepXRel"], "tileZ" => $tileZ, "coordRelZ" => $segment["segDepZRel"], "longueur" => $segment["longueur"], 
				"arrX" => $segment["segFinX"], "arrZ" => $segment["segFinZ"], "angleFin" => $angleFin, "nomLigne" => $segment["nomLigne"], "pkd" => $segment["pkd"], "pkf" => $segment["pkf"], "numVoie" => $segment["numVoie"],
				"coordAbsX" => $segment["segDepX"], "coordAbsZ" => $segment["segDepZ"]];
			// logger("Ruban inséré :", "ERROR", $rubans[count($rubans) - 1]);
		}

		$segment2 = ["rubanId" => $rubanId, "rubanGUIDs" => $rubanGUIDs, "sensCourbe" => $segment["sensCourbe"], "rayonCourbe" => $segment["rayonCourbe"], "segDepX" => $segment["segDepX"], "segDepZ" => $segment["segDepZ"], "longueur" => $segment["longueur"], "angle" => $angle, "angleFin" => $angleFin];

		$segmentDecoupes = decoupeSegment($segment2);
		foreach ($segmentDecoupes as $key => $coupe) {
			logger("✂ Coupe", "ERROR", $coupe);
			$tileX = $coupe["tileX"];
			$tileZ = $coupe["tileZ"];
			$segmentId = $coupe["id"];

			$existingTileIndex = -1;
			if (count($tilesList) > 0) {
				for ($i = 0; $i < count($tilesList); $i++) {
					$tile = $tilesList[$i];
					if ($tile["X"] === $tileX && $tile["Z"] === $tileZ) {
						$existingTileIndex = $i;
						break;
					}		
				}
			}
			if ($existingTileIndex >= 0)
				$tilesList[$existingTileIndex]["segmentsIds"][] = $segmentId;
			else
				$tilesList[] = ["X" => $tileX, "Z" => $tileZ, "segmentsIds" => [$segmentId]];

				$segments[] = $coupe;
		}

		$segmentPrecedent["angle"] = $angleFin;
		$segmentPrecedent["pkf"] = $segment["pkf"];
		$segmentPrecedent["nomLigne"] = $segment["nomLigne"];
		$segmentPrecedent["numVoie"] = $segment["numVoie"];
	}


	$trackTileTemplateData = [];
	foreach ($tilesList as $tile) {
		$tileSegments = array_filter($segments, function ($segment) use ($tile) {
			return in_array($segment["id"], $tile["segmentsIds"]);
		}); // Merci ChatGPT
		usort($tileSegments, function ($a, $b) use ($tile) {
			return array_search($a["id"], $tile["segmentsIds"]) - array_search($b["id"], $tile["segmentsIds"]);
		});	// Merci ChatGPT

		$segmentPrecedentRibonId = null;
		foreach ($tileSegments as $key => $segment) {
			// Création d'un nouveau ruban dans la liste
			if ($segment["rubanId"] !== $segmentPrecedentRibonId) {
				$trackTileTemplateData[] = [
					"id" => generateID(), 
					"GUIDs" => $segment["rubanGUIDs"], 
					"sensCourbe" => $segment["sensCourbe"], 
					"rayonCourbe" => $segment["rayonCourbe"], 
					"curves" => []
				];
				$segmentPrecedentRibonId = $segment["rubanId"];
			}
			// Ajout d'un segment au dernier ruban défini
			$trackTileTemplateData[count($trackTileTemplateData) - 1]["curves"][] = [
				"id" => $segment["id"], 
				"longueur" => $segment["longueur"], 
				"tileX" => $segment["tileX"], 
				"coordRelX" => $segment["segDepXRel"], 
				"tileZ" => $segment["tileZ"], 
				"coordRelZ" => $segment["segDepZRel"], 
				"angle" => [
					"cos" => cos($segment["angle"]),
					"sin" => sin($segment["angle"]),
				],
			];
		}

	}

	// echo json_encode($trackTileTemplateData);
	foreach ($trackTileTemplateData as $key => $tile) {
		$trackTileFile = fopen($routeDirectory."/Networks/Track Tiles/".newTrackTileFile($tile["curves"][0]["tileX"], $tile["curves"][0]["tileZ"]),"w");
		fwrite($trackTileFile, $twig->render('TrackTile_template.xml', ["rubans" => [$tile]]));
	}
	

// GENERATION FICHIER TRACKS.BIN
	$regleVoie = "";
	$typeVoie = "";
	$vitessesPrimaire = [];
	$vitessesSecondaire = [];
	$electrifications = [];
	$qualitesVoie = [];
	$vitesseAuto = false;
	
	$rubansFormated = [];

	$trackNetworkGUIDs = generateGUID();

	foreach ($rubans as $key => $ruban) {
		$rubanId = $ruban["id"];
		$tileX = $ruban["tileX"];
		$tileZ = $ruban["tileZ"];
		$tileRelRibDepX = $ruban["coordRelX"];
		$tileRelRibDepZ = $ruban["coordRelZ"];
		$ribExtentX = $ruban["arrX"] - ($ruban["tileX"] * 1024 + $ruban["coordRelX"]);
		$ribExtentZ = $ruban["arrZ"] - ($ruban["tileZ"] * 1024 + $ruban["coordRelZ"]);
		$ribLongueur = $ruban["longueur"];
		$ribPkd = $ruban["pkd"];
		$ribHeights = [];

		$ribHeights[] = ["start" => 0, "end" => $ribLongueur, "height" => 0.300008];

		if (!$vitesseAuto)
			$vitesses = ["start" => 0, "end" => $ribLongueur, "primary" => 200, "secondary" => 100];
			// $vitesses = ["start" => 0, "end" => $ribLongueur, "primary" => $vitessesPrimaire[$ruban["nomLigne"]], "secondary" => $vitessesSecondaire[$ruban["nomLigne"]]];
		else {
			$ribVitesses = [/* DONNEES DE LA BDD */];
			$vitesses = [];
			foreach ($ribVitesses as $key => $ribVitesse) {
				$vitesses[] = ["start" => max(0, $ribVitesse["pkd"] - $ribPkd), "end" => min($ribLongueur, $ribVitesse["pkf"] - $ribPkd), "primary" => $ribVitesse["vitesse"], "secondary" => $ribVitesse["vitesse"]];
			}
		}

		// $ribElec = ["start" => 0, "end" => $ribLongueur, $electrifications[$ruban["numLigne"]]];
		// $ribQual = ["start" => 0, "end" => $ribLongueur, $qualitesVoie[$ruban["numLigne"]]];
		$ribElec = ["start" => 0, "end" => $ribLongueur, "electrification" => "OverheadWires"];
		$ribQual = ["start" => 0, "end" => $ribLongueur, "qualite" => 1];

		$ribbonProps = ["id" => $rubanId, "GUIDs" => $ruban["GUIDs"], "heights" => $ribHeights, "longueur" => $ribLongueur, "tileX" => $tileX, "tileZ" => $tileZ, "coordRelX" => $tileRelRibDepX, "coordRelZ" => $tileRelRibDepZ, 
			"extentX" => $ribExtentX, "extentZ" => $ribExtentZ, "regleVoie" => $trackRuleBlueprint, "typeVoie" => $trackBlueprint, "vitesses" => $vitesses, "electrification" => $ribElec, "qualite" => $ribQual];
		$rubansFormated[] = $ribbonProps;
	}

	$extremitesTraitees = [];
	foreach ($rubans as $key => $ruban) {
		$extremitesTraitees[$ruban["id"]] = [ "0" => false, "1" => false];
	}

	$nodesFormated = [];
	foreach ($rubans as $key => $ruban) {
		// logger("Execution de la boucle foreach ribbon1", "WARN");
		$ribPos0 = ["X" => $ruban["tileX"] * 1024 + $ruban["coordRelX"], "Z" => $ruban["tileZ"] * 1024 + $ruban["coordRelZ"]];
		$ribPos1 = ["X" => $ruban["arrX"], "Z" => $ruban["arrZ"]];

		for ($extremite = 0; $extremite <= 1; $extremite++) { 
			$nodeExtrems = [];
			if ($extremite === 0)
				$pos1 = $ribPos0;
			else
				$pos1 = $ribPos1;
			// logger("_Execution de la boucle foreach extremite1 (#{$extremite})", "WARN");
			if ($extremitesTraitees[$ruban["id"]][$extremite] === false) {
				// logger("__Extremite non traitee. 💠Traitement extrémité {$extremite} du ruban {$key}", "WARN");
				$nodeExtrems[] = ["rubanGUIDs" => $ruban["GUIDs"], "extremPos" => $extremite];
				$extremitesTraitees[$ruban["id"]][$extremite] = true;

				foreach ($rubans as $key2 => $ruban2) {
					// logger("___Execution de la boucle foreach ribbon2", "WARN");
					$rib2Pos0 = ["X" => $ruban2["tileX"] * 1024 + $ruban2["coordRelX"], "Z" => $ruban2["tileZ"] * 1024 + $ruban2["coordRelZ"]];
					$rib2Pos1 = ["X" => $ruban2["arrX"], "Z" => $ruban2["arrZ"]];

					for ($extremite2 = 0; $extremite2 <= 1; $extremite2++) { 
						if ($extremite2 === 0)
							$pos2 = $rib2Pos0;
						else
							$pos2 = $rib2Pos1;
						// logger("____Execution de la boucle foreach extremite2 (#{$extremite2})", "WARN");
						if ($extremitesTraitees[$ruban2["id"]][$extremite2] === false) {
							// logger("_____Extremite non traitee", "WARN");
							if (sqrt(abs($pos1["X"] - $pos2["X"]) ** 2 + abs($pos1["Z"] - $pos2["Z"]) ** 2) <= 0.001) {
								// logger("______Distance < 0.001. 💠Traitement extrémité {$extremite2} du ruban {$key2}", "WARN");
								$nodeExtrems[] = ["rubanGUIDs" => $ruban2["GUIDs"], "extremPos" => $extremite2];
								$extremitesTraitees[$ruban2["id"]][$extremite2] = true;
							} 
							// else {
							// 	logger("_____.. mais distance trop grande", "WARN");
							// }
						} 
						// else {
						// 	logger("_____✅Extremite déjà traitee", "WARN");
						// }
					}
				}
			} 
			// else {
			// 	logger("__✅Extremite déjà traitee", "WARN");
			// }

			if (count($nodeExtrems) > 0) {
				$nodesFormated[] = ["id" => generateID(), "extremites" => $nodeExtrems];
			}
		}
	}

	$tracksTemplateData = [
		"trackNetwork" => [
			"id" => generateID(),
			"GUIDs" => generateGUID(),
			"rubans" => $rubansFormated,
			"nodes" => $nodesFormated,
		],
	];

	// echo json_encode($tracksTemplateData);

	$tracksFile = fopen($routeDirectory."/Networks/Tracks.xml","w");
	fwrite($tracksFile, $twig->render('Tracks_template.xml', $tracksTemplateData));
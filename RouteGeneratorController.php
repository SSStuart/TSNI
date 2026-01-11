<?php

namespace App\Http\Controllers\tsni;

use App\Http\Controllers\Controller;
use App\Models\tsni\LineSegment;
use ErrorException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use MathPHP\Number\Complex;

class RouteGeneratorController extends Controller
{
	private $routeName;
	private $latStart;
	private $lonStart;
	private $latTop;
	private $lonLeft;
	private $latBottom;
	private $lonRight;
	private $selectedLines; 
	private $selectedOutsideLines;
	private $trackRuleBlueprint;
	private $trackBlueprint;
    private $segmentPrecedent;
	private $rubans;
	private $segments;
	private $detailRubans;
	private $tilesList;
	private $trackTiles;
	private $zoneNumber;
    private $routeProperties;
    private $trackData;
	private $S;

    public function generateLine(Request $request) {
        // $formDatas = $request->validate([
        //     "routeName" => "nullable|string",
        //     "latStart" => "required|numeric",
        //     "lonStart" => "required|numeric",
        //     "latTop" => "required|numeric",
        //     "lonLeft" => "required|numeric",
        //     "latBottom" => "required|numeric",
        //     "lonRight" => "required|numeric",
        //     "selectedLines" => "required|array",
        //     "selectedOutsideLines" => "nullable|array",

        //     "ProviderTrackRule" => "required|string",
        //     "ProductTrackRule" => "required|string",
        //     "trackRule" => "required|string",
        //     "ProviderTrack" => "required|string",
        //     "ProductTrack" => "required|string",
        //     "track" => "required|string",
        // ]);
        $this->routeName = $formDatas["routeName"] ?? "TSNI - Itinéraire généré";
		$this->latStart = $formDatas["latStart"] ?? 48.750000;
		$this->lonStart = $formDatas["lonStart"] ?? 2.300000;
		$this->latTop = $formDatas["latTop"] ?? 48.800000;
		$this->lonLeft = $formDatas["lonLeft"] ?? 2.250000;
		$this->latBottom = $formDatas["latBottom"] ?? 48.700000;
		$this->lonRight = $formDatas["lonRight"] ?? 2.350000;
		$this->selectedLines = $formDatas["selectedLines"] ?? [];
		$this->selectedOutsideLines = $formDatas["selectedOutsideLines"] ?? [];
		$this->trackRuleBlueprint = [
			"provider" => $formDatas["ProviderTrackRule"] ?? "DTG",
			"product" => $formDatas["ProductTrackRule"] ?? "Academy",
			"blueprint" => $formDatas["trackRule"] ?? "RailNetwork\\TrackRule\\TSA_TrackRuleDE.xml",
		];
		$this->trackBlueprint = [
			"provider" => $formDatas["ProviderTrack"] ?? "DTG",
			"product" => $formDatas["ProductTrack"] ?? "Academy",
			"blueprint" => $formDatas["track"] ?? "RailNetwork\\Track\\track01_conc.xml",
		];

        $this->segmentPrecedent = [];
        $this->rubans = [];
        $this->segments = [];
        $this->detailRubans = [];
        $this->tilesList = [];
        $this->trackTiles = [];
        $this->zoneNumber = ($this->lonStart < 0 ? 30 : ($this->lonStart < 6 ? 31 : 32));
        $this->trackData = [];

		// \/ DEBUG \/
		$segments = LineSegment::where('IDGAIA', 'd161f15c-4e53-11ea-98ff-014c64e0362d')
								->orWhere('IDGAIA', 'd161f2d0-4e53-11ea-98ff-014c64e0362d')
								->orWhere('IDGAIA', 'd161f448-4e53-11ea-98ff-014c64e0362d')
								->orWhere('IDGAIA', 'd161f5aa-4e53-11ea-98ff-014c64e0362d')
								->orWhere('IDGAIA', 'd161f71a-4e53-11ea-98ff-014c64e0362d')
								->orWhere('IDGAIA', 'd161f880-4e53-11ea-98ff-014c64e0362d')
								->orWhere('IDGAIA', 'd161f9fe-4e53-11ea-98ff-014c64e0362d')
								->orderBy('nomLigne')->orderBy('nomVoie')->orderBy('pkd')
								->get();
		// $segments = LineSegment::where('Xd', '>=', 2.429987)->where('Xd', '<=', 2.483556)
		// 						->where('Zd', '>=', 48.885161)->where('Zd', '<=', 48.909209)
		// 						->orderBy('nomLigne')->orderBy('nomVoie')->orderBy('pkd')
		//  						->get();
		$this->latStart = 48.367;
		$this->lonStart = -4.1598;
		$this->latTop = $this->latStart + 0.2;
		$this->lonLeft = $this->lonStart - 0.2;
		$this->latBottom = $this->latStart - 0.2;
		$this->lonRight = $this->lonStart + 0.2;
		// /\ DEBUG /\

        $this->routeProperties = [
            "GUIDs" => $this->generateGUID(),
            "name" => [
                "fr" => $this->routeName,
            ],
            "origin" => [
                "long" => $this->lonStart,
                "lat" => $this->latStart
            ],
            "mapOffset" => [
                "X" => round($this->convertGeodeticToUTM($this->lonStart, $this->latStart)["X"]),
                "Z" => round($this->convertGeodeticToUTM($this->lonStart, $this->latStart)["Z"])
            ],
            "zoneNumber" => $this->zoneNumber,
        ];


		foreach ($segments as $segment) {
			$rayonCourbeSegment = ($segment->sens !== "ALIGNEMENT" ? intval($segment->rayon) : -1);
			$coordUTMDepartSegment = $this->convertGeodeticToUTM(floatval($segment->Xd), floatval($segment->Zd));
			$coordUTMFinSegment = $this->convertGeodeticToUTM(floatval($segment->Xf), floatval($segment->Zf));
			$segDepXRel = $coordUTMDepartSegment["X"] - $this->routeProperties["mapOffset"]["X"];
			$segDepZRel = $coordUTMDepartSegment["Z"] - $this->routeProperties["mapOffset"]["Z"];
			$segFinXRel = $coordUTMFinSegment["X"] - $this->routeProperties["mapOffset"]["X"];
			$segFinZRel = $coordUTMFinSegment["Z"] - $this->routeProperties["mapOffset"]["Z"];
			if ($segment->sens === "ALIGNEMENT")
				$longueurSegment = sqrt(abs($coordUTMFinSegment["X"] - $coordUTMDepartSegment["X"]) ** 2 + abs($coordUTMFinSegment["Z"] - $coordUTMDepartSegment["Z"]) ** 2);
			else
				$longueurSegment = abs($this->pkProcessing($segment->pkf) - $this->pkProcessing($segment->pkd));

			
			$segmentsBDD[] = [
				"GUIDs" => $this->generateGUID(),
				"numVoie" => $segment->nomVoie, 
				"codeLigne" => $segment->codeLigne, 
				"nomLigne" => $segment->nomLigne, 
				"sensCourbe" => $segment->sens, 
				"rayonCourbe" => $rayonCourbeSegment, 
				"pkd" => $this->pkProcessing($segment->pkd), 
				"pkf" => $this->pkProcessing($segment->pkf), 
				"segDepX" => $segDepXRel, 
				"segDepZ" => $segDepZRel, 
				"segFinX" => $segFinXRel, 
				"segFinZ" => $segFinZRel, 
				"longueur" => $longueurSegment,
			];
		}

		$routeDirectory = "tsni/generatedRoutes/{$this->routeProperties["GUIDs"][2]}";
		Storage::makeDirectory($routeDirectory);
		Storage::makeDirectory("{$routeDirectory}/Networks");
		Storage::makeDirectory("{$routeDirectory}/Networks/Track Tiles");
		Storage::makeDirectory("{$routeDirectory}/RouteInformation");
		Storage::disk('local')->put(
			"{$routeDirectory}/RouteInformation/image.png",
			Storage::disk('public')->get("tsni/pictures/thumbnail.png")
		);

		
		// GENERATION FICHIER ROUTEPROPERTIES
		$xml = view('tsni.files.route_properties', ["route" => $this->routeProperties])->render();
		Storage::put("{$routeDirectory}/RouteProperties.xml", $xml);


		// GENERATION FICHIERS TRACK TILES
		foreach ($segmentsBDD as $key => $segment) {
			if ($key > 0 && $segment["sensCourbe"] !== "ALIGNEMENT") {
				$precedAngle = fmod(deg2rad($this->segmentPrecedent["angle"]), 2* pi());
				if ($precedAngle < 0)
					$precedAngle += 2*pi();
				$currAngle = fmod((new Complex($segment["segFinX"] - $segment["segDepX"], $segment["segFinZ"] - $segment["segDepZ"]))->arg(), 2* pi());
				if ($currAngle < 0)
					$currAngle += 2*pi();

				if ($currAngle - $precedAngle > 0.1)
					$segment["sensCourbe"] = "GAUCHE";
				else {
					if ($currAngle - $precedAngle < -0.1)
						$segment["sensCourbe"] = "DROITE";
					else
						Log::channel('tsni')->warning("Contrôle direction non concluant");
				}
			}

			$this->segmentPrecedent["GUIDs"] = null;

			if ($segment["sensCourbe"] === "ALIGNEMENT") {
				$angle = 0;

				if ($segment["longueur"] > 0)
					$angle = (($segment["segFinZ"] - $segment["segDepZ"]) >= 0 ? 1 : -1) * rad2deg(acos(($segment["segFinX"] - $segment["segDepX"]) / $segment["longueur"]));
				else
					Log::channel('tsni')->error("Division par 0 (longueur), segment: {segment}", ['segment' => $segment]);
			}
			else {

				$deltaAngle = 0;
				if ($segment["rayonCourbe"] > 0)
					$deltaAngle = rad2deg($segment["longueur"] / $segment["rayonCourbe"]);
				else 
					Log::channel('tsni')->error("Division par 0 (rayonCourbe), segment: {segment}", ['segment' => $segment]);

				$segtpX = $segment["segFinX"] - $segment["segDepX"];
				$segtpZ = $segment["segFinZ"] - $segment["segDepZ"];
				$zF = new Complex($segtpX, $segtpZ);
				$W = new Complex(sqrt($segment["rayonCourbe"]**2 - ($zF->abs() / 2) **2), 0);
				$zU = new Complex($segtpX / 2, $segtpZ / 2);

				if ($segment["sensCourbe"] === "GAUCHE")
					$this->S = 1;
				else
					$this->S = -1;

				if ($deltaAngle < 180)
					$T = 1;
				else
					$T = -1;

				$i_complex = new Complex(0, 1);
				$zC = $zU->add($zF->divide($zF->abs())->multiply($W)->multiply($i_complex)->multiply($this->S)->multiply($T));
				$zA = $zC->multiply($i_complex)->multiply(-$this->S);
				$aX = $zA->r;
				$aZ = $zA->i;
				$angle = rad2deg(($aZ >= 0 ? 1 : -1) * acos($aX / $zA->abs()));
			}

			if ($key > 0 && $segment["nomLigne"] === $this->segmentPrecedent["nomLigne"] && $segment["numVoie"] === $this->segmentPrecedent["numVoie"]) { /* Ignorer si premier segment */
				if ($this->segmentPrecedent["pkf"] !== $segment["pkd"])
					Log::channel('tsni')->error("Discontinuité : segment n°{key}, segDepX = {segDepX}, segDepZ = {segDepZ}", ['key' => $key, 'segDepX' => $segment["segDepX"], 'segDepZ' => $segment["segDepZ"]]);
				elseif (abs($angle - $this->segmentPrecedent["angle"]) >= 0.00001 && abs($angle - $this->segmentPrecedent["angle"]) <= 0.5)
					Log::channel('tsni')->error("Rupture angle : segment n°{key}, segDepX = {segDepX}, segDepZ = {segDepZ}", ['key' => $key, 'segDepX' => $segment["segDepX"], 'segDepZ' => $segment["segDepZ"]]);
			}

			// if (is_nan($angle))
			// Log::channel('tsni')->error("⚠ Angle à une valeur NaN. segment n° :key . :segment", ["key" => $key, "segment" => $segment])

			$tileCoord = $this->getTileCoordAndSegRelCoord($segment["segDepX"], $segment["segDepZ"]);
			$tileX = $tileCoord["tileX"];
			$tileZ = $tileCoord["tileZ"];
			$segment["segDepXRel"] = $segment["segDepX"] - 1024 * $tileX;
			$segment["segDepZRel"] = $segment["segDepZ"] - 1024 * $tileZ;

			if ($segment["sensCourbe"] !== "ALIGNEMENT") {
				$varDir = rad2deg($segment["longueur"] / $segment["rayonCourbe"]);
				$angleFin = $angle + $varDir * $this->S;
			} else {
				$angleFin = $angle;
			}

			if (isset($this->segmentPrecedent["GUIDs"])) {
				$rubanPrecedent = $this->rubans[count($this->rubans)-1];
				if ($rubanPrecedent["nomLigne"] === $segment["nomLigne"] && $rubanPrecedent["pkf"] === $segment["pkd"] 
					&& $rubanPrecedent["numVoie"] === $segment["numVoie"] && $rubanPrecedent["longueur"] + $segment["longueur"] < 10000 && $rubanPrecedent["angleFin"] >= $angle - 0.1 && $rubanPrecedent["angleFin"] <= $angle + 0.1) {
					$this->rubans[count($this->rubans)-1]["longueur"] += $segment["longueur"];
					$this->rubans[count($this->rubans)-1]["arrX"] = $segment["segFinX"];
					$this->rubans[count($this->rubans)-1]["arrZ"] = $segment["segFinZ"];
					$this->rubans[count($this->rubans)-1]["pkf"] = $segment["pkf"];
				}
			} else {
				$rubanId = $this->generateID();
				$rubanGUIDs = $this->generateGUID();
				$this->segmentPrecedent["GUIDs"] = $rubanGUIDs;
				$this->rubans[] = ["id" => $rubanId, "GUIDs" => $rubanGUIDs, "tileX" => $tileX, "coordRelX" => $segment["segDepXRel"], "tileZ" => $tileZ, "coordRelZ" => $segment["segDepZRel"], "longueur" => $segment["longueur"], 
					"arrX" => $segment["segFinX"], "arrZ" => $segment["segFinZ"], "angleFin" => $angleFin, "nomLigne" => $segment["nomLigne"], "pkd" => $segment["pkd"], "pkf" => $segment["pkf"], "numVoie" => $segment["numVoie"],
					"coordAbsX" => $segment["segDepX"], "coordAbsZ" => $segment["segDepZ"]];
				// Log::channel('tsni')->info("Ruban inséré : :ruban", ["ruban" => $this->rubans[count($this->rubans) - 1]])
			}

			$segment2 = ["rubanId" => $rubanId, "rubanGUIDs" => $rubanGUIDs, "sensCourbe" => $segment["sensCourbe"], "rayonCourbe" => $segment["rayonCourbe"], "segDepX" => $segment["segDepX"], "segDepZ" => $segment["segDepZ"], "longueur" => $segment["longueur"], "angle" => $angle, "angleFin" => $angleFin];

			$segmentDecoupes = $this->decoupeSegment($segment2);
			foreach ($segmentDecoupes as $key => $coupe) {
				Log::channel('tsni')->info("✂ Coupe : :coupe", ['coupe' => $coupe]);
				$tileX = $coupe["tileX"];
				$tileZ = $coupe["tileZ"];
				$segmentId = $coupe["id"];

				$existingTileIndex = -1;
				if (count($this->tilesList) > 0) {
					for ($i = 0; $i < count($this->tilesList); $i++) {
						$tile = $this->tilesList[$i];
						if ($tile["X"] === $tileX && $tile["Z"] === $tileZ) {
							$existingTileIndex = $i;
							break;
						}		
					}
				}
				if ($existingTileIndex >= 0)
					$this->tilesList[$existingTileIndex]["segmentsIds"][] = $segmentId;
				else
					$this->tilesList[] = ["X" => $tileX, "Z" => $tileZ, "segmentsIds" => [$segmentId]];

					$this->segments[] = $coupe;
			}

			$this->segmentPrecedent["angle"] = $angleFin;
			$this->segmentPrecedent["pkf"] = $segment["pkf"];
			$this->segmentPrecedent["nomLigne"] = $segment["nomLigne"];
			$this->segmentPrecedent["numVoie"] = $segment["numVoie"];
		}


		$trackTileTemplateData = [];
		foreach ($this->tilesList as $tile) {
			$tileSegments = array_filter($this->segments, function ($segment) use ($tile) {
				return in_array($segment["id"], $tile["segmentsIds"]);
			}); // Merci ChatGPT
			usort($tileSegments, function ($a, $b) use ($tile) {
				return array_search($a["id"], $tile["segmentsIds"]) - array_search($b["id"], $tile["segmentsIds"]);
			});	// Merci ChatGPT

			$segmentPrecedentRibbonId = null;
			foreach ($tileSegments as $key => $segment) {
				// Création d'un nouveau ruban dans la liste
				if ($segment["rubanId"] !== $segmentPrecedentRibbonId) {
					$trackTileTemplateData[] = [
						"id" => $this->generateID(), 
						"GUIDs" => $segment["rubanGUIDs"], 
						"sensCourbe" => $segment["sensCourbe"], 
						"rayonCourbe" => $segment["rayonCourbe"], 
						"curves" => []
					];
					$segmentPrecedentRibbonId = $segment["rubanId"];
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
						"cos" => cos(deg2rad($segment["angle"])),
						"sin" => sin(deg2rad($segment["angle"])),
					],
				];
			}

			foreach ($this->tilesList as $tile) {
				$trackTileTemplateDataCopy = $trackTileTemplateData;
				foreach ($trackTileTemplateData as $keyRuban => $ruban) {
					$nbSegmentRestant = 0;
					foreach ($ruban["curves"] as $keySegment => $segment) {
						if ($segment["tileX"] !== $tile["X"] || $segment["tileZ"] !== $tile["Z"])
							unset($trackTileTemplateDataCopy[$keyRuban]["curves"][$keySegment]);
						else
							$nbSegmentRestant++;
					}
					if ($nbSegmentRestant === 0) {
						unset($trackTileTemplateDataCopy[$keyRuban]);
					}
				}

				$xml = view('tsni.files.track_tile_template', ["rubans" => $trackTileTemplateDataCopy])->render();
				Storage::put("{$routeDirectory}/Networks/Track Tiles/".$this->newTrackTileFile($tile["X"], $tile["Z"]), $xml);
			}


			// GENERATION FICHIER TRACKS.BIN
			$regleVoie = "";
			$typeVoie = "";
			$vitessesPrimaire = [];
			$vitessesSecondaire = [];
			$electrifications = [];
			$qualitesVoie = [];
			$vitesseAuto = false;
			
			$rubansFormatred = [];

			$trackNetworkGUIDs = $this->generateGUID();

			foreach ($this->rubans as $key => $ruban) {
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
					"extentX" => $ribExtentX, "extentZ" => $ribExtentZ, "regleVoie" => $this->trackRuleBlueprint, "typeVoie" => $this->trackBlueprint, "vitesses" => $vitesses, "electrification" => $ribElec, "qualite" => $ribQual];
				$rubansFormatred[] = $ribbonProps;
			}

			$extremitesTraitees = [];
			foreach ($this->rubans as $key => $ruban) {
				$extremitesTraitees[$ruban["id"]] = [ "0" => false, "1" => false];
			}

			$nodesFormatted = [];
			foreach ($this->rubans as $key => $ruban) {
				// Log::channel('tsni')->info("Execution de la boucle foreach ribbon1");
				$ribPos0 = ["X" => $ruban["tileX"] * 1024 + $ruban["coordRelX"], "Z" => $ruban["tileZ"] * 1024 + $ruban["coordRelZ"]];
				$ribPos1 = ["X" => $ruban["arrX"], "Z" => $ruban["arrZ"]];

				for ($extremite = 0; $extremite <= 1; $extremite++) { 
					$nodeExtrems = [];
					if ($extremite === 0)
						$pos1 = $ribPos0;
					else
						$pos1 = $ribPos1;
					// Log::channel('tsni')->info("_Execution de la boucle foreach extremite1 (#:extremite)", ["extremite" => $extremite]);
					if ($extremitesTraitees[$ruban["id"]][$extremite] === false) {
						// Log::channel('tsni')->info("__Extremite non traitee. 💠Traitement extrémité :extremite du ruban :key", ["extremite" => $extremite, "key" => $key]);
						$nodeExtrems[] = ["rubanGUIDs" => $ruban["GUIDs"], "extremPos" => $extremite];
						$extremitesTraitees[$ruban["id"]][$extremite] = true;

						foreach ($this->rubans as $key2 => $ruban2) {
							// Log::channel('tsni')->info("___Execution de la boucle foreach ribbon2");
							$rib2Pos0 = ["X" => $ruban2["tileX"] * 1024 + $ruban2["coordRelX"], "Z" => $ruban2["tileZ"] * 1024 + $ruban2["coordRelZ"]];
							$rib2Pos1 = ["X" => $ruban2["arrX"], "Z" => $ruban2["arrZ"]];

							for ($extremite2 = 0; $extremite2 <= 1; $extremite2++) { 
								if ($extremite2 === 0)
									$pos2 = $rib2Pos0;
								else
									$pos2 = $rib2Pos1;
								// Log::channel('tsni')->info("____Execution de la boucle foreach extremite2 (#:extremite)", ["extremite" => $extremite2]);
								if ($extremitesTraitees[$ruban2["id"]][$extremite2] === false) {
									// Log::channel('tsni')->info("_____Extremite non traitee");
									if (sqrt(abs($pos1["X"] - $pos2["X"]) ** 2 + abs($pos1["Z"] - $pos2["Z"]) ** 2) <= 0.001) {
										// Log::channel('tsni')->info("______Distance < 0.001. 💠Traitement extrémité :extremite du ruban :key", ["extremite" => $extremite2, "key" => $key2]);
										$nodeExtrems[] = ["rubanGUIDs" => $ruban2["GUIDs"], "extremPos" => $extremite2];
										$extremitesTraitees[$ruban2["id"]][$extremite2] = true;
									} 
									// else {
									// 	Log::channel('tsni')->info("_____.. mais distance trop grande");
									// }
								} 
								// else {
								// 	Log::channel('tsni')->info("_____✅Extremite déjà traitee");
								// }
							}
						}
					} 
					// else {
					// 	Log::channel('tsni')->info("__✅Extremite déjà traitee");
					// }

					if (count($nodeExtrems) > 0) {
						$nodesFormatted[] = ["id" => $this->generateID(), "extremites" => $nodeExtrems];
					}
				}
			}

			$tracksTemplateData = [
				"trackNetwork" => [
					"id" => $this->generateID(),
					"GUIDs" => $this->generateGUID(),
					"rubans" => $rubansFormatred,
					"nodes" => $nodesFormatted,
				],
			];

			$xml = view('tsni.files.tracks_template', $tracksTemplateData)->render();
			Storage::put("{$routeDirectory}/Networks/Tracks.xml", $xml);
		}
    }

    private function decToHex($n): string {
        $hexa = array("a","b","c","d","e","f");
		if ($n >= 10 && $n <= 15) {
			$n = $hexa[$n-10];
		}
		return $n;
    }

    private function generateID($type = "dec", $length = 9): string {
        $randomID = "";
		switch ($type) {
			case 'devStr':
				for ($char = 1; $char < 37; $char++) {
					if (in_array($char, [9, 14, 19, 24]))
						$randomID .= "-";
					else {
						if ($char === 1)
							$randomID .= $this->decToHex(rand(1,15));
						else
							$randomID .= $this->decToHex(rand(0,15));
					}
				}
				break;
			case 'dec':
			default:
				for ($char = 1; $char <= $length; $char++) {
					if ($char === 1)
						$randomID .= rand(1,9);
					else
						$randomID .= rand(0,9);
				}
				break;
		}
		return $randomID;
    }

    private function generateGUID(): array {
		return [$this->generateID("dec", 19), $this->generateID("dec", 19), $this->generateID("devStr")];
	}

    private function convertGeodeticToUTM($long, $lat): array {
		$long = deg2rad($long);
		$lat = deg2rad($lat);
		$Long0 = 0;
		switch ($this->zoneNumber) {
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

	private function getTileCoordAndSegRelCoord($segDepX, $segDepZ): array {
		$tileX = floor($segDepX / 1024);
		$tileZ = floor($segDepZ / 1024);
		$segDepXRel = $segDepX - 1024 * $tileX;
		$segDepZRel = $segDepZ - 1024 * $tileZ;

		return ["tileX" => $tileX, "tileZ" => $tileZ, "segDepXRel" => $segDepXRel, "segDepZRel" => $segDepZRel];
	}

	private function pkProcessing($pk): int {
		$signe = substr($pk,3,1);
		if ($signe === "+") {
			$pk = str_replace("+", "", $pk);
			return intval($pk);
		} elseif ($signe === "-") {
			$pk = str_replace("-", "", $pk);
			return -(intval($pk));
		} else
            throw new ErrorException("Unexcepted pk value: " + print_r($pk));
	}

    private function decoupeSegment($segment): array {
		// Log::channel('tsni')->info("Variable segment : :segment", ["segment" => $segment]);
		$longueur = $segment["longueur"];
		$segDepX = $segment["segDepX"];
		$segDepZ = $segment["segDepZ"];
		$angle = $segment["angle"];
		$sensCourbe = $segment["sensCourbe"];
		$rayonCourbe = $segment["rayonCourbe"];
		$coupes = [];
		$coupePrecedente = [];

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
				$segDepX = $coupePrecedente["X"];
				$segDepZ = $coupePrecedente["Z"];
				$angle = $coupePrecedente["angle"];
			}

			$tileCoord = $this->getTileCoordAndSegRelCoord($segDepX, $segDepZ);
			$tileX = $tileCoord["tileX"];
			$tileZ = $tileCoord["tileZ"];
			$segDepXRel = $tileCoord["segDepXRel"];
			$segDepZRel = $tileCoord["segDepZRel"];

			$segmentId = $this->generateID();

			if ($sensCourbe !== "ALIGNEMENT") {
				$varDir = rad2deg($longueur / $segment["rayonCourbe"]);
				$coupePrecedente["angle"] = $angle + $varDir * $this->S;
				$coupePrecedente["X"] = $segDepX + (-cos(deg2rad($angle - 90 * $this->S)) + cos(deg2rad($coupePrecedente["angle"] - 90 * $this->S))) * $rayonCourbe;
				$coupePrecedente["Z"] = $segDepZ + (-sin(deg2rad($angle - 90 * $this->S)) + sin(deg2rad($coupePrecedente["angle"] - 90 * $this->S))) * $rayonCourbe;
			} else {
				$coupePrecedente["angle"] = $angle;
				$coupePrecedente["X"] = $segDepX + cos(deg2rad($angle)) * $longueur;
				$coupePrecedente["Z"] = $segDepZ + sin(deg2rad($angle)) * $longueur;
			}

			$coupes[] = ["id" => $segmentId, "rubanId" => $segment["rubanId"], "rubanGUIDs" => $segment["rubanGUIDs"], "sensCourbe" => $sensCourbe, "rayonCourbe" => $rayonCourbe, 
				"tileX" => $tileX, "segDepXRel" => $segDepXRel, "tileZ" => $tileZ, "segDepZRel" => $segDepZRel, "longueur" => $longueur, "angle" => $angle, "angleFin" => $segment["angleFin"], 
				"segDepXAbs" => $segDepX, "segDepZAbs" => $segDepZ ];
		}

		// Log::channel('tsni')->info("Valeurs retournées : :return", ["return" => $coupes]);
		return $coupes;
	}

    private function newTrackTileFile($X, $Z): string {
		$XPart = ($X >= 0 ? "+" : "-").str_pad(abs($X), 6, "0", STR_PAD_LEFT);
		$ZPart = ($Z >= 0 ? "+" : "-").str_pad(abs($Z), 6, "0", STR_PAD_LEFT);
		return "{$XPart}{$ZPart}.xml";
	}

	private function segmentContainsId($var): bool {
		return $var["id"];
	}

	/*private function isSurroundedByStraight($segment) {
		
		FONCTIONNEMENT A DEFINIR !

	}*/

	private function calculRaccordParabolique($vitesse, $rayon, Complex $Apos, Complex $Dpos, $Adir, $Ddir, $Ax, $Ay, $sens) {
		// 1 : Evaluer le dévers
		$d = 11.8 * ($vitesse**2 / $rayon); // ... - I
		// Controle résultat....


		// 2 : Evaluer le gauche dans le raccordement (vitesse d’inclinaison)
		$i = (180 / $vitesse) < 5 ? (180 / $vitesse) : (216 / $vitesse);

		// 3 : Calculer le point final par itération jusqu’à atteindre l’objectif
		do {
			$p = $d / (2 * $i);
			$Opos = (new Complex($p, $sens * ($p**2 / (6*$rayon) + $rayon)))->multiply(new Complex(cos($Adir), sin($Adir)))->add(new Complex($Ax, $Ay));
			$Bpos = (new Complex(2 * $p, $sens * ((2 * $p**2) / (3 * $rayon))))->multiply(new Complex(cos($Adir), sin($Adir)))->add(new Complex($Ax, $Ay));
			$R = $Bpos->subtract($Opos);
			$alpha = $R->arg() - (3 * pi()) / 2 * $sens - $Adir;
			$theta = $Ddir - $Adir;
			$beta = $theta - 2 * $alpha;
			$Cpos = $R->multiply(new Complex(cos($beta), sin($beta)))->add($Opos);
			$Cdir = $Adir + $alpha + $beta;
			$u = $Bpos->subtract($Apos);
			$alpha2 = $u->arg() - $Adir;
			$alpha3 = $alpha - $alpha2;
			$v = $Cpos->subtract($Bpos);
			$w = (new Complex(cos($Cdir + $alpha3), sin($Cdir + $alpha3)))->multiply($u->abs());
			$erreur = $Apos->add($u)->add($v)->add($w)->subtract($Dpos)->abs() * 1000;

			if ($erreur > 0)
				$d += $erreur * 1;
			else
				$d -= $erreur * 1;
		} while ($erreur > -1 && $erreur < 1);
	}
}

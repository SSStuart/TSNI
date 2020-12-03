<!DOCTYPE html>
<html lang="fr">
    <head>
        <title>TS Network Importer</title>
        <meta charset="UTF-8">
        <meta name="description" content="Un outil pour importer des réseaux ferroviaires dans Train Simulator">
        <meta name="author" content="DUBROMEL Rémy">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" href="pictures/logoTSNI.png">
        <link rel="stylesheet" type="text/css" href="styles.css?v=<?php echo rand(0,1000)?>">
        <script type="text/javascript"> (function() { var css = document.createElement('link'); css.href = 'https://use.fontawesome.com/releases/v5.1.0/css/all.css'; css.rel = 'stylesheet'; css.type = 'text/css'; document.getElementsByTagName('head')[0].appendChild(css); })(); </script>
        <!--<script src="https://kit.fontawesome.com/68225099a4.js" crossorigin="anonymous"></script>-->
    </head>

    <body>
        <!--CONNEXION MYSQL-->
            <?php
                $servername = "sql106.byethost31.com";
                $username = "b31_26395455";
                $password = "kdSbe8pHbQ4r";
                $dbname = "b31_26395455_TSNI";

                // Create connection
                $conn = new mysqli($servername, $username, $password, $dbname);

                /*// Check connection
                if ($conn->connect_error) {
                    die("Connection failed: " . $conn->connect_error);
                }
                echo "Connected successfully";*/
            ?>
        <!--FIN connexion MySQL-->
        <header>
            <a href="http://plein2sites.byethost31.com/TSNI" class="linkHome"><img src="pictures/logoTSNItgv.png" class="logo" alt="TSNI"> <span class="nameDetail">Train Simulator Network Importer | [ALPHA]</span></a>
        </header>

        <?php
            $log = '';
            #RECUPERATION DONNEES
                $selection =  $_GET["selectType"];
                $formrouteName = $_GET["routeName"];
                $latStart = floatval($_GET["latStart"]);
                $lonStart = floatval($_GET["lonStart"]);
                $fournisseurRegleVoie = $_GET["ProviderTrackRule"];
                $produitRegleVoie = $_GET["ProductTrackRule"];
                $regleVoie = $_GET["trackRule"];
                $fournisseurVoie = $_GET["ProviderTrack"];
                $produitVoie = $_GET["ProductTrack"];
                $voie = $_GET["track"];
                $qualiteVoie = $_GET["qualiteVoie"];
                $limiteVitessePrim = intval($_GET["limiteVitessePrim"]);
                $limiteVitesseSecon = intval($_GET["limiteVitesseSecon"]);
                $electrification = $_GET["electrification"];
                $direction = "either";
                if ($selection == "line") {
                    $formline = $_GET["lineSelected"];
                    $formline = explode(";", $formline);
                    $sql = "SELECT * FROM LinesNetworks WHERE ";
                    for ($line = 0; $line <count($formline); $line++) {
                        $sql .= "nomLigne='".iconv("UTF-8", "ISO-8859-1",$formline[$line])."'";
                        if ($line+1 < count($formline)) {
                            $sql .= " OR ";
                        }
                    }
                    if ($result=mysqli_query($conn,$sql)) {
                        $rowcount=mysqli_num_rows($result);
                    }
                } else if ($selection == "zone") {
                    $latTop = $_GET["latTop"];
                    $lonLeft = $_GET["lonLeft"];
                    $latBottom = $_GET["latBottom"];
                    $lonRight = $_GET["lonRight"];
                    $sql = "SELECT * FROM LinesNetworks WHERE (Xd>=".$lonLeft." AND Xd<=".$lonRight." AND Zd>=".$latBottom." AND Zd<=".$latTop.") OR (Xf>=".$lonLeft." AND Xf<=".$lonRight." AND Zf>=".$latBottom." AND Zf<=".$latTop.")";
                }
                

            $log .='=========[DATA]========='."\n".'Route name: '.$formrouteName."\n".'Line(s) selected: '.$formline."\n".'Latitude start: '.$latStart."\n".'Longitude start: '.$lonStart."\n".'Provider Trackrule: '.$fournisseurRegleVoie."\n".'Product Trackrule: '.$produitRegleVoie."\n".'Trackrule: '.$regleVoie."\n".'Provider Track: '.$fournisseurVoie."\n".'Product Track: '.$produitVoie."\n".'Track: '.$voie."\n".'Ride quality: '.$qualiteVoie."\n".'Primary speed limit: '.$limiteVitessePrim."\n".'Secondary speed limit: '.$limiteVitesseSecon."\n".'Electrification: '.$electrification."\n".'Nb row for line "'.$formline.'": '.$rowcount."\n";

            #DEF FUNCTIONS
                function decToHex($n) {
                    $hexa = array("A","B","C","D","E","F");
                    if ($n>=10) {
                        $n = $hexa[$n-10];
                    }
                    return $n;
                }

                function genID($n, $typeID) {
                    $randomID = "";
                    if ($typeID=="d") {
                        for ($char = 1; $char <= $n; $char++) {
                            $randomID .= rand(0,9);
                        }
                    } elseif ($typeID=="devstr") {
                        for ($char = 1; $char < 37; $char++) {
                            if ($char == 9 or $char == 14 or $char == 19 or $char == 24) {
                                $randomID .= "-";
                            } else {
                                $randomID .= decToHex(rand(0,15));
                            }
                        }
                    }
                    return $randomID;
                }

                function fileName($x, $z) {
                    $xPart = strval(abs($x));
                    for ($nbZero=1; $nbZero<=(6-strlen(abs($x))); $nbZero++) {
                        $xPart = "0" . $xPart;
                    }
                    if ($x >= 0) {
                        $xPart = "+" . $xPart;
                    } else {
                        $xPart = "-" . $xPart;
                    }
                    $zPart = strval(abs($z));
                    for ($nbZero=1; $nbZero<=(6-strlen(abs($z))); $nbZero++) {
                        $zPart = "0" . $zPart;
                    }
                    if ($z >= 0) {
                        $zPart = "+" . $zPart;
                    } else {
                        $zPart = "-" . $zPart;
                    }
                    $result = $xPart.$zPart;
                    return $result;
                }

                function destroyplus3000($pk) { #CONVERSION PK
                    $signe = substr($pk,3,1);
                    if ($signe=="+") {
                        $pk = str_replace("+", "", $pk);
                        return intval($pk);
                    } elseif ($signe=="-") {
                        $pk = str_replace("-", "", $pk);
                        return -(intval($pk));
                    }
                }

            #INITIALISATION VARIABLES
                $routeID = genID(0, "devstr");
                $nbSegments = $rowcount;
                $precedseg = array();
                $detailsRib = array();
                $content = '';
                $lenghtRibbons = array();
                $nbRibbons = 0;
                $ribbons = array();
                $height = 0;
                $coordChunkX = array();
                $coordChunkZ = array();
                $coordVoieDepartX = array();
                $coordVoieDepartZ = array();
                $coordVoieRelativeX = array();
                $coordVoieRelativeZ = array();
                $coordVoieFinX = array();
                $coordVoieFinZ = array();
                $nodeID = array();
                $pkdlist = array();
                $pkflist = array();
                $rayonCourbelist = array();
                $sensCourbelist = array();
                $segDepXlist = array();
                $segDepZlist = array();
                $segFinXlist = array();
                $segFinZlist = array();
                $tiles = array();

            $log .= 'Route ID: '.$routeID."\n";

            mkdir("generatedFiles/".$routeID);
            mkdir("generatedFiles/".$routeID."/Networks");
            mkdir("generatedFiles/".$routeID."/Networks/Track Tiles");
            mkdir("generatedFiles/".$routeID."/RouteInformation");
            copy("pictures/thumbnail.png","generatedFiles/".$routeID."/RouteInformation/image.png");
            #CREATION FICHIER ROUTEPROPERTIES
                $log .="\n".'=========[ROUTEPROPERTIES]========='."\n".'Generation...';

                $routePropreties = fopen("generatedFiles/".$routeID."/RouteProperties.xml","w");
                $content .= '<?xml version="1.0" encoding="utf-8"?>'."\n".'<cRouteProperties xmlns:d="http://www.kuju.com/TnT/2003/Delta" d:version="1.0" d:id="'.genID(9,"d").'">'."\n".'<ID>'."\n".'<cGUID>'."\n".'<UUID>'."\n".'<e d:type="sUInt64">'.genID(19,"d").'</e>'."\n".'<e d:type="sUInt64">'.genID(19,"d").'</e>'."\n".'</UUID>'."\n".'<DevString d:type="cDeltaString">'.$routeID.'</DevString>'."\n".'</cGUID>'."\n".'</ID>'."\n".'<DisplayName>'."\n".'<Localisation-cUserLocalisedString>'."\n".'<English d:type="cDeltaString">'.$formrouteName.'</English>'."\n".'<French d:type="cDeltaString">'.$formrouteName.'</French>'."\n".'<Italian d:type="cDeltaString"></Italian>'."\n".'<German d:type="cDeltaString"></German>'."\n".'<Spanish d:type="cDeltaString"></Spanish>'."\n".'<Dutch d:type="cDeltaString"></Dutch>'."\n".'<Polish d:type="cDeltaString"></Polish>'."\n".'<Russian d:type="cDeltaString"></Russian>'."\n".'<Other/>'."\n".'';
                $content .= '<Key d:type="cDeltaString">dced938e-eb65-4de4-bbe9-487311272dd6</Key>'."\n".'</Localisation-cUserLocalisedString>'."\n".'</DisplayName>'."\n".'<InfrastructureModified d:type="bool">1</InfrastructureModified>'."\n".'<BlueprintID>'."\n".'<iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'<BlueprintSetID>'."\n".'<iBlueprintLibrary-cBlueprintSetID>'."\n".'<Provider d:type="cDeltaString">Kuju</Provider>'."\n".'<Product d:type="cDeltaString">RailSimulator</Product>'."\n".'</iBlueprintLibrary-cBlueprintSetID>'."\n".'</BlueprintSetID>'."\n".'<BlueprintID d:type="cDeltaString">TemplateRoutes\Default.xml</BlueprintID>'."\n".'</iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'</BlueprintID>'."\n".'<Skies>'."\n".'<cRouteBlueprint-sSkies>'."\n".'<SpringSkyBlueprint>'."\n".'<iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'<BlueprintSetID>'."\n".'<iBlueprintLibrary-cBlueprintSetID>'."\n".'<Provider d:type="cDeltaString">Kuju</Provider>'."\n".'<Product d:type="cDeltaString">RailSimulatorCore</Product>'."\n".'</iBlueprintLibrary-cBlueprintSetID>'."\n".'</BlueprintSetID>'."\n".'<BlueprintID d:type="cDeltaString">TimeOfDay\Core_Spring.xml</BlueprintID>'."\n".'</iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'</SpringSkyBlueprint>'."\n".'<SummerSkyBlueprint>'."\n".'<iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'<BlueprintSetID>'."\n".'<iBlueprintLibrary-cBlueprintSetID>'."\n".'<Provider d:type="cDeltaString">Kuju</Provider>'."\n".'<Product d:type="cDeltaString">RailSimulatorCore</Product>'."\n".'</iBlueprintLibrary-cBlueprintSetID>'."\n".'</BlueprintSetID>'."\n".'<BlueprintID d:type="cDeltaString">TimeOfDay\Core_Summer.xml</BlueprintID>'."\n".'</iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'</SummerSkyBlueprint>'."\n".'<AutumnSkyBlueprint>'."\n".'<iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'<BlueprintSetID>'."\n".'<iBlueprintLibrary-cBlueprintSetID>'."\n".'<Provider d:type="cDeltaString">Kuju</Provider>'."\n".'<Product d:type="cDeltaString">RailSimulatorCore</Product>'."\n".'</iBlueprintLibrary-cBlueprintSetID>'."\n".'</BlueprintSetID>'."\n".'<BlueprintID d:type="cDeltaString">TimeOfDay\Core_Autumn.xml</BlueprintID>'."\n".'</iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'</AutumnSkyBlueprint>'."\n".'<WinterSkyBlueprint>'."\n".'<iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'<BlueprintSetID>'."\n".'<iBlueprintLibrary-cBlueprintSetID>'."\n".'<Provider d:type="cDeltaString">Kuju</Provider>'."\n".'<Product d:type="cDeltaString">RailSimulatorCore</Product>'."\n".'</iBlueprintLibrary-cBlueprintSetID>'."\n".'</BlueprintSetID>'."\n".'<BlueprintID d:type="cDeltaString">TimeOfDay\Core_Winter.xml</BlueprintID>'."\n".'</iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'</WinterSkyBlueprint>'."\n".'</cRouteBlueprint-sSkies>'."\n".'</Skies>'."\n".'<WeatherBlueprint>'."\n".'<iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'<BlueprintSetID>'."\n".'<iBlueprintLibrary-cBlueprintSetID>'."\n".'<Provider d:type="cDeltaString">Kuju</Provider>'."\n".'<Product d:type="cDeltaString">RailSimulatorCore</Product>'."\n".'</iBlueprintLibrary-cBlueprintSetID>'."\n".'</BlueprintSetID>'."\n".'<BlueprintID d:type="cDeltaString">Weather\Default.xml</BlueprintID>'."\n".'</iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'</WeatherBlueprint>'."\n".'<TerrainBlueprint>'."\n".'<iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'<BlueprintSetID>'."\n".'<iBlueprintLibrary-cBlueprintSetID>'."\n".'<Provider d:type="cDeltaString">Kuju</Provider>'."\n".'<Product d:type="cDeltaString">RailSimulator</Product>'."\n".'</iBlueprintLibrary-cBlueprintSetID>'."\n".'</BlueprintSetID>'."\n".'<BlueprintID d:type="cDeltaString">Environment\Terrain\Texturing.xml</BlueprintID>'."\n".'</iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'</TerrainBlueprint>'."\n".'<MapBlueprint>'."\n".'<iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'<BlueprintSetID>'."\n".'<iBlueprintLibrary-cBlueprintSetID>'."\n".'<Provider d:type="cDeltaString"></Provider>'."\n".'<Product d:type="cDeltaString"></Product>'."\n".'</iBlueprintLibrary-cBlueprintSetID>'."\n".'</BlueprintSetID>'."\n".'<BlueprintID d:type="cDeltaString"></BlueprintID>'."\n".'</iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'</MapBlueprint>'."\n".'<LockCounter d:type="sUInt32">1</LockCounter>'."\n".'<Locked d:type="bool">0</Locked>'."\n".'<MapProjection>'."\n".'<cMapProjectionOwner d:id="'.genID(8,"d").'">'."\n".'<MapProjection>'."\n".'<cUTMMapProjection d:id="'.genID(9,"d").'">'."\n".'<Origin>'."\n".'<sGeoPosition>'."\n".'<Lat d:type="sFloat64" d:alt_encoding="0000000000000000" d:precision="string">'.$latStart.'</Lat>'."\n".'<Long d:type="sFloat64" d:alt_encoding="0000000000000000" d:precision="string">'.$lonStart.'</Long>'."\n".'</sGeoPosition>'."\n".'</Origin>'."\n".'<MapOffset>'."\n".'<sMapCoords>'."\n".'<Easting d:type="sFloat64" d:alt_encoding="8310CB396A6214C1" d:precision="string">0</Easting>'."\n".'<Northing d:type="sFloat64" d:alt_encoding="0000000000000000" d:precision="string">0</Northing>'."\n".'</sMapCoords>'."\n".'</MapOffset>'."\n".'<ZoneNumber d:type="sInt32">31</ZoneNumber>'."\n".'<ZoneLetter d:type="cDeltaString">N</ZoneLetter>'."\n".'</cUTMMapProjection>'."\n".'</MapProjection>'."\n".'</cMapProjectionOwner>'."\n".'</MapProjection>'."\n".'<RBlueprintSetPreLoad>'."\n".'<iBlueprintLibrary-cBlueprintSetID d:id="'.genID(8,"d").'">'."\n".'<Provider d:type="cDeltaString"></Provider>'."\n".'<Product d:type="cDeltaString"></Product>'."\n".'</iBlueprintLibrary-cBlueprintSetID>'."\n".'</RBlueprintSetPreLoad>'."\n".'<AuthoredLanguage d:type="cDeltaString">fr</AuthoredLanguage>'."\n".'<Version d:type="sFloat32" d:alt_encoding="000000000000F03F" d:precision="string">1</Version>'."\n".'<TimeZone d:type="sFloat32" d:alt_encoding="0000000000000000" d:precision="string">0</TimeZone>'."\n".'<SummerTime d:type="sFloat32" d:alt_encoding="000000000020AC40" d:precision="string">3600</SummerTime>'."\n".'<HasTimeZoneSet d:type="bool">0</HasTimeZoneSet>'."\n".'<HasSpeedsigns d:type="bool">1</HasSpeedsigns>'."\n".'<WorkshopId d:type="sUInt64">0</WorkshopId>'."\n".'<WorkshopBy d:type="sUInt64">0</WorkshopBy>'."\n".'<WorkshopTags d:type="cDeltaString"></WorkshopTags>'."\n".'</cRouteProperties>';
                fwrite($routePropreties, $content);
                fclose($routePropreties);

                $log .='Generated'."\n";

            #CREATION FICHIERS TRACK TILES
                $log .="\n".'=========[TRACK TILES]========='."\n".'[1]Initialisation'."\n";

                $content = '';
                #RECUPERATION DONNEES
                $sql = "SELECT * FROM LinesNetworks WHERE nomLigne='".iconv("UTF-8", "ISO-8859-1",$formline)."'";
                $result = $conn->query($sql);

                if ($result->num_rows > 0) {
                // output data of each row
                    while($row = $result->fetch_assoc()) {
                        $pkdlist[] = $row["pkd"];
                        $pkflist[] = $row["pkf"];
                        $rayonCourbelist[] = $row["rayon"];
                        $sensCourbelist[] = $row["sens"];
                        $segDepXlist[] = 4313.4*pow(sin($row["Xd"]*pi()/6),0.84777);
                        $segDepZlist[] = 110766* $row["Zd"] - 1082.6;
                        $segFinXlist[] = 4313.4*pow(sin($row["Xf"]*pi()/6),0.84777);
                        $segFinZlist[] = 110766* $row["Zf"] - 1082.6;
                    }
                }

                $log .= ' PKD: '.print_r($pkdlist, true)."\n".' PKF: '.print_r($pkflist, true)."\n".' Curve radius: '.print_r($rayonCourbelist, true)."\n".' Curve direction: '.print_r($sensCourbelist, true)."\n".' Segment start X: '.print_r($segDepXlist, true)."\n".' Segment start Z: '.print_r($segDepZlist, true)."\n".' Segment end X: '.print_r($segFinXlist, true)."\n".' Segment end Z: '.print_r($segFinZlist, true)."\n";
                #FIN RECUP DONNEES
                $log .= "\n".'[2]Segments'."\n";

                for ($segment=0; $segment<$nbSegments; $segment++) {
                    $pkd = destroyplus3000($pkdlist[$segment]);
                    $pkf = destroyplus3000($pkflist[$segment]);
                    $rayonCourbe = intval($rayonCourbelist[$segment]);
                    $sensCourbe = $sensCourbelist[$segment];
                    $segDepX = floatval($segDepXlist[$segment]);
                    $segDepZ = floatval($segDepZlist[$segment]);
                    $segFinX = floatval($segFinXlist[$segment]);
                    $segFinZ = floatval($segFinZlist[$segment]);
                    $lenght = abs($pkf - $pkd);
                    
                    $X = floor(($segDepX-(4313.4*pow(sin($lonStart*pi()/6),0.84777)))/1024);
                    $Z = floor(($segDepZ-(110766* $latStart - 1082.6))/1024);

                    $log .= ' Segment '.$segment.': '."\n".'  PKD: '.$pkd."\n".'  PKF: '.$pkf."\n".'  Curve radius: '.$rayonCourbe."\n".'  Curve direction: '.$sensCourbe."\n".'  X: '.$X."\n".'  Z: '.$Z."\n".'  Segment start X: '.$segDepX."\n".'  Segment start Z: '.$segDepZ."\n".'  Segment end X: '.$segFinX."\n".'  Segment end Z: '.$segFinZ."\n".'  Lenght: '.$lenght."\n\n";
                    
                    /*if ($segment==0) {
                        $log .= '  Generation of the first ribbon IDs'."\n";

                        $ribbons[0] = genID(9,"d");
                        $ribbons[1] = genID(9,"d");
                        $ribbons[2] = genID(9,"devstr");
                    }*/
                    if ($rayonCourbe==0) {
                        $log .= '  Curve radius = 0'."\n";

                        $angletp=(180/pi())*acos(($segFinX-$segDepX)/$lenght);
                    } else {
                        $log .= '  Curve radius != 0'."\n";

                        $k = ($segDepX-$segFinX)/($segDepZ-$segFinZ);
                        $h = ($segFinX**2-$segDepX**2+$segFinZ**2-$segDepZ**2)/(2*($segFinZ-$segDepZ));
                        $m = $k**2+1;
                        $n = -2*$segDepX+2*$k*$h-2*$k*$segDepZ;
                        $p = $segDepX**2+$segDepZ**2-2*$h*$segDepZ+h**2-$rayonCourbe**2;
                        if ($sensCourbe == "GAUCHE") {
                            $log .= '  Curve direction = GAUCHE'."\n";

                            $centreX = (-$n+sqrt($n**2-4*$m*$p))/(2*$m);
                            $angletp = acos(($segDepX-$centreX)/$rayonCourbe)+90;
                        } else {
                            $log .= '  Curve direction != GAUCHE'."\n";

                            $centreX = (-$n-sqrt($n**2-4*$m*$p))/(2*$m);
                            $angletp = acos(($segDepX-$centreX)/$rayonCourbe)-90;
                        }
                    }
                    $angle = $angletp;
                    if ($lenght>500) {
                        $log .= '  Lenght > 500'."\n";

                        $coupes = floor($lenght/500);
                        $reste = ($lenght%500);
                        if ($reste==0) {
                            $coupes= $coupes-1;
                        }
                    } else {
                        $log .= '  Lenght <= 500'."\n";

                        $coupes = 0;
                        $reste = 0;
                    }
                    $log .= "\n".'  [2.1]Cuts'."\n";

                    for ($decoupe=0; $decoupe<=$coupes; $decoupe++) {
                        $log .= '   Cut: '.$decoupe."\n";

                        if ($coupes!=0) {
                            $log .= '    Nb cuts != 0'."\n";

                            $lenght = 500;
                            if ($decoupe==$coupes) {
                                $lenght = $reste;
                            }
                        }
                        if ($decoupe!=0) {
                            $log .= '    Cut != 0'."\n";

                            $precedseg[0] = $segDepX;
                            $precedseg[1] = $segDepZ;
                            $precedseg[2] = $angle;
                        }

                        $segDepX = ($segDepX%1024);
                        $segDepZ = ($segDepZ%1024);
                        $tileExist = 0;
                        if ($segment+$decoupe>0) {
                            $log .= '   Segment != 0'."\n";

                            if ($tiles[count($tiles)-2]==$X and $tiles[count($tiles)-1]==$Z) {
                                $log .= '   Tile exist(same as previous)'."\n";

                                $tileExist = 1;
                            } else {
                                for ($tile=0; $tile<(count($tiles)/2); $tile++) {
                                    if ($tiles[$tile*2]==$X and $tiles[$tile*2+1]==$Z) {
                                        $log .= '   Tile exist(same as tile '.($tile/2).')'."\n";

                                        $tileExist = 1;
                                    }
                                }
                            }
                        }
                        if ($tileExist==0) {
                            $log .= '   Tile does not exist'."\n";
                        }
                        $log .= '   Generation of the ribbon IDs'."\n";

                        $ribbons[] = genID(9,"d");
                        $ribbons[] = genID(9,"d");
                        $ribbons[] = genID(9,"devstr");

                        $detailsRib[] = $X;
                        $detailsRib[] = $segDepX;
                        $detailsRib[] = $Z;
                        $detailsRib[] = $segDepZ;
                        $detailsRib[] = $lenght;
                        $log .= "\n".'   [2.2]File writing'."\n";
                        if ($tileExist==0) {
                            $log .= '    New tile'."\n";

                            $tracksTile = fopen("generatedFiles/".$routeID."/Networks/Track Tiles/".fileName($X, $Z).".xml","a");
                            $content .= '<?xml version="1.0" encoding="utf-8"?>'."\n".'<cRecordSet xmlns:d="http://www.kuju.com/TnT/2003/Delta" d:version="1.0" d:id="'.genID(9,"d").'">'."\n".'<Record>'."\n".'<Network-cNetworkRibbonUnstreamed-cCurveContainerUnstreamed d:id="'.genID(9,"d").'">'."\n".'<RibbonID>'."\n".'<Network-cNetworkRibbon-cCurveContainer-cID>'."\n".'<RibbonID>'."\n".'<cGUID>'."\n".'<UUID>'."\n".'<e d:type="sUInt64">'.$ribbons[count($ribbons)-3].'</e>'."\n".'<e d:type="sUInt64">'.$ribbons[count($ribbons)-2].'</e>'."\n".'</UUID>'."\n".'<DevString d:type="cDeltaString">'.$ribbons[count($ribbons)-1].'</DevString>'."\n".'</cGUID>'."\n".'</RibbonID>'."\n".'<NetworkTypeID>'."\n".'<cGUID>'."\n".'<UUID>'."\n".'<e d:type="sUInt64">4902272991866588709</e>'."\n".'<e d:type="sUInt64">14017088955258244018</e>'."\n".'</UUID>'."\n".'<DevString d:type="cDeltaString">10330e25-5f50-4408-b20b-389b23b486c2</DevString>'."\n".'</cGUID>'."\n".'</NetworkTypeID>'."\n".'</Network-cNetworkRibbon-cCurveContainer-cID>'."\n".'</RibbonID>'."\n".'<Curve>'."\n";
                            if ($rayonCourbe==0) {
                                $log .= '      Straight line'."\n\n";
                                $content .= '<cCurveStraight d:id="'.genID(9,"d").'">'."\n".'<Length d:type="sFloat32" d:alt_encoding="00000060712B1D40" d:precision="string">'.$lenght.'</Length>'."\n".'<StartPos>'."\n".'<cFarVector2>'."\n".'<X>'."\n".'<cFarCoordinate>'."\n".'<RouteCoordinate>'."\n".'<cRouteCoordinate>'."\n".'<Distance d:type="sInt32">'.$X.'</Distance>'."\n".'</cRouteCoordinate>'."\n".'</RouteCoordinate>'."\n".'<TileCoordinate>'."\n".'<cTileCoordinate>'."\n".'<Distance d:type="sFloat32" d:alt_encoding="000000C0A0F55340" d:precision="string">'.$segDepX.'</Distance>'."\n".'</cTileCoordinate>'."\n".'</TileCoordinate>'."\n".'</cFarCoordinate>'."\n".'</X>'."\n".'<Z>'."\n".'<cFarCoordinate>'."\n".'<RouteCoordinate>'."\n".'<cRouteCoordinate>'."\n".'<Distance d:type="sInt32">'.$Z.'</Distance>'."\n".'</cRouteCoordinate>'."\n".'</RouteCoordinate>'."\n".'<TileCoordinate>'."\n".'<cTileCoordinate>'."\n".'<Distance d:type="sFloat32" d:alt_encoding="000000C0A0F55340" d:precision="string">'.$segDepZ.'</Distance>'."\n".'</cTileCoordinate>'."\n".'</TileCoordinate>'."\n".'</cFarCoordinate>'."\n".'</Z>'."\n".'</cFarVector2>'."\n".'</StartPos>'."\n".'<StartTangent d:numElements="2" d:elementType="sFloat32" d:precision="string">'.cos($angle)." ".sin($angle).'</StartTangent>'."\n".'</cCurveStraight>'."\n";
                            } else {
                                $log .= '     Curved line'."\n";
                                $content .= '<cCurveArc d:id="'.genID(9,"d").'">'."\n".'<Curvature d:type="sFloat32" d:alt_encoding="00000040DF70743F" d:precision="string">'.$rayonCourbe.'</Curvature>'."\n".'<Length d:type="sFloat32" d:alt_encoding="00000000A6A26240" d:precision="string">'.$lenght.'</Length>'."\n".'<StartPos>'."\n".'<cFarVector2>'."\n".'<X>'."\n".'<cFarCoordinate>'."\n".'<RouteCoordinate>'."\n".'<cRouteCoordinate>'."\n".'<Distance d:type="sInt32">'.$X.'</Distance>'."\n".'</cRouteCoordinate>'."\n".'</RouteCoordinate>'."\n".'<TileCoordinate>'."\n".'<cTileCoordinate>'."\n".'<Distance d:type="sFloat32" d:alt_encoding="000000C0A0F55340" d:precision="string">'.$segDepX.'</Distance>'."\n".'</cTileCoordinate>'."\n".'</TileCoordinate>'."\n".'</cFarCoordinate>'."\n".'</X>'."\n".'<Z>'."\n".'<cFarCoordinate>'."\n".'<RouteCoordinate>'."\n".'<cRouteCoordinate>'."\n".'<Distance d:type="sInt32">'.$Z.'</Distance>'."\n".'</cRouteCoordinate>'."\n".'</RouteCoordinate>'."\n".'<TileCoordinate>'."\n".'<cTileCoordinate>'."\n".'<Distance d:type="sFloat32" d:alt_encoding="000000C0A0F55340" d:precision="string">'.$segDepZ.'</Distance>'."\n".'</cTileCoordinate>'."\n".'</TileCoordinate>'."\n".'</cFarCoordinate>'."\n".'</Z>'."\n".'</cFarVector2>'."\n".'</StartPos>'."\n".'<StartTangent d:numElements="2" d:elementType="sFloat32" d:precision="string">'.cos($angle)." ".sin($angle).'</StartTangent>'."\n".'<CurveSign d:type="sFloat32" d:alt_encoding="000000000000F0BF" d:precision="string">';
                                if ($sensCourbe == "GAUCHE") {
                                    $log .= '      Turning left'."\n\n";
                                    $content .= "-1";
                                } else {
                                    $log .= '      Turning right'."\n\n";
                                    $content .= "1";
                                }
                                $content .= '</CurveSign>'."\n".'</cCurveArc>'."\n";
                            }
                            $content .= '</Curve>'."\n".'</Network-cNetworkRibbonUnstreamed-cCurveContainerUnstreamed>'."\n";
                            $tiles[] = $X;
                            $tiles[] = $Z;
                        }
                        if ($tileExist==1) {
                            $log .= '    Existing tile'."\n";

                            $tracksTile = fopen("generatedFiles/".$routeID."/Networks/Track Tiles/".fileName($X, $Z).".xml","a");
                            $content .= '<Network-cNetworkRibbonUnstreamed-cCurveContainerUnstreamed d:id="'.genID(9,"d").'">'."\n".'<RibbonID>'."\n".'<Network-cNetworkRibbon-cCurveContainer-cID>'."\n".'<RibbonID>'."\n".'<cGUID>'."\n".'<UUID>'."\n".'<e d:type="sUInt64">'.$ribbons[count($ribbons)-3].'</e>'."\n".'<e d:type="sUInt64">'.$ribbons[count($ribbons)-2].'</e>'."\n".'</UUID>'."\n".'<DevString d:type="cDeltaString">'.$ribbons[count($ribbons)-1].'</DevString>'."\n".'</cGUID>'."\n".'</RibbonID>'."\n".'<NetworkTypeID>'."\n".'<cGUID>'."\n".'<UUID>'."\n".'<e d:type="sUInt64">4902272991866588709</e>'."\n".'<e d:type="sUInt64">14017088955258244018</e>'."\n".'</UUID>'."\n".'<DevString d:type="cDeltaString">10330e25-5f50-4408-b20b-389b23b486c2</DevString>'."\n".'</cGUID>'."\n".'</NetworkTypeID>'."\n".'</Network-cNetworkRibbon-cCurveContainer-cID>'."\n".'</RibbonID>'."\n".'<Curve>'."\n";
                            if ($rayonCourbe==0) {
                                $log .= '     Straight line'."\n\n";
                                $content .= '<cCurveStraight d:id="'.genID(9,"d").'">'."\n".'<Length d:type="sFloat32" d:alt_encoding="00000060712B1D40" d:precision="string">'.$lenght.'</Length>'."\n".'<StartPos>'."\n".'<cFarVector2>'."\n".'<X>'."\n".'<cFarCoordinate>'."\n".'<RouteCoordinate>'."\n".'<cRouteCoordinate>'."\n".'<Distance d:type="sInt32">'.$X.'</Distance>'."\n".'</cRouteCoordinate>'."\n".'</RouteCoordinate>'."\n".'<TileCoordinate>'."\n".'<cTileCoordinate>'."\n".'<Distance d:type="sFloat32" d:alt_encoding="000000C0A0F55340" d:precision="string">'.$segDepX.'</Distance>'."\n".'</cTileCoordinate>'."\n".'</TileCoordinate>'."\n".'</cFarCoordinate>'."\n".'</X>'."\n".'<Z>'."\n".'<cFarCoordinate>'."\n".'<RouteCoordinate>'."\n".'<cRouteCoordinate>'."\n".'<Distance d:type="sInt32">'.$Z.'</Distance>'."\n".'</cRouteCoordinate>'."\n".'</RouteCoordinate>'."\n".'<TileCoordinate>'."\n".'<cTileCoordinate>'."\n".'<Distance d:type="sFloat32" d:alt_encoding="000000C0A0F55340" d:precision="string">'.$segDepZ.'</Distance>'."\n".'</cTileCoordinate>'."\n".'</TileCoordinate>'."\n".'</cFarCoordinate>'."\n".'</Z>'."\n".'</cFarVector2>'."\n".'</StartPos>'."\n".'<StartTangent d:numElements="2" d:elementType="sFloat32" d:precision="string">'.cos($angle)." ".sin($angle).'</StartTangent>'."\n".'</cCurveStraight>'."\n";
                            } else {
                                $log .= '     Curved line'."\n";
                                $content .= '<cCurveArc d:id="'.genID(9,"d").'">'."\n".'<Curvature d:type="sFloat32" d:alt_encoding="00000040DF70743F" d:precision="string">'.$rayonCourbe.'</Curvature>'."\n".'<Length d:type="sFloat32" d:alt_encoding="00000000A6A26240" d:precision="string">'.$lenght.'</Length>'."\n".'<StartPos>'."\n".'<cFarVector2>'."\n".'<X>'."\n".'<cFarCoordinate>'."\n".'<RouteCoordinate>'."\n".'<cRouteCoordinate>'."\n".'<Distance d:type="sInt32">'.$X.'</Distance>'."\n".'</cRouteCoordinate>'."\n".'</RouteCoordinate>'."\n".'<TileCoordinate>'."\n".'<cTileCoordinate>'."\n".'<Distance d:type="sFloat32" d:alt_encoding="000000C0A0F55340" d:precision="string">'.$segDepX.'</Distance>'."\n".'</cTileCoordinate>'."\n".'</TileCoordinate>'."\n".'</cFarCoordinate>'."\n".'</X>'."\n".'<Z>'."\n".'<cFarCoordinate>'."\n".'<RouteCoordinate>'."\n".'<cRouteCoordinate>'."\n".'<Distance d:type="sInt32">'.$Z.'</Distance>'."\n".'</cRouteCoordinate>'."\n".'</RouteCoordinate>'."\n".'<TileCoordinate>'."\n".'<cTileCoordinate>'."\n".'<Distance d:type="sFloat32" d:alt_encoding="000000C0A0F55340" d:precision="string">'.$segDepZ.'</Distance>'."\n".'</cTileCoordinate>'."\n".'</TileCoordinate>'."\n".'</cFarCoordinate>'."\n".'</Z>'."\n".'</cFarVector2>'."\n".'</StartPos>'."\n".'<StartTangent d:numElements="2" d:elementType="sFloat32" d:precision="string">'.cos($angle)." ".sin($angle).'</StartTangent>'."\n".'<CurveSign d:type="sFloat32" d:alt_encoding="000000000000F0BF" d:precision="string">'."\n";
                                if ($sensCourbe == "GAUCHE") {
                                    $log .= '      Turning left'."\n\n";
                                    $content .= "-1";
                                } else {
                                    $log .= '      Turning right'."\n\n";
                                    $content .= "1";
                                }
                                $content .= '</CurveSign>'."\n".'</cCurveArc>'."\n";
                            }
                            $content .= '</Curve>'."\n".'</Network-cNetworkRibbonUnstreamed-cCurveContainerUnstreamed>'."\n";
                        }
                        fwrite($tracksTile, $content);
                        fclose($tracksTile);
                        $content="";
                        
                        $precedseg[0]=$segDepX+1024*$X+(4313.4*pow(sin($lonStart*pi()/6),0.84777));
                        $precedseg[1]=$segDepZ+1024*$Z+(110766* $latStart - 1082.6);
                        $precedseg[2]=$angle;
                        if ($rayonCourbe != 0) {
                            if ($sensCourbe =="GAUCHE") {
                                $dir = 360*$lenght/(2*pi()*$rayonCourbe)+90;
                                $angletp = $precedseg[2]+(180-abs($dir-$precedseg[2]))/2;
                                $precedseg[2] = $precedseg[2] + $dir;
                                $precedseg[0] = $precedseg[0] + cos($angletp)*2*$rayonCourbe*sin($dir/2);
                                $precedseg[1] = $precedseg[1] + sin($angletp)*2*$rayonCourbe*sin($dir/2);
                            } else {
                                $dir = 360*$lenght/(2*pi()*$rayonCourbe)-90;
                                $angletp = $precedseg[2]-(180-abs($dir-$precedseg[2]))/2;
                                $precedseg[2] = $precedseg[2] - $dir;
                                $precedseg[0] = $precedseg[0] + cos($angletp)*2*$rayonCourbe*sin($dir/2);
                                $precedseg[1] = $precedseg[1] + sin($angletp)*2*$rayonCourbe*sin($dir/2);
                            }
                        } else {
                            $precedseg[0]=$precedseg[0]+cos($precedseg[2])*$lenght;
                            $precedseg[1]=$precedseg[1]+sin($precedseg[2])*$lenght;
                        }
                        $detailsRib[]=$precedseg[0];
                        $detailsRib[]=$precedseg[1];
                    }
                }
                $log .= "\n".'[3]Closing Files'."\n";

                for ($tile=0; $tile<(count($tiles)/2); $tile++) {
                    $X = $tiles[$tile*2];
                    $Z = $tiles[$tile*2+1];

                    $log .= ' Closing '.fileName($X, $Z).'.xml'."\n";

                    $tracksTile = fopen("generatedFiles/".$routeID."/Networks/Track Tiles/".fileName($X, $Z).".xml","a");
                    $content = '</Record>'."\n".'</cRecordSet>';
                    fwrite($tracksTile, $content);
                    fclose($tracksTile);
                }

            #TRANSFERT VARIABLES
                for ($ribbon=0; $ribbon<(count($ribbons)/3); $ribbon++) {
                    /*$ribbonsID[] = $ribbons[$ribbon];*/
                    $coordChunkX[] = $detailsRib[$ribbon*7];
                    $coordVoieDepartX[] = $detailsRib[$ribbon*7+1];
                    $coordChunkZ[] = $detailsRib[$ribbon*7+2];
                    $coordVoieDepartZ[] = $detailsRib[$ribbon*7+3];
                    $lenghtRibbons[] = $detailsRib[$ribbon*7+4];
                    $coordVoieRelativeX[] = $detailsRib[$ribbon*7+5]-($detailsRib[$ribbon*7]*1024+$detailsRib[$ribbon*7+1]);
                    $coordVoieRelativeZ[] = $detailsRib[$ribbon*7+6]-($detailsRib[$ribbon*7+2]*1024+$detailsRib[$ribbon*7+3]);
                }


            #CREATION FICHIER TRACKS.BIN
                $content = '';
                $tracksBin = fopen("generatedFiles/".$routeID."/Networks/tracks.xml","w");

                $content .= '<?xml version="1.0" encoding="utf-8"?>'."\n".'<cRecordSet xmlns:d="http://www.kuju.com/TnT/2003/Delta" d:version="1.0" d:id="' . genID(9,"d") . '">'."\n".'<Record>'."\n".'<Network-cTrackNetwork d:id="' . genID(9,"d") . '">'."\n".'<NetworkID>'."\n".'<cGUID>'."\n".'<UUID>'."\n".'<e d:type="sUInt64">' . genID(19,"d") . '</e>'."\n".'<e d:type="sUInt64">' . genID(19,"d") . '</e>'."\n".'</UUID>'."\n".'<DevString d:type="cDeltaString">' . genID(0,"devstr") . '</DevString>'."\n".'</cGUID>'."\n".'</NetworkID>'."\n".'<RibbonContainer>'."\n".'<Network-cRibbonContainerUnstreamed d:id="' . genID(9,"d") . '">'."\n".'<Ribbon>'."\n";

                #BOUCLE RIBBON
                    for ($ribbon=0; $ribbon<(count($ribbons)/3); $ribbon++) {
                        /*if ($ribbon == 0) {
                            $ribbonsID = array(genID(9, "d"));
                        } else {
                            $ribbonsID[] = genID(9, "d");
                        }
                            $ribbonsID[] = genID(9, "d");
                            $ribbonsID[] = genID(0, "devstr");*/
                        $content .= '<Network-cTrackRibbon d:id="' . genID(9,"d") . '">'."\n".'<_length d:type="sFloat32" d:alt_encoding="00000060712B1D40" d:precision="string">' . $lenghtRibbons[$ribbon] . '</_length>'."\n".'<RibbonID>'."\n".'<cGUID>'."\n".'<UUID>'."\n".'<e d:type="sUInt64">' . $ribbons[$ribbon*3] . '</e>'."\n".'<e d:type="sUInt64">' . $ribbons[$ribbon*3+1] . '</e>'."\n".'</UUID>'."\n".'<DevString d:type="cDeltaString">' . $ribbons[$ribbon*3+2] . '</DevString>'."\n".'</cGUID>'."\n".'</RibbonID>'."\n".'<Height>'."\n";
                        #EVENTUELLE BOUCLE HAUTEUR
                        $content .= '<Network-iRibbon-cHeight d:id="' . genID(9,"d") . '">'."\n".'<_position d:type="sFloat32" d:alt_encoding="0000000000000000" d:precision="string">' . '0' . '</_position>'."\n".'<_height d:type="sFloat32" d:alt_encoding="000000405333D33F" d:precision="string">' . $height . '</_height>'."\n".'<_manual d:type="bool">1</_manual>'."\n".'</Network-iRibbon-cHeight>'."\n";

                        $content .= '</Height>'."\n".'<RouteVector>'."\n".'<cRouteVector2>'."\n".'<X>'."\n".'<cRouteCoordinate>'."\n".'<Distance d:type="sInt32">' . $coordChunkX[$ribbon] . '</Distance>'."\n".'</cRouteCoordinate>'."\n".'</X>'."\n".'<Z>'."\n".'<cRouteCoordinate>'."\n".'<Distance d:type="sInt32">' . $coordChunkZ[$ribbon] . '</Distance>'."\n".'</cRouteCoordinate>'."\n".'</Z>'."\n".'</cRouteVector2>'."\n".'</RouteVector>'."\n".'<RBottomLeft d:numElements="2" d:elementType="sFloat32" d:precision="string">' . $coordVoieDepartX[$ribbon]  . ' ' . $coordVoieDepartZ[$ribbon] . '</RBottomLeft>'."\n".'<RExtents d:numElements="2" d:elementType="sFloat32" d:precision="string">' . $coordVoieRelativeX[$ribbon]  . ' ' . $coordVoieRelativeZ[$ribbon] . '</RExtents>'."\n".'<FixedPatternRef>'."\n".'<Network-cNetworkRibbon-sFixedPatternRef>'."\n".'<FixedPattern>'."\n".'<d:nil/>'."\n".'</FixedPattern>'."\n".'<FixedPatternRibbonIndex d:type="sInt32">-1</FixedPatternRibbonIndex>'."\n".'</Network-cNetworkRibbon-sFixedPatternRef>'."\n".'</FixedPatternRef>'."\n".'<LockCounterWhenModified d:type="sUInt32">1</LockCounterWhenModified>'."\n".'<Properties>'."\n".'<Network-cPropertyContainer d:id="' . genID(9,"d") . '">'."\n".'<Property>'."\n".'<Network-cTrackNetworkTrackRule d:id="' . genID(9,"d") . '">'."\n".'<_start d:type="sFloat32" d:alt_encoding="0000000000000000" d:precision="string">' . '0' . '</_start>'."\n".'<_end d:type="sFloat32" d:alt_encoding="00000060712B1D40" d:precision="string">' . $lenghtRibbons[$ribbon] . '</_end>'."\n".'<ScenarioOwned d:type="bool">0</ScenarioOwned>'."\n".'<Property>'."\n".'<Network-iTrackNetworkTrackRule-cPropertyValue>'."\n".'<TrackRule>'."\n".'<iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'<BlueprintSetID>'."\n".'<iBlueprintLibrary-cBlueprintSetID>'."\n".'<Provider d:type="cDeltaString">' . $fournisseurRegleVoie . '</Provider>'."\n".'<Product d:type="cDeltaString">' . $produitRegleVoie . '</Product>'."\n".'</iBlueprintLibrary-cBlueprintSetID>'."\n".'</BlueprintSetID>'."\n".'<BlueprintID d:type="cDeltaString">' . $regleVoie . '</BlueprintID>'."\n".'</iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'</TrackRule>'."\n".'</Network-iTrackNetworkTrackRule-cPropertyValue>'."\n".'</Property>'."\n".'</Network-cTrackNetworkTrackRule>'."\n".'<Network-cSectionGenericProperties d:id="' . genID(9,"d") . '">'."\n".'<_start d:type="sFloat32" d:alt_encoding="0000000000000000" d:precision="string">' . '0' . '</_start>'."\n".'<_end d:type="sFloat32" d:alt_encoding="00000060712B1D40" d:precision="string">' . $lenghtRibbons[$ribbon] . '</_end>'."\n".'<ScenarioOwned d:type="bool">0</ScenarioOwned>'."\n".'<BlueprintID>'."\n".'<iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'<BlueprintSetID>'."\n".'<iBlueprintLibrary-cBlueprintSetID>'."\n".'<Provider d:type="cDeltaString">' . $fournisseurVoie . '</Provider>'."\n".'<Product d:type="cDeltaString">' . $produitVoie . '</Product>'."\n".'</iBlueprintLibrary-cBlueprintSetID>'."\n".'</BlueprintSetID>'."\n".'<BlueprintID d:type="cDeltaString">' . $voie . '</BlueprintID>'."\n".'</iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'</BlueprintID>'."\n".'<SecondaryBlueprintID>'."\n".'<iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'<BlueprintSetID>'."\n".'<iBlueprintLibrary-cBlueprintSetID>'."\n".'<Provider d:type="cDeltaString"></Provider>'."\n".'<Product d:type="cDeltaString"></Product>'."\n".'</iBlueprintLibrary-cBlueprintSetID>'."\n".'</BlueprintSetID>'."\n".'<BlueprintID d:type="cDeltaString"></BlueprintID>'."\n".'</iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'</SecondaryBlueprintID>'."\n".'<ElectrificationBlueprintID>'."\n".'<iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'<BlueprintSetID>'."\n".'<iBlueprintLibrary-cBlueprintSetID>'."\n".'<Provider d:type="cDeltaString"></Provider>'."\n".'<Product d:type="cDeltaString"></Product>'."\n".'</iBlueprintLibrary-cBlueprintSetID>'."\n".'</BlueprintSetID>'."\n".'<BlueprintID d:type="cDeltaString"></BlueprintID>'."\n".'</iBlueprintLibrary-cAbsoluteBlueprintID>'."\n".'</ElectrificationBlueprintID>'."\n".'<LoftScaleFactor d:type="sFloat32" d:alt_encoding="000000000000F03F" d:precision="string">1</LoftScaleFactor>'."\n".'</Network-cSectionGenericProperties>'."\n".'<Network-cTrackNetworkRideQuality d:id="' . genID(9,"d") . '">'."\n".'<_start d:type="sFloat32" d:alt_encoding="0000000000000000" d:precision="string">' . '0' . '</_start>'."\n".'<_end d:type="sFloat32" d:alt_encoding="00000000A6A26240" d:precision="string">' . $lenghtRibbons[$ribbon] . '</_end>'."\n".'<ScenarioOwned d:type="bool">1</ScenarioOwned>'."\n".'' . '<Property>'."\n".'<Network-iTrackNetworkRideQuality-cPropertyValue>'."\n".'<LineUnevenness d:type="sFloat32" d:alt_encoding="0000000000004440" d:precision="string">' . $qualiteVoie . '</LineUnevenness>'."\n".'</Network-iTrackNetworkRideQuality-cPropertyValue>'."\n".'</Property>'."\n".'</Network-cTrackNetworkRideQuality>'."\n".'<Network-cTrackNetworkSpeedLimit d:id="' . genID(9,"d") . '">'."\n".'<_start d:type="sFloat32" d:alt_encoding="0000000000000000" d:precision="string">' . '0' . '</_start>'."\n".'<_end d:type="sFloat32" d:alt_encoding="00000000A6A26240" d:precision="string">' . $lenghtRibbons[$ribbon] . '</_end>'."\n".'<ScenarioOwned d:type="bool">1</ScenarioOwned>'."\n".'<Property>'."\n".'<Network-iTrackNetworkSpeedLimit-cPropertyValue>'."\n".'<Primary d:type="sInt32">' . $limiteVitessePrim . '</Primary>'."\n".'<Secondary d:type="sInt32">' . $limiteVitesseSecon . '</Secondary>'."\n".'</Network-iTrackNetworkSpeedLimit-cPropertyValue>'."\n".'</Property>'."\n".'</Network-cTrackNetworkSpeedLimit>'."\n";
                        if ($electrification != "") {
                            $content .= '<Network-cTrackNetworkElectrification d:id="' . genID(9,"d") . '" >'."\n".'<_start d:type="sFloat32" d:alt_encoding="0000000000000000" d:precision="string">' . '0' . '</_start >'."\n".'<_end d:type="sFloat32" d:alt_encoding="00000000A6A26240" d:precision="string">' . $lenghtRibbons[$ribbon] . '</_end >'."\n".'<ScenarioOwned d:type="bool">0</ScenarioOwned >'."\n".'<Property >'."\n".'<Network-iTrackNetworkElectrification-cPropertyValue >'."\n".'<Electrification d:type="cDeltaString">' . $electrification . '</Electrification >'."\n".'</Network-iTrackNetworkElectrification-cPropertyValue >'."\n".'</Property >'."\n".'</Network-cTrackNetworkElectrification >'."\n";
                        }
                        $content .= '</Property >'."\n".'<SimpleValuePropertyEditFlag d:type="sUInt32">2</SimpleValuePropertyEditFlag >'."\n".'</Network-cPropertyContainer >'."\n".'</Properties >'."\n".'<ExplicitDirection >'."\n".'<Network-cDirection >'."\n".'<_dir d:type="cDeltaString">' . $direction . '</_dir >'."\n".'</Network-cDirection >'."\n".'</ExplicitDirection >'."\n".'<Superelevated d:type="bool">' . '0' . '</Superelevated >'."\n".'</Network-cTrackRibbon >'."\n";
                    }
                    $content .= '</Ribbon>'."\n".'<Node>';
                #PARTIE NODE
                    for ($track=0; $track<count($coordVoieDepartX); $track++) {
                        $coordVoieFinX[] = $coordVoieDepartX[$track] + $coordVoieRelativeX[$track];
                    }
                    for ($track=0; $track<count($coordVoieDepartZ); $track++) {
                        $coordVoieFinZ[] = $coordVoieDepartZ[$track] + $coordVoieRelativeZ[$track];
                    }
                    $coordVoieX = array_merge($coordVoieDepartX + $coordVoieFinX);
                    $coordVoieZ = array_merge($coordVoieDepartZ + $coordVoieFinZ);
                    $nodeX = array($coordVoieDepartX[0]);
                    $nodeZ = array($coordVoieDepartZ[0]);
                    $content .= '<Network-cTrackNode d:id="'.genID(9,"d").'">'."\n".'<Connection>'."\n".'<Network-cNetworkNode-sRConnection>'."\n".'<_id>'."\n".'<cGUID>'."\n".'<UUID>'."\n".'<e d:type="sUInt64">'. $ribbons[0].'</e>'."\n".'<e d:type="sUInt64">'. $ribbons[1]. '</e>'."\n".'</UUID>'."\n".'<DevString d:type="cDeltaString">'. $ribbons[2] .'</DevString>'."\n".'</cGUID>'."\n".'</_id>'."\n".'<_end>'."\n".'<cNormFloat>'."\n".'<Position d:type="sFloat32" d:alt_encoding="0000000000000000" d:precision="string">'.''.'</Position>'."\n".'</cNormFloat>'."\n".'</_end>'."\n".'</Network-cNetworkNode-sRConnection>'."\n".'</Connection>'."\n".'<FixedPatternRef>'."\n".'<Network-cNetworkNode-sFixedPatternRef>'."\n".'<FixedPattern>'."\n".'<d:nil/>'."\n".'</FixedPattern>'."\n".'<FixedPatternNodeIndex d:type="sInt32">-1</FixedPatternNodeIndex>'."\n".'</Network-cNetworkNode-sFixedPatternRef>'."\n".'</FixedPatternRef>'."\n".'<RouteVector>'."\n".'<cRouteVector2>'."\n".'<X>'."\n".'<cRouteCoordinate>'."\n".'<Distance d:type="sInt32">2147483647</Distance>'."\n".'</cRouteCoordinate>'."\n".'</X>'."\n".'<Z>'."\n".'<cRouteCoordinate>'."\n".'<Distance d:type="sInt32">2147483647</Distance>'."\n".'</cRouteCoordinate>'."\n".'</Z>'."\n".'</cRouteVector2>'."\n".'</RouteVector>'."\n".'<PatternRef>'."\n".'<Network-cTrackNode-sPatternRef>'."\n".'<Pattern>'."\n".'<d:nil/>'."\n".'</Pattern>'."\n".'<PatternNodeIndex d:type="sInt32">-1</PatternNodeIndex>'."\n".'</Network-cTrackNode-sPatternRef>'."\n".'</PatternRef>'."\n".'</Network-cTrackNode>'."\n";
                    #BOUCLE NODE
                        for ($endRibbon=0; $endRibbon<count($coordVoieX); $endRibbon++) {
                            $nodeID[] = genID(9,"d");
                            $coord = array($coordVoieX[$endRibbon],$coordVoieZ[$endRibbon]);
                            for ($node=0; $node<count($nodeX); $node++) {
                                if (abs($nodeX[$node]-$coord[0])<0.5 and abs($nodeZ[$node]-$coord[1])<0.5) {
                                    $nodeExist = 1;
                                }
                            }
                            if ($nodeExist==0) {
                                $nodeX[] = $coord[0];
                                $nodeZ[] = $coord[1];
                                $content .= '<Network-cTrackNode d:id="'.$nodeID[$endRibbon].'">'."\n".'<Connection>'."\n";
                                for ($endRibbon2=0; $endRibbon2<count($coordVoieX); $endRibbon2++) {
                                    if (abs($coordVoieX[$endRibbon2]-$coord[0])<0.5 and abs($coordVoieZ[$endRibbon2]-$coord[1])<0.5) {
                                        $content .= '<Network-cNetworkNode-sRConnection>'."\n".'<_id>'."\n".'<cGUID>'."\n".'<UUID>'."\n".'<e d:type="sUInt64">'. $ribbons[intdiv($endRibbon2,2)*3].'</e>'."\n".'<e d:type="sUInt64">'. $ribbons[intdiv($endRibbon2,2)*3+1]. '</e>'."\n".'</UUID>'."\n".'<DevString d:type="cDeltaString">'. $ribbons[intdiv($endRibbon2,2)*3+2];
                                        $content .= '</DevString>'."\n".'</cGUID>'."\n".'</_id>'."\n".'<_end>'."\n".'<cNormFloat>'."\n".'<Position d:type="sFloat32" d:alt_encoding="0000000000000000" d:precision="string">'.''.'</Position>'."\n".'</cNormFloat>'."\n".'</_end>'."\n".'</Network-cNetworkNode-sRConnection>'."\n";

                                    }
                                }
                                $content .= '</Connection>'."\n".'<FixedPatternRef>'."\n".'<Network-cNetworkNode-sFixedPatternRef>'."\n".'<FixedPattern>'."\n".'<d:nil/>'."\n".'</FixedPattern>'."\n".'<FixedPatternNodeIndex d:type="sInt32">-1</FixedPatternNodeIndex>'."\n".'</Network-cNetworkNode-sFixedPatternRef>'."\n".'</FixedPatternRef>'."\n".'<RouteVector>'."\n".'<cRouteVector2>'."\n".'<X>'."\n".'<cRouteCoordinate>'."\n".'<Distance d:type="sInt32">2147483647</Distance>'."\n".'</cRouteCoordinate>'."\n".'</X>'."\n".'<Z>'."\n".'<cRouteCoordinate>'."\n".'<Distance d:type="sInt32">2147483647</Distance>'."\n".'</cRouteCoordinate>'."\n".'</Z>'."\n".'</cRouteVector2>'."\n".'</RouteVector>'."\n".'<PatternRef>'."\n".'<Network-cTrackNode-sPatternRef>'."\n".'<Pattern>'."\n".'<d:nil/>'."\n".'</Pattern>'."\n".'<PatternNodeIndex d:type="sInt32">-1</PatternNodeIndex>'."\n".'</Network-cTrackNode-sPatternRef>'."\n".'</PatternRef>'."\n".'</Network-cTrackNode>'."\n";
                            }
                            $nodeExist = 0;
                        }
                        $content .= '</Node>'."\n".'</Network-cRibbonContainerUnstreamed>'."\n".'</RibbonContainer>'."\n".'<AreaMarkers/>'."\n".'</Network-cTrackNetwork>'."\n".'</Record>'."\n".'</cRecordSet>';

                fwrite($tracksBin, $content);
                fclose($tracksBin);

            #CREATION LOG
                $logFile = fopen("generatedFiles/".$routeID.".log","w");
                fwrite($logFile, $log);
                fclose($logFile);


            #CREATION DOSSIER ZIP
                // Get real path for our folder
                $rootPath = realpath("generatedFiles/".$routeID);

                // Initialize archive object
                $zip = new ZipArchive();
                $zip->open("generatedFiles/".$routeID.'.zip', ZipArchive::CREATE | ZipArchive::OVERWRITE);

                // Initialize empty "delete list"
                $filesToDelete = array();

                // Create recursive directory iterator
                $files = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($rootPath),
                    RecursiveIteratorIterator::LEAVES_ONLY
                );

                foreach ($files as $name => $file)
                {
                    // Skip directories (they would be added automatically)
                    if (!$file->isDir())
                    {
                        // Get real and relative path for current file
                        $filePath = $file->getRealPath();
                        $relativePath = substr($filePath, strlen($rootPath) + 1);

                        // Add current file to archive
                        $zip->addFile($filePath, $relativePath);

                        // Add current file to "delete list"
                        // delete it later cause ZipArchive create archive only after calling close function and ZipArchive lock files until archive created)
                        if ($file->getFilename() != 'TestFilesTwoDays')
                        {
                            $filesToDelete[] = $filePath;
                        }
                    }
                }
        ?>

        <section>
            <div class="dlSection">
                <p>
                    <?php echo $_GET["lineSelected"];?><br>
                    Votre itinéraire est prêt, vous pouvez le télécharger : 
                    <a class="dlLink" id="dlLink" href="generatedFiles/<?php echo $routeID ?>.zip" download><i class="far fa-file-archive"></i> Télécharger (.zip)</a>
                    <a class="dlLog" href="generatedFiles/<?php echo $routeID ?>.log" title="Peut-être utile en cas de problème" download>Télécharger le log</a>
                </p>
                <h1>Instructions d'installation</h1>
                <p>
                    Après avoir téléchargé le dossier, décompressez-le (<a href="https://www.7-zip.org" target="_blank" title="https://www.7-zip.org">7-zip</a>) puis ouvrez le programme SerzMaster.exe (qui devrait se trouver ici :<input class="pathRailworks" id="pathSerz" value="C:\Program Files (x86)\Steam\steamapps\common\RailWorks\SerzMaster.exe" readonly onclick="selectpath('pathSerz')" size="75"><i class="far fa-clipboard" title="Cliquez pour copier le chemin" onclick="selectpath('pathSerz')"></i>).<br>
                    Précisez le chemin de cet exécutable ainsi que le chemin du dossier de l'itinéraire suivi de <input class="pathRailworks" value="\Networks" readonly size="9">.<br>
                   <img class="serzmaster" src="pictures/serzMaster.jpg" alt="Vérifiez aussi que 'Recursive' est cochée et que la conversion soit de XML à BIN"><br>
                    Puis cliquez sur <button type="button" class="btnExample" onclick="window.alert('Sur SerzMaster c&#39;est mieux');">Process</button>. Lorsque l'opération est complété supprimez tous les fichiers .xml dans le dossier Networks ainsi que Networks\Track Tiles.
                </p>
                <p>
                    Pour finir placez l'itinéraire dans le répertoire suivant :<br>
                    <input class="pathRailworks" id="pathRailworks" value="C:\Program Files (x86)\Steam\steamapps\common\RailWorks\Content\Routes\" readonly onclick="selectpath('pathRailworks')" size="75"><i class="far fa-clipboard" title="Cliquez pour copier le chemin" onclick="selectpath('pathRailworks')"></i><br>
                    <details>
                        <summary><i class="fas fa-exclamation-triangle"></i> Si un itinéraire de ce nom existe déjà</summary>
                        <p>
                            Ne remplacez pas le dossier, changez le nom de l'itinéraire que vous venez de télécharger. Puis éditer le fichier <code>RouteProperties.xml</code> (à l'intérieur du dossier de l'itinéraire) en remplaçant la valeur de la balise <code>&lt;DevString&gt</code> par le nouveau nom de l'itinéraire.<br>
                            <img class="modifRoutesProt" src="pictures/modifDevstr.jpg" alt='Remplacer la valeur entre &lt;DevString d:type="cDeltaString"&gt et &lt;/DevString&gt'>
                        </p>
                    </details>
                </p>
            </div>
        </section>

        <footer>
            <dialog id="dialogCopied">Copié dans le presse-papier !</dialog>
            <p>
                0.1 | Contact : 
                <i class="fab fa-discord contactCatg"></i> SSStuart#0046
                <i class="fas fa-envelope contactCatg"></i> <input class="mail" id="mail" type="text" readonly onclick="selectmail()" onmouseout="deselectmail()" value="ssstuart.glunabouli@gmail.com" size="30"><br>
                TSNI n'est <strong>pas</strong> associé à Dovetail Games.
            </p>
        </footer>

        <script>
             function selectpath(path) {
                document.getElementById(path).select();
                document.getElementById(path).setSelectionRange(0, 99999);
                document.execCommand("copy");
                 if(navigator.userAgent.toLowerCase().indexOf('firefox') > -1){
                    window.alert("Copié dans le presse-papier !");
                } else {
                    document.getElementById("dialogCopied").open = true;
                    setTimeout(function() {document.getElementById("dialogCopied").open = false; }, 3000);
                }
            }
            
            window.onbeforeunload = function(event)
            {   
                document.getElementById('dlLink').href = "#";
            };

            function selectmail() {
                document.getElementById("mail").select();
                document.getElementById("mail").setSelectionRange(0, 99999);
                document.execCommand("copy");
                if(navigator.userAgent.toLowerCase().indexOf('firefox') > -1){
                    window.alert("Copié dans le presse-papier !");
                } else {
                    document.getElementById("dialogCopied").open = true;
                    setTimeout(function() {document.getElementById("dialogCopied").open = false; }, 3000);
                }
            }
            function deselectmail() {
                window.getSelection().removeAllRanges()
                document.getElementById("sc-copy").style.cssText = "opacity: 0;";
            }
        </script>
    </body>
</html>

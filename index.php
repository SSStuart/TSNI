<!DOCTYPE html>
<html lang="fr">
    <head>
        <title>TS Network Importer</title>
        <meta charset="UTF-8">
        <meta name="description" content="A tool to import railway networks into Train Simulator">
        <meta name="author" content="DUBROMEL Rémy">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
        <link rel="icon" href="pictures/favicon.png">
        <link rel="stylesheet" type="text/css" href="styles.css?v=<?php echo rand(0,1000)?>">
        <script src="https://kit.fontawesome.com/68225099a4.js" crossorigin="anonymous"></script>
        <script src='https://api.mapbox.com/mapbox-gl-js/v1.12.0/mapbox-gl.js'></script>
        <link href='https://api.mapbox.com/mapbox-gl-js/v1.12.0/mapbox-gl.css' rel='stylesheet' />
        <link rel="preconnect" href="https://fonts.gstatic.com">
        <link href="https://fonts.googleapis.com/css2?family=Raleway&family=Roboto+Mono&display=swap" rel="stylesheet">
    </head>

    <body onload="isPhone()">
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


                #SUPRESSION DES FICHIERS DE + DE 1 JOURS
                    //get all directory inside input directory using php RecursiveDirectoryIterator class.
                    $iterator = new RecursiveDirectoryIterator('generatedFiles/');
                    //get all files inside all directory itrations using php RecursiveIteratorIterator class.
                    foreach(new RecursiveIteratorIterator($iterator) as $file){
                        //check for files only.
                        if(is_file($file)){
                            //unlink file .
                            if (time() - filemtime($file) >= 60 * 60 * 24) { // 1 days
                                unlink($file);
                            }
                        }
                    }
            ?>
        <!--FIN connexion MySQL-->
        <div class="backP2S"><a href="http://plein2sites.byethost31.com/P2S/" title="Retour Plein de sites"><i class="fas fa-chevron-left"></i>P2S</a></div>
        <div id="mobileInfo" style="display:none;">
            <i class="fas fa-mobile-alt"></i><br>
            <p class="mobileInfoTexte">Ce site n'a pas été conçu pour être utilisé sur mobile.<br>(T'as TS installé sur ton téléphone ?)</p>
            <button type="button" class="continueMobile" onclick="disableMobile()">Continuer quand même</button>
        </div>
        <header>
            <a href="http://plein2sites.byethost31.com/TSNI" class="linkHome"><img src="pictures/logoTSNItgv.png" class="logo" alt="TSNI"> <span class="nameDetail">Train Simulator Network Importer | [ALPHA]</span></a>
        </header>
        <section>
            <div class="descTSNI">
                <h1>Qu'est-ce que c'est ?</h1>
                <p>
                    C'est un programme en ligne qui permet d'importer des lignes ferroviaires (françaises) dans le jeu <a href="https://live.dovetailgames.com/live/train-simulator" target="_blank" title="https://live.dovetailgames.com/live/train-simulator">Train Simulator</a> à l'aide d'une <a href="#aboutDB" title="https://www.data.gouv.fr/fr/datasets/courbe-des-voies/">base de données SNCF</a>.<br>
                    Cela permet d'obtenir un itinéraire qui contient (uniquement) les voies ferrées d'une ou plusieurs lignes que l'on choisit. Cela évite d'avoir à les placer manuellement dans l'éditeur du jeu.
                </p>
                <h1>Comment l'utiliser ?</h1>
                <p>
                    Pour utiliser le programme, il suffit de remplir le formulaire ci-dessous.<br>
                </p>
                <img src="pictures/steps.png" id="stepsPic" alt="">
                <table id="stepsTable"><tbody><tr>
                    <td><span class="descStep"><a href="#step1">1</a></span>Préciser les propriétés de l'itinéraire.</td>
                    <td id="middle"><span class="descStep">2</span>Choisir la zone d'intérêt : <span class="descStep subStep"><a href="#fieldLine">.A</a></span>une/plusieurs ligne(s) dans la liste<br> OU<br><span class="descStep subStep"><a href="#fieldZone">.B</a></span>une zone rectangulaire (coordonnées géodésiques).</td>
                    <td><span class="descStep"><a href="#step3">3</a></span>Préciser les propriétés de la voie <del>(pour chaque ligne si vous les avez sélectionnées ainsi)</del>.</td>
                </tr></tbody></table>
                <p>
                    <i class="fas fa-exclamation-circle"></i> Le modèle (template) d'itinéraire, la voie et la règle de voie doivent être présents dans vos fichiers locaux du jeu. (Créez un itinéraire et importez tous les produits présents dans vos fichiers locaux pour choisir et trouver le nom exact de la voie et de la règle de voie.)<br>
                </p>
                <p>
                    Lorsque tout est complété, exécutez le programme.
                </p>
                <p>
                    <em>Les développeurs ne sont pas responsables de quelconques problèmes pouvant survenir sur le jeu, liés à l'utilisation du présent programme.<br>
                    <strong>Une sauvegarde des fichiers du jeu est recommandée.</strong></em>
                </p>
                
            </div>
            <form action="createFile.php" id="form">
                <h1>Formulaire</h1>
                <span class="step" id="step1">1</span>
                <fieldset>
                    <legend><i class="fas fa-route"></i> Propriétés itinéraire</legend>
                    <div class="inputGrp"><label for="routeName"> Nom de l'itinéraire :</label>
                    <input type="text" id="routeName" name="routeName" size="25" required oninput="validationFn()"></div><span class="requiredInput" title="Obligatoire">*</span><br>
                    <div class="inputGrp"><label for="routeTemplate"> Modèle :</label>
                    <input type="text" id="routeTemplate" name="routeTemplate" size="25"></div><br>
                    <div class="inputGrp"><label for="latStart">Latitude départ :</label>
                    <input type="number" min="41.5" max="52" step="0.010000" id="latStart" name="latStart" size="10" pattern="((-?)\d+(,|\.)\d+)|((-?)\d+)" oninput="actualise();validationFn()" required>&deg;</div><span class="requiredInput" title="Obligatoire">*</span><div class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext" style="width:300px">La latitude de départ devrait être comprise dans la zone (si sélection par zone choisie)</span></div><br>
                    <div class="inputGrp"><label for="lonStart">Longitude départ :</label>
                    <input type="number" min="-5.5" max="8.5" step="0.010000" id="lonStart" name="lonStart" size="10" pattern="((-?)\d+(,|\.)\d+)|((-?)\d+)" oninput="actualise();validationFn()" required>&deg;</div><span class="requiredInput" title="Obligatoire">*</span><div class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext" style="width:350px">La longitude de départ devrait être comprise dans la zone (si sélection par zone choisie)</span></div>
                </fieldset>
                <span class="step" id="step2">2</span>
                <fieldset id="fieldLine">
                    <legend><input type="radio" id="selectLine" name="selectType" value="line" onchange="selectData()" required><label for="selectLine"><i class="fas fa-route"></i> Sélectionner une ligne</label></legend>
                    <details>
                        <summary>Obtention du nom de la ligne</summary>
                        <div id="detailsCont">
                            Zoomez sur une ligne sur la carte pour voir son nom
                            <div id='map'></div>
                            <script>
                                mapboxgl.accessToken = 'pk.eyJ1Ijoic3NzdHVhcnQiLCJhIjoiY2tpZzRnMjBhMHE5NjJybnhhcTV3b2RsZiJ9.8cKWpCIfNd1o2dNPQ80dZg';
                                var map = new mapboxgl.Map({
                                    container: 'map',
                                    style: 'mapbox://styles/ssstuart/ckig91dkl2uhp19ry46dzj0ce',
                                    center: [2.60376 , 47.122476],
                                    zoom: 4
                                });
                            </script>
                        </div>
                    </details>
                    <div class="inputGrp"><input list="listLines" id="line" placeholder="Rechercher une ligne" size="85">
                    <datalist id="listLines">
                        <?php
                            $sql = "SELECT DISTINCT nomLigne FROM LinesNetworks";
                            $result = $conn->query($sql);

                            if ($result->num_rows > 0) {
                                while($row = $result->fetch_assoc()) {
                                    echo '<option value="'.iconv("ISO-8859-1", "UTF-8", $row["nomLigne"]).'">';
                                }
                            }
                        ?>
                    </datalist>
                    <button type="button" class="clearLine" onclick="clearLine()"><i class="fas fa-times"></i></button></div>
                    <button class="btnAddLine" type="button" onclick="addLine();validationFn()">Ajouter la ligne</button><br>
                    <textarea id="lineSelected" name="lineSelected" rows="4" cols="75" placeholder="Lignes sélectionnées" readonly></textarea><span class="requiredInput" title="Obligatoire">*</span>
                </fieldset>
                <fieldset id="fieldZone">
                    <legend><input type="radio" id="selectZone" name="selectType" value="zone" onchange="selectData()" required><label for="selectZone"><i class="fas fa-vector-square"></i> Sélectionner une zone</label></legend>
                    <details>
                        <summary>Obtention des coordonnées</summary>
                        <p>Pour obtenir les coordonnées adaptées vous pouvez utiliser <a href="https://www.geoportail.gouv.fr/carte" target="_blank" title="https://www.geoportail.gouv.fr/carte">Géoportail</a>, cliquez sur "Cartes" en haut à gauche, sous "Données thématiques" cliquez sur "Territoires et Transports" > "Transports" > "Réseau ferroviaire".<br>
                        Cliquez sur "Outils", puis sur "Afficher les coordonnées" sous "Outils principaux", cliquez sur "Afficher" dans la partie "Quadrillage" pour afficher le quadrillage.
                        </p>
                    </details>
                    <p>
                        La latitude doit être comprise entre 41,5&deg;N et 52&deg;N, la longitude doit être comprise entre -5,5&deg;E et 8,5&deg;E. Le Point 1 doit être le point le plus en haut à gauche.
                    </p>
                    <table>
                        <tr>
                            <td class="colPoint1">
                                <label for="latTop" class="lblPoint1">Point 1</label>
                                <div class="inputGrp"><input type="number" min="41.5" max="52" step="0.000001" id="latTop" name="latTop" pattern="((-?)\d+(,|\.)\d+)|((-?)\d+)" placeholder="Latitude" oninput="actualise();validationFn()" value="52">&deg;</div><span class="requiredInput" title="Obligatoire">*</span> <span class="coordInfos">(41,5↔52)</span><div class="inputGrp"><input type="number" min="-5.5" max="8.5" step="0.000001" id="lonLeft" name="lonLeft" pattern="((-?)\d+(,|\.)\d+)|((-?)\d+)" placeholder="Longitude" oninput="actualise();validationFn()" value="-5.5">&deg;</div><span class="requiredInput" title="Obligatoire">*</span> <span class="coordInfos">(-5,5↔8,5)</span><br>
                            </td>
                            <td class="colMap">
                                Aperçu
                                <div class="mapFrance">
                                    <div class="zone" id="zone">
                                        <div class="point one">
                                        </div>
                                        <div class="point two" id="two">
                                        </div>
                                    </div>
                                </div>
                                <span id="erreurMap"></span>
                            </td>
                            <td class="colPoint2">
                                <label for="latBottom" class="lblPoint2">Point 2</label>
                                <div class="inputGrp"><input type="number" min="41.5" max="52" step="0.000001" id="latBottom" name="latBottom" pattern="((-?)\d+(,|\.)\d+)|((-?)\d+)" placeholder="Latitude" oninput="actualise();validationFn()" value="41.5">&deg;</div><span class="requiredInput" title="Obligatoire">*</span> <span class="coordInfos">(41,5↔52)</span><div class="inputGrp"><input type="number" min="-5.5" max="8.5" step="0.000001" id="lonRight" name="lonRight" pattern="((-?)\d+(,|\.)\d+)|((-?)\d+)" placeholder="Longitude" oninput="actualise();validationFn()" value="8.5">&deg;</div><span class="requiredInput" title="Obligatoire">*</span> <span class="coordInfos">(-5,5↔8,5)</span>
                            </td>
                        </tr>
                    </table>
                </fieldset>
                <span class="step" id="step3">3</span>
                <fieldset>
                    <legend><i class="fas fa-train"></i> Propriétés voie</legend>
                    <div class="inputSupGrp">
                        <span class="legendSupGrp">Règle de voie</span><br>
                        <div class="inputGrp"><label for="ProviderTrackRule"> Fournisseur :</label>
                        <input type="text" id="ProviderTrackRule" name="ProviderTrackRule" size="10" placeholder="Kuju"></div><br>
                        <div class="inputGrp"><label for="ProductTrackRule"> Produit :</label>
                        <input type="text" id="ProductTrackRule" name="ProductTrackRule" size="10" placeholder="RailsimulatorUS"></div><br>
                        <div class="inputGrp"><label for="trackRule"> Fichier :</label>
                        <input type="text" id="trackRule" name="trackRule" size="100" placeholder="RailNetwork\TrackRules\SanB_Bars_Mainline.xml"></div><br>
                    </div>
                    <div class="inputSupGrp">
                        <span class="legendSupGrp">Voie</span><br>
                        <div class="inputGrp"><label for="ProviderTrack"> Fournisseur :</label>
                        <input type="text" id="ProviderTrack" name="ProviderTrack" size="10" placeholder="Kuju"></div><br>
                        <div class="inputGrp"><label for="ProductTrack"> Produit :</label>
                        <input type="text" id="ProductTrack" name="ProductTrack" size="10" placeholder="RailsimulatorUS"></div><br>
                        <div class="inputGrp"><label for="track"> Fichier :</label>
                        <input type="text" id="track" name="track" size="100" placeholder="RailNetwork\Track\SanB_Bars_track01.xml"></div><br>
                    </div>
                    <div class="inputGrp"><label for="limiteVitessePrim"> Limite de vitesse primaire :</label>
                    <input type="text" id="limiteVitessePrim" name="limiteVitessePrim" size="3" pattern="^([1-9][0-9]+|[1-9])$" oninput="validationFn()" required>km/h</div><span class="requiredInput" title="Obligatoire">*</span><div class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext" style="width:300px">La limite de vitesse pour les trains passagers</span></div><br>
                    <div class="inputGrp"><label for="limiteVitesseSecon"> Limite de vitesse secondaire :</label>
                    <input type="text" id="limiteVitesseSecon" name="limiteVitesseSecon" size="3" oninput="validationFn()" required>km/h</div><span class="requiredInput" title="Obligatoire">*</span><div class="tooltip"><i class="fas fa-info-circle"></i><span class="tooltiptext" style="width:350px">La limite de vitesse pour les trains marchandises</span></div><br>
                    <div class="inputGrp"><input type="radio" id="elecNo" name="electrification" value="no" oninput="validationFn()" required>
                    <label for="elecNo">Non électrifié</label>
                    <input type="radio" id="elecCatenary" name="electrification" value="Caténaire" oninput="validationFn()" required>
                    <label for="elecCatenary">Caténaire</label>
                    <input type="radio" id="elecThirdRail" name="electrification" value="TroisRail" oninput="validationFn()" required>
                    <label for="elecThirdRail">Troisième rail</label></div><span class="requiredInput" title="Obligatoire">*</span><br>
                    <div class="inputGrp"><label for="qualiteVoie"> Qualité de la voie :</label>
                    <input type="text" id="qualiteVoie" name="qualiteVoie" size="3" pattern="(^100(\.0{1,2})?$)|(^([1-9]([0-9])?|0)(\.[0-9]{1,2})?$)" placeholder="100">%</div><br>
                </fieldset>
                <button type="button" class="submitBtn" id="fakeSubmit" onclick="manualSubmit()"><i class="fas fa-cogs"></i> Exécuter</button>
                <button type="reset" class="resetBtn" onclick=" return resetForm()"><i class="fas fa-trash"></i> Réinitialiser</button>
            </form>

            <div class="installation">
                <h2>Installation</h2>
                <p>
                    <i class="fas fa-info-circle"></i> Les instructions d'installation sont aussi disponibles sur la page de téléchargement de l'itinéraire.
                </p>
                <p>
                    Après avoir téléchargé le dossier, décompressez-le puis ouvrez le programme SerzMaster.exe (qui devrait se trouver ici :<input class="pathRailworks" id="pathSerz" value="C:\Program Files (x86)\Steam\steamapps\common\RailWorks\SerzMaster.exe" readonly onclick="copyInput(this.id)" size="75"><i class="far fa-clipboard" title="Cliquez pour copier le chemin" onclick="copyInput('pathSerz')"></i>).<br>
                    Précisez le chemin de cet exécutable ainsi que le chemin du <abbr title="Le dossier que vous venez de décompresser">dossier de l'itinéraire</abbr> suivi de <input class="pathRailworks" value="\Networks" readonly size="9">. Assurez-vous que <input type="checkbox" id="recursive" checked disabled><label for="recursive">Recursive</label> est coché et que la conversion soit de XML à BIN.<br>
                    <img class="serzmaster" src="pictures/serzMaster.jpg" alt="Vérifiez aussi que 'Recursive' est cochée et que la conversion soit de XML à BIN"><br>
                    Puis cliquez sur <button type="button" class="btnExample" onclick="window.alert('Sur SerzMaster c&#39;est mieux');">Process</button>. Lorsque l'opération est complété supprimez tous les fichiers .xml dans le dossier Networks ainsi que Networks\Track Tiles.
                </p>
                <p>
                    Pour finir placez l'itinéraire dans le répertoire suivant :<br>
                    <input class="pathRailworks" id="pathRailworks" value="C:\Program Files (x86)\Steam\steamapps\common\RailWorks\Content\Routes\" readonly onclick="copyInput(this.id)" size="75"><i class="far fa-clipboard" title="Cliquez pour copier le chemin" onclick="copyInput('pathRailworks')"></i><br>
                    <details>
                        <summary><i class="fas fa-exclamation-triangle"></i> Si un itinéraire de ce nom existe déjà</summary>
                        <p>
                            Ne remplacez pas le dossier, changez le nom de l'itinéraire que vous venez de télécharger. Puis éditer le fichier <code>RouteProperties.xml</code> (à l'intérieur du dossier de l'itinéraire) en remplaçant la valeur de la balise <code>&lt;DevString&gt</code> par le nouveau nom de l'itinéraire.<br>
                            <img class="modifRoutesProt" src="pictures/modifDevstr.jpg" alt='Remplacer la valeur entre &lt;DevString d:type="cDeltaString"&gt et &lt;/DevString&gt'>
                        </p>
                    </details>
                </p>
            </div>

            <div class="about">
                <h2>Outils utilisés</h2>
                <div id="aboutDB" data-udata-dataset="5959365ca3a7291dd09c8263"></div>
                <script data-udata="https://www.data.gouv.fr/" src="https://static.data.gouv.fr/static/oembed.js" async defer></script>
                <div id="aboutTracksbin">
                    <table><tbody><tr>
                        <td><img class="aboutLogo" src="https://www.codadeltreno.com/wp-content/themes/codadeltreno/assets/img/Logo.png" alt="coda del treno"></td>
                        <td>Le tutoriel <a href="https://www.codadeltreno.com/en/tutorials/tutorial-1-2/" target="_blank">Understanding tracks.bin</a> provenant du site <a href="https://www.codadeltreno.com/en/" target="_blank">coda del treno</a> a été très utiles pour comprendre la structure du fichier <input class="pathRailworks" id="trackbin" value="tracks.bin" readonly size="10"></td>
                    </tr></tbody></table>
                </div>
                <div id="aboutMap">
                    <table><tbody><tr>
                        <td><img class="aboutLogo" src="pictures/mapbox-logo.png" alt="Mapbox"></td>
                        <td>La carte permettant de trouver le nom d'une ligne a été créer avec <a href="https://www.mapbox.com" target="_blank">Mapbox</a>.</td>
                    </tr></tbody></table>
                </div>
            </div>
        </section>

        <div id="loadOverlay" hidden>
            <div id="cloud">
                <i class="fas fa-folder-open"></i>
                <img id="loading" src="pictures/loader.gif" alt="">
                <p>
                    Génération en cours...<br>Veuillez patienter
                </p>
            </div>
        </div>

        <footer>
            <dialog id="dialogCopied">Copié dans le presse-papier !</dialog>
            <p>
                0.1 | Contact : 
                <i class="fab fa-discord contactCatg"></i> <input class="discord" id="discord" type="text" readonly onclick="copyInput(this.id)" value="SSStuart#0046" size="13">
                <i class="fas fa-envelope contactCatg"></i> <input class="mail" id="mail" type="text" readonly onclick="copyInput(this.id)" value="ssstuart.glunabouli@gmail.com" size="30"><br>
                TSNI n'est <strong>pas</strong> associé à Dovetail Games.
            </p>
        </footer>
        <a href="bugReport.php" class="bugReport"><i class="fas fa-comment-alt"></i> Signaler un problème</a>

        <script>
            function isPhone() {
                if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|BB|PlayBook|IEMobile|Windows Phone|Kindle|Silk|Opera Mini/i.test(navigator.userAgent)) {
                    document.getElementById('mobileInfo').style.display ="block";
                    document.body.style.overflow = "hidden";
                }
            }
            function disableMobile() {
                document.getElementById('mobileInfo').style.display ="none";
                document.body.style.overflow = "visible";
            }

            function manualSubmit() {
                var validation = validationFn();
                var validity = validation[0];
                var invalidInputs = validation[1];
                if (validity) {
                    document.getElementById('form').submit();
                    document.getElementById('loadOverlay').hidden = false;
                } else {
                    window.alert('Veuillez vérifier que touts les champs nécessaires (*) soit remplie dans les parties suivantes :\n'+invalidInputs);
                    highlightInp = document.getElementsByTagName("input")
                    for (inputs = 0; inputs < highlightInp.length; inputs++) {
                        highlightInp[inputs].classList.add("highlight");
                    }
                    highlightTxtArea = document.getElementsByTagName("textarea")
                    for (inputs = 0; inputs < highlightTxtArea.length; inputs++) {
                        highlightTxtArea[inputs].classList.add("highlight");
                    }
                }
            }

            function resetForm() {
                return confirm("Êtes-vous sûr de vouloir tout supprimer ?");
            }

            function actualise() {
                //Variables
                var latStart = parseFloat(document.getElementById('latStart').value);
                var lonStart = parseFloat(document.getElementById('lonStart').value);

                var lonLeft = parseFloat(document.getElementById('lonLeft').value);
                var lonRight = parseFloat(document.getElementById('lonRight').value);
                var latTop = parseFloat(document.getElementById('latTop').value);
                var latBottom = parseFloat(document.getElementById('latBottom').value);

                var lonMax = parseFloat(document.getElementById('lonRight').max);
                var lonMin = parseFloat(document.getElementById('lonLeft').min);
                var latMax = parseFloat(document.getElementById('latTop').max);
                var latMin = parseFloat(document.getElementById('latBottom').min);

                //Vérifie que la coordonnée de départ est dans la zone si sélection par zone
                if (document.getElementById("selectZone").checked == true) {
                    if (latStart < latBottom || latStart > latTop) {
                        document.getElementById('latStart').style.backgroundColor = "darkred";
                    } else {
                        document.getElementById('latStart').style.backgroundColor = "";
                    }
                    if (lonStart < lonLeft || lonStart > lonRight) {
                        document.getElementById('lonStart').style.backgroundColor = "darkred";
                    } else {
                        document.getElementById('lonStart').style.backgroundColor = "";
                    }
                } else {
                    document.getElementById('latStart').style.backgroundColor = "";
                    document.getElementById('lonStart').style.backgroundColor = "";
                }
                
                //Vérification des valeur pour sélection par zone
                if (lonLeft < lonMin || lonLeft > lonMax) {
                    document.getElementById('lonLeft').style.backgroundColor = "darkred";
                } else {
                    document.getElementById('lonLeft').style.backgroundColor = "";
                }
                
                if (lonRight > lonMax || lonRight < lonMin) {
                    document.getElementById('lonRight').style.backgroundColor = "darkred";
                } else {
                    document.getElementById('lonRight').style.backgroundColor = "";
                }
                
                if (latTop > latMax || latTop < latMin) {
                    document.getElementById('latTop').style.backgroundColor = "darkred";
                } else {
                    document.getElementById('latTop').style.backgroundColor = "";
                }
                
                if (latBottom < latMin || latBottom > latMax) {
                    document.getElementById('latBottom').style.backgroundColor = "darkred";
                } else {
                    document.getElementById('latBottom').style.backgroundColor = "";
                }

                //Affichage de la zone
                if ((lonRight <= lonMax && lonLeft >= lonMin && latTop <= latMax && latBottom >= latMin) && (lonLeft<lonRight && latBottom<latTop)) {
                    var largeur = (Math.abs(lonLeft - lonRight)*30/14);
                    var hauteur = (Math.abs(latTop - latBottom)*30/10.5);
                    var decalLeft = (Math.abs(-5.5 - lonLeft)*30/14);
                    var decalTop = (Math.abs(52 - latTop)*30/10.5);

                    document.getElementById('zone').style.height = hauteur+"vh";
                    document.getElementById('zone').style.width = largeur+"vh";
                    document.getElementById('zone').style.marginLeft = decalLeft+"vh";
                    document.getElementById('zone').style.marginTop = decalTop+"vh";
                    document.getElementById('two').style.marginLeft = largeur+"vh";
                    document.getElementById('two').style.marginTop = hauteur+"vh";
                    document.getElementById('erreurMap').innerHTML  = "";
                } else {
                    document.getElementById('erreurMap').innerHTML ="ERREUR";
                }
            }

            function validationFn() {
                //Vérification du remplissage du formulaire
                //ETAPE 1
                var invalidInputs = [];
                var validityForm = true;
                var valiName = document.getElementById("routeName").validity.valid;
                var valiLatStart = document.getElementById("latStart").validity.valid;
                var valiLonStart = document.getElementById("lonStart").validity.valid;
                if (valiName && valiLatStart && valiLonStart) {
                    document.getElementById("step1").classList.add("complete");
                } else {
                    document.getElementById("step1").classList.remove("complete");
                    validityForm = false;
                    invalidInputs.push("\n1 - Propriétés itinéraire");
                }
                //ETAPE 2 Vérification qu'au moins une option est choisis
                if (document.getElementById("selectLine").checked === false && document.getElementById("selectZone").checked === false) {
                    validityForm = false;
                } else if (document.getElementById("selectLine").checked === true) {
                    //ETAPE 2.A
                    if (document.getElementById("lineSelected").value != "") {
                        document.getElementById("step2").classList.add("complete");
                    } else {
                        document.getElementById("step2").classList.remove("complete");
                        validityForm = false;
                        invalidInputs.push("\n2A- Sélectionner une ligne");
                    }
                } else if (document.getElementById("selectZone").checked === true) {
                    //ETAPE 2.B
                    var valiLatTop = document.getElementById("latTop").validity.valid;
                    var valiLonLeft = document.getElementById("lonLeft").validity.valid;
                    var valiLatBottom = document.getElementById("latBottom").validity.valid;
                    var valiLonRight = document.getElementById("lonRight").validity.valid;
                    if (valiLatTop && valiLonLeft && valiLatBottom && valiLonRight) {
                        document.getElementById("step2").classList.add("complete");
                    } else {
                        document.getElementById("step2").classList.remove("complete");
                        validityForm = false;
                        invalidInputs.push("\n2B- Sélectionner une zone");
                    }
                }
                //ETAPE 3
                var validLimitVitessPrim = document.getElementById("limiteVitessePrim").validity.valid;
                var validLimitVitessSecon = document.getElementById("limiteVitesseSecon").validity.valid;
                var validElecNo = document.getElementById("elecNo").validity.valid;
                var validElecCat = document.getElementById("elecCatenary").validity.valid;
                var validElecThird = document.getElementById("elecThirdRail").validity.valid;
                if (validLimitVitessPrim && validLimitVitessSecon && validElecNo && validElecCat && validElecThird) {
                    document.getElementById("step3").classList.add("complete");
                } else {
                    document.getElementById("step3").classList.remove("complete");
                    validityForm = false;
                    invalidInputs.push("\n3 - Propriétés voie");
                }
                return [validityForm, invalidInputs];
            }

            function selectData() {
                if (document.getElementById("selectLine").checked === true) {
                    document.getElementById("fieldZone").style = "height: 10px;padding: 0;overflow: hidden";
                    document.getElementById("zone").style = "display: none";
                    document.getElementById("fieldLine").style = "height: auto;padding: revert;overflow: hidden";
                    document.getElementById("step2").style.top = "3vh";
                    document.getElementById("step2").innerHTML = "2.A";
                    document.getElementById('lineSelected').required = true;
                } else if (document.getElementById("selectZone").checked === true) {
                    document.getElementById("fieldLine").style = "height: 10px;padding: 0;overflow: hidden";
                    document.getElementById("fieldZone").style = "height: auto;padding: revert;overflow: hidden";
                    document.getElementById("zone").style = "display: block";
                    document.getElementById("step2").style.top ="10vh";
                    document.getElementById("step2").innerHTML = "2.B";
                    document.getElementById('latTop').required = true;
                    document.getElementById('lonLeft').required = true;
                    document.getElementById('latBottom').required = true;
                    document.getElementById('lonRight').required = true;
                }
            }

            selectedLine = [];
            var nbLines = document.getElementById("listLines").options.length;
            function addLine() {
                e = document.getElementById("line");
                if (selectedLine.includes(e.value)== false) {
                    for (line=0; line<nbLines; line++) {
                        if (e.value == document.getElementById("listLines").options[line].value) {
                            selectedLine = selectedLine + e.value + ";";
                            document.getElementById("lineSelected").value = selectedLine.slice(0,-1);
                        }
                    }
                }
            }

            function clearLine() {
                document.getElementById("line").value = "";
            }

            function copyInput(path) {
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
        </script>
    </body>
</html>
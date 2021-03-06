<?php
/*
* Copyright 2001, 2019 Thomas Belliard, Laurent Delineau, Edouard Hue, Eric Lebrun, Stephane Boireau
*
* This file is part of GEPI.
*
* GEPI is free software; you can redistribute it and/or modify
* it under the terms of the GNU General Public License as published by
* the Free Software Foundation; either version 2 of the License, or
* (at your option) any later version.
*
* GEPI is distributed in the hope that it will be useful,
* but WITHOUT ANY WARRANTY; without even the implied warranty of
* MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
* GNU General Public License for more details.
*
* You should have received a copy of the GNU General Public License
* along with GEPI; if not, write to the Free Software
* Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

$variables_non_protegees = 'yes';

// Initialisations files
require_once("../lib/initialisations.inc.php");


// Resume session
$resultat_session = $session_gepi->security_check();
if ($resultat_session == 'c') {
	header("Location: ../utilisateurs/mon_compte.php?change_mdp=yes");
	die();
} else if ($resultat_session == '0') {
	header("Location: ../logout.php?auto=1");
	die();
}



$sql="SELECT 1=1 FROM droits WHERE id='/mod_epreuve_blanche/saisie_notes.php';";
$test=mysqli_query($GLOBALS["mysqli"], $sql);
if(mysqli_num_rows($test)==0) {
$sql="INSERT INTO droits SET id='/mod_epreuve_blanche/saisie_notes.php',
administrateur='V',
professeur='V',
cpe='F',
scolarite='V',
eleve='F',
responsable='F',
secours='F',
autre='F',
description='Epreuve blanche: Saisie des notes',
statut='';";
$insert=mysqli_query($GLOBALS["mysqli"], $sql);
}

//======================================================================================
// Section checkAccess() à décommenter en prenant soin d'ajouter le droit correspondant:
if (!checkAccess()) {
	header("Location: ../logout.php?auto=1");
	die();
}
//======================================================================================

$id_epreuve=isset($_POST['id_epreuve']) ? $_POST['id_epreuve'] : (isset($_GET['id_epreuve']) ? $_GET['id_epreuve'] : NULL);
$mode=isset($_POST['mode']) ? $_POST['mode'] : (isset($_GET['mode']) ? $_GET['mode'] : NULL);
$en_tete=isset($_POST['en_tete']) ? $_POST['en_tete'] : NULL;

if(isset($_POST['saisie_notes'])) {
	check_token();

	$sql="SELECT * FROM eb_epreuves WHERE id='$id_epreuve';";
	$res=mysqli_query($GLOBALS["mysqli"], $sql);
	if(mysqli_num_rows($res)==0) {
		$msg="L'épreuve choisie (<i>$id_epreuve</i>) n'existe pas.\n";
	}
	else {
		$lig=mysqli_fetch_object($res);
		$etat=$lig->etat;
		$note_sur=$lig->note_sur;
	
		if($etat!='clos') {
		
			$n_anonymat=isset($_POST['n_anonymat']) ? $_POST['n_anonymat'] : (isset($_GET['n_anonymat']) ? $_GET['n_anonymat'] : array());
			$note=isset($_POST['note']) ? $_POST['note'] : (isset($_GET['note']) ? $_GET['note'] : array());
		
			$msg="";
		
			for($i=0;$i<count($n_anonymat);$i++) {
				$saisie="y";
				if($_SESSION['statut']=='professeur') {
					$sql="SELECT 1=1 FROM eb_copies WHERE id_epreuve='$id_epreuve' AND login_prof='".$_SESSION['login']."' AND n_anonymat='$n_anonymat[$i]';";
					$test=mysqli_query($GLOBALS["mysqli"], $sql);
		
					if(mysqli_num_rows($test)==0) {
						$saisie="n";
						// AJOUTER UNE ALERTE INTRUSION
					}
				}
		
				if($saisie=="y") {
					$elev_statut='';
					if(($note[$i]=='disp')){
						$elev_note='0';
						$elev_statut='disp';
					}
					elseif(($note[$i]=='abs')){
						$elev_note='0';
						$elev_statut='abs';
					}
					elseif(($note[$i]=='-')){
						$elev_note='0';
						$elev_statut='-';
					}
					elseif(preg_match("/^[0-9\.\,]{1,}$/",$note[$i])) {
						$elev_note=str_replace(",", ".", "$note[$i]");
						if(($elev_note<0)||($elev_note>$note_sur)){
							$elev_note='';
							$elev_statut='';
						}
					}
					else{
						$elev_note='';
						//$elev_statut='';
						$elev_statut='v';
					}
					if(($elev_note!='')or($elev_statut!='')){
						$sql="UPDATE eb_copies SET note='$elev_note', statut='$elev_statut' WHERE id_epreuve='$id_epreuve' AND n_anonymat='$n_anonymat[$i]';";
						$res=mysqli_query($GLOBALS["mysqli"], $sql);
						if(!$res) {
							$msg.="Erreur: $sql<br />";
						}
					}
				}
			}
		
			if(($msg=='')&&(count($n_anonymat)>0)) {
				$msg="Enregistrement effectué (".strftime("%d/%m/%Y à %H:%M:%S").").";
			}
		}
		else {
			$msg="L'épreuve choisie (<i>$id_epreuve</i>) est close.\n";
		}
	}
}
elseif((isset($mode))&&($mode=='export_csv')) {
	check_token();

	$export="y";

	// Vérifier que l'accès est autorisé
	if($_SESSION['statut']=='professeur') {
		$sql="SELECT 1=1 FROM eb_copies WHERE id_epreuve='$id_epreuve' AND login_prof='".$_SESSION['login']."';";
		$test=mysqli_query($GLOBALS["mysqli"], $sql);
	
		if(mysqli_num_rows($test)==0) {
			$export="n";
			// AJOUTER UNE ALERTE INTRUSION
		}

		$sql="SELECT n_anonymat, note, statut FROM eb_copies WHERE id_epreuve='$id_epreuve' AND login_prof='".$_SESSION['login']."' ORDER BY n_anonymat;";
	}
	else {
		$sql="SELECT * FROM eb_copies WHERE id_epreuve='$id_epreuve' ORDER BY n_anonymat;";
	}

	if($export=="y") {
		$res=mysqli_query($GLOBALS["mysqli"], $sql);

		if($_SESSION['statut']=='professeur') {
			$csv="N_ANONYMAT;NOTE;\n";
			while($lig=mysqli_fetch_object($res)) {
				$note="";
				if($lig->statut=='v') {
					$note="";
				}
				elseif($lig->statut!='') {
					$note=$lig->statut;
				}
				else {
					$note=$lig->note;
				}
				$csv.=$lig->n_anonymat.";".$note.";\n";
			}
		}
		else {
			// Pouvoir choisir les champs?
			//$csv="N_ANONYMAT;LOGIN_ELE;NOTE;LOGIN_PROF;\n";
			$csv="N_ANONYMAT;LOGIN_ELE;NOM_PRENOM_ELE;CLASSE;NOTE;LOGIN_PROF;NOM_PROF\n";
			while($lig=mysqli_fetch_object($res)) {
				$note="";
				if($lig->statut=='v') {
					$note="";
				}
				elseif($lig->statut!='') {
					$note=$lig->statut;
				}
				else {
					$note=$lig->note;
				}
				$tmp_tab=get_class_from_ele_login($lig->login_ele);
				$csv.=$lig->n_anonymat.";".$lig->login_ele.";".get_nom_prenom_eleve($lig->login_ele).";".$tmp_tab['liste'].";".$note.";".$lig->login_prof.";".affiche_utilisateur($lig->login_prof,$tmp_tab['id0']).";\n";
			}
		}

		$nom_fic="export_saisie_notes_".$_SESSION['login']."_$id_epreuve.csv";
	
		$now = gmdate('D, d M Y H:i:s') . ' GMT';
		send_file_download_headers('text/x-csv',$nom_fic);
		//echo $csv;
		echo echo_csv_encoded($csv);
		die();
	}
}
// 20130406
elseif((isset($id_epreuve))&&(isset($mode))&&($mode=='upload_csv')&&(in_array($_SESSION['statut'], array('professeur', 'administrateur', 'scolarite')))) {
	check_token();

	$upload_autorise="y";
	if($_SESSION['statut']=='professeur') {
		$sql="SELECT * FROM eb_epreuves ee, eb_profs ep WHERE ee.etat!='clos' AND ee.id=ep.id_epreuve AND ep.login_prof='".$_SESSION['login']."' AND ep.id_epreuve='$id_epreuve';";
		//echo "$sql<br />\n";
		$res=mysqli_query($GLOBALS["mysqli"], $sql);
		if(mysqli_num_rows($res)==0) {
			$msg="Accès non autorisé à cette épreuve.<br />\n";
			$upload_autorise="n";
		}
		else {
			$lig=mysqli_fetch_object($res);
			$note_sur=$lig->note_sur;
		}
	}
	else {
		$sql="SELECT * FROM eb_epreuves ee WHERE ee.etat!='clos' AND ee.id='$id_epreuve';";
		//echo "$sql<br />\n";
		$res=mysqli_query($GLOBALS["mysqli"], $sql);
		if(mysqli_num_rows($res)==0) {
			$msg="Cette épreuve est close ou inexistante.<br />\n";
			$upload_autorise="n";
		}
		else {
			$lig=mysqli_fetch_object($res);
			$note_sur=$lig->note_sur;
		}
	}

	if($upload_autorise=="y") {
		$csv_file = isset($_FILES["csv_file"]) ? $_FILES["csv_file"] : NULL;
		if((!isset($csv_file))||($csv_file['tmp_name']=='')||($csv_file['error']!='0')) {
			$msg="Aucun fichier n'a été transmis.<br />";
		}
		elseif(!in_array($csv_file['type'], array('text/csv',
									'text/plain',
									'application/csv',
									'text/comma-separated-values',
									'application/excel',
									'application/vnd.ms-excel',
									'application/vnd.msexcel',
									'text/anytext',
									'application/octet-stream',
									'application/txt',
									'application/tsv'))) {
			$msg="Le fichier n'est pas un fichier CSV.<br />";
		}
		elseif($csv_file['tmp_name'] != "") {
			$fp = @fopen($csv_file['tmp_name'], "r");
			if(!$fp) {
				$msg="Impossible d'ouvrir le fichier CSV<br />";
			}
			else {
				$nb_reg=0;
				$msg="";
				$long_max=4000;
				while(!feof($fp)) {
					if (isset($en_tete)) {
						$data = fgetcsv ($fp, $long_max, ";");

						// Recherche des champs
						$tabchamps = array("N_ANONYMAT","NOTE");

						for($i=0;$i<sizeof($data);$i++) {
							for($j=0;$j<sizeof($tabchamps);$j++) {
								if($data[$i]==$tabchamps[$j]) {
									$indice_champ[$tabchamps[$j]]=$i;
								}
							}
						}

						/*
						if(!isset($indice_champ['N_ANONYMAT'])) {
						}
						*/

						unset($en_tete);
					}

					if(!isset($indice_champ['N_ANONYMAT'])) {
						$indice_champ['N_ANONYMAT']=0;
					}

					if(!isset($indice_champ['NOTE'])) {
						$indice_champ['NOTE']=1;
					}

					$data = fgetcsv ($fp, $long_max, ";");

					if((isset($data[$indice_champ['NOTE']]))&&($data[$indice_champ['N_ANONYMAT']]!='')&&($data[$indice_champ['NOTE']]!='')) {
						if($_SESSION['statut']=='professeur') {
							$sql="SELECT * FROM eb_copies WHERE id_epreuve='$id_epreuve' AND login_prof='".$_SESSION['login']."' AND n_anonymat='".$data[$indice_champ['N_ANONYMAT']]."';";
							$res=mysqli_query($GLOBALS["mysqli"], $sql);
							if(mysqli_num_rows($res)==0) {
								$msg.="Le numéro d'anonymat ".$data[$indice_champ['N_ANONYMAT']]." ne vous est pas attribué.<br />";
							}
							else {
								$note_courante=preg_replace("/,/", ".", $data[$indice_champ['NOTE']]);
								if((preg_match("/^[0-9\.\,]{1,}$/", $data[$indice_champ['NOTE']]))&&($note_courante>=0)&&($note_courante<=$note_sur)) {
									$sql="UPDATE eb_copies SET note='$note_courante', statut='' WHERE id_epreuve='$id_epreuve' AND login_prof='".$_SESSION['login']."' AND n_anonymat='".$data[$indice_champ['N_ANONYMAT']]."';";
									$update=mysqli_query($GLOBALS["mysqli"], $sql);
									if($update) {
										$nb_reg++;
									}
									else {
										$msg.="Erreur lors de l'enregistrement de la note ".$note_courante." pour le numéro d'anonymat ".$data[$indice_champ['N_ANONYMAT']].".<br />";
									}
								}
								elseif(($data[$indice_champ['NOTE']]=="abs")||($data[$indice_champ['NOTE']]=="disp")||($data[$indice_champ['NOTE']]=="-")) {
									$sql="UPDATE eb_copies SET note='0.0', statut='".$data[$indice_champ['NOTE']]."' WHERE id_epreuve='$id_epreuve' AND login_prof='".$_SESSION['login']."' AND n_anonymat='".$data[$indice_champ['N_ANONYMAT']]."';";
									$update=mysqli_query($GLOBALS["mysqli"], $sql);
									if($update) {
										$nb_reg++;
									}
									else {
										$msg.="Erreur lors de l'enregistrement de la note ".$note_courante." pour le numéro d'anonymat ".$data[$indice_champ['N_ANONYMAT']].".<br />";
									}
								}
								else {
									$msg.="La note ".$data[$indice_champ['NOTE']]." pour le numéro d'anonymat ".$data[$indice_champ['N_ANONYMAT']]." est invalide.<br />";
								}
							}
						}
						else {
							$sql="SELECT * FROM eb_copies WHERE id_epreuve='$id_epreuve' AND n_anonymat='".$data[$indice_champ['N_ANONYMAT']]."';";
							$res=mysqli_query($GLOBALS["mysqli"], $sql);
							if(mysqli_num_rows($res)==0) {
								$msg.="Le numéro d'anonymat ".$data[$indice_champ['N_ANONYMAT']]." n'est pas associé à un élève dans la table 'eb_copies'.<br />";
							}
							else {
								$note_courante=preg_replace("/,/", ".", $data[$indice_champ['NOTE']]);
								if((preg_match("/^[0-9\.\,]{1,}$/", $data[$indice_champ['NOTE']]))&&($note_courante>=0)&&($note_courante<=$note_sur)) {
									$sql="UPDATE eb_copies SET note='$note_courante', statut='' WHERE id_epreuve='$id_epreuve' AND n_anonymat='".$data[$indice_champ['N_ANONYMAT']]."';";
									$update=mysqli_query($GLOBALS["mysqli"], $sql);
									if($update) {
										$nb_reg++;
									}
									else {
										$msg.="Erreur lors de l'enregistrement de la note ".$note_courante." pour le numéro d'anonymat ".$data[$indice_champ['N_ANONYMAT']].".<br />";
									}
								}
								elseif(($data[$indice_champ['NOTE']]=="abs")||($data[$indice_champ['NOTE']]=="disp")||($data[$indice_champ['NOTE']]=="-")) {
									$sql="UPDATE eb_copies SET note='0.0', statut='".$data[$indice_champ['NOTE']]."' WHERE id_epreuve='$id_epreuve' AND n_anonymat='".$data[$indice_champ['N_ANONYMAT']]."';";
									$update=mysqli_query($GLOBALS["mysqli"], $sql);
									if($update) {
										$nb_reg++;
									}
									else {
										$msg.="Erreur lors de l'enregistrement de la note ".$note_courante." pour le numéro d'anonymat ".$data[$indice_champ['N_ANONYMAT']].".<br />";
									}
								}
								else {
									$msg.="La note ".$data[$indice_champ['NOTE']]." pour le numéro d'anonymat ".$data[$indice_champ['N_ANONYMAT']]." est invalide.<br />";
								}
							}
						}
					}
				}
				if($nb_reg>0) {
					$msg.="$nb_reg notes enregistrée(s).<br />";
				}
			}
		}
		else {
			$msg="Aucun fichier n'a été sélectionné !<br />";
		}
	}
}

include('lib_eb.php');

$javascript_specifique[] = "lib/tablekit";
$utilisation_tablekit="ok";

//+++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
$themessage  = 'Des informations ont été modifiées. Voulez-vous vraiment quitter sans enregistrer ?';
//**************** EN-TETE *****************
$titre_page = "Epreuve blanche: Saisie des notes";
//echo "<div class='noprint'>\n";
require_once("../lib/header.inc.php");
//echo "</div>\n";
//**************** FIN EN-TETE *****************

//debug_var();

//echo "<div class='noprint'>\n";
//echo "<form method=\"post\" action=\"".$_SERVER['PHP_SELF']."\" name='form1'>\n";

if(isset($id_epreuve)) {
	echo "<p class='bold'><a href='index.php?id_epreuve=$id_epreuve&amp;mode=modif_epreuve'";
	echo " onclick=\"return confirm_abandon (this, change, '$themessage')\"";
	echo ">Epreuve blanche n°$id_epreuve</a>";
}
else {
	echo "<p class='bold'><a href='index.php'";
	echo " onclick=\"return confirm_abandon (this, change, '$themessage')\"";
	echo ">Menu Epreuve blanche</a>";
}

//echo "</p>\n";
//echo "</div>\n";

//==================================================================

if(!isset($id_epreuve)) {
	echo "</p>\n";
	// Accéder aux épreuves blanches: non closes
	if(($_SESSION['statut']=='administrateur')||($_SESSION['statut']=='scolarite')) {
		$sql="SELECT * FROM eb_epreuves WHERE etat!='clos' ORDER BY date, intitule;";
	}
	elseif($_SESSION['statut']=='professeur') {
		$sql="SELECT ee.* FROM eb_epreuves ee, eb_profs ep WHERE ee.etat!='clos' AND ee.id=ep.id_epreuve AND ep.login_prof='".$_SESSION['login']."' ORDER BY ee.date, ee.intitule;";
	}
	else {
		echo "<p>Accès non autorisé.</p>\n";

		// Mettre un tentative_intrusion()
		// Envisager une saisie par le compte secours

		require("../lib/footer.inc.php");
		die();
	}

	//echo "$sql<br />\n";
	$res=mysqli_query($GLOBALS["mysqli"], $sql);
	if(mysqli_num_rows($res)==0) {
		echo "<p>Aucune épreuve non close.</p>\n";
	}
	else {
		echo "<p><b>Epreuves en cours&nbsp;:</b></p>\n";
		echo "<ul>\n";
		while($lig=mysqli_fetch_object($res)) {
			echo "<li>\n";
			//echo "Modifier <a href='".$_SERVER['PHP_SELF']."?id_epreuve=$lig->id&amp;modif_epreuve=y'";
			echo "Saisir <a href='".$_SERVER['PHP_SELF']."?id_epreuve=$lig->id'";
			if($lig->description!='') {
				echo " onmouseover=\"delais_afficher_div('div_epreuve_".$lig->id."','y',-100,20,1000,20,20)\" onmouseout=\"cacher_div('div_epreuve_".$lig->id."')\"";

				$titre="Epreuve n°$lig->id";
				$texte="<p><b>".$lig->intitule."</b><br />";
				$texte.=$lig->description;
				$tabdiv_infobulle[]=creer_div_infobulle('div_epreuve_'.$lig->id,$titre,"",$texte,"",30,0,'y','y','n','n');

			}
			echo ">$lig->intitule</a> (<i>".formate_date($lig->date)."</i>)<br />\n";
			echo "</li>\n";
		}
		echo "</ul>\n";
	}

	require("../lib/footer.inc.php");
	die();
}

echo " | <a href='".$_SERVER['PHP_SELF']."'";
echo " onclick=\"return confirm_abandon (this, change, '$themessage')\"";
echo ">Choix de l'épreuve</a>";

echo " | <a href='".$_SERVER['PHP_SELF']."?id_epreuve=$id_epreuve&amp;mode=export_csv".add_token_in_url()."'";
echo " onclick=\"return confirm_abandon (this, change, '$themessage')\"";
echo ">Exporter au format CSV</a>";

// 20130406
echo " | <a href='".$_SERVER['PHP_SELF']."?id_epreuve=$id_epreuve&amp;mode=import_csv".add_token_in_url()."'";
echo " onclick=\"return confirm_abandon (this, change, '$themessage')\"";
echo ">Importer les notes depuis un CSV</a>";

echo "</p>\n";

//========================================================
// Si prof, tester si id_epreuve est bien associé au prof
if($_SESSION['statut']=='professeur') {
	$sql="SELECT * FROM eb_epreuves ee, eb_profs ep WHERE ee.etat!='clos' AND ee.id=ep.id_epreuve AND ep.login_prof='".$_SESSION['login']."' AND ep.id_epreuve='$id_epreuve' ORDER BY ee.date, ee.intitule;";
	//echo "$sql<br />\n";
	$res=mysqli_query($GLOBALS["mysqli"], $sql);
	if(mysqli_num_rows($res)==0) {
		echo "<p>Accès non autorisé.</p>\n";

		// Mettre un tentative_intrusion()
		// Envisager une saisie par le compte secours

		require("../lib/footer.inc.php");
		die();
	}
}

//========================================================
echo "<p class='bold'>Epreuve n°$id_epreuve</p>\n";

$sql="SELECT * FROM eb_epreuves WHERE id='$id_epreuve';";
$res=mysqli_query($GLOBALS["mysqli"], $sql);
if(mysqli_num_rows($res)==0) {
	echo "<p>L'épreuve choisie (<i>$id_epreuve</i>) n'existe pas.</p>\n";
	require("../lib/footer.inc.php");
	die();
}

$lig=mysqli_fetch_object($res);
$etat=$lig->etat;
$note_sur=$lig->note_sur;

echo "<blockquote>\n";
echo "<p><b>".$lig->intitule."</b> (<i>".formate_date($lig->date)."</i>)<br />\n";
if($lig->description!='') {
	echo nl2br(trim($lig->description))."<br />\n";
}
else {
	echo "Pas de description saisie.<br />\n";
}
echo "</blockquote>\n";


//========================================================
if(($_SESSION['statut']=='administrateur')||($_SESSION['statut']=='scolarite')) {

	$non_aff=isset($_POST['non_aff']) ? $_POST['non_aff'] : (isset($_GET['non_aff']) ? $_GET['non_aff'] : NULL);
	$restreindre_prof=isset($_POST['restreindre_prof']) ? $_POST['restreindre_prof'] : (isset($_GET['restreindre_prof']) ? $_GET['restreindre_prof'] : NULL);
	if(isset($non_aff)) {
		$sql="SELECT * FROM eb_copies WHERE id_epreuve='$id_epreuve' AND login_prof='' ORDER BY n_anonymat";
	}
	elseif(isset($restreindre_prof)) {
		$sql="SELECT * FROM eb_copies WHERE id_epreuve='$id_epreuve' AND login_prof='".$restreindre_prof."' ORDER BY n_anonymat";
	}
	else {
		$sql="SELECT * FROM eb_copies WHERE id_epreuve='$id_epreuve' ORDER BY n_anonymat";
	}
	$res=mysqli_query($GLOBALS["mysqli"], $sql);
	
	if(mysqli_num_rows($res)==0) {
		echo "<p style='color:red;'>Aucune copie n'a été trouvée.<br />Avez-vous associé des groupes/enseignements à l'épreuve&nbsp;?</p>\n";
		require("../lib/footer.inc.php");
		die();
	}
}
elseif($_SESSION['statut']=='professeur') {
	$sql="SELECT * FROM eb_copies WHERE id_epreuve='$id_epreuve' AND login_prof='".$_SESSION['login']."' ORDER BY n_anonymat;";
	$res=mysqli_query($GLOBALS["mysqli"], $sql);
	
	if(mysqli_num_rows($res)==0) {
		echo "<p style='color:red;'>Aucune copie ne vous a été attribuée.<br />Pour un peu, vous auriez corrigé les copies pour rien;o)</p>\n";
		require("../lib/footer.inc.php");
		die();
	}
}
else {
	echo "<p>Accès non autorisé.</p>\n";
	// Mettre un tentative_intrusion()
	// Envisager une saisie par le compte secours
	require("../lib/footer.inc.php");
	die();
}


//========================================================
$sql="SELECT 1=1 FROM eb_copies WHERE id_epreuve='$id_epreuve';";
$test1=mysqli_query($GLOBALS["mysqli"], $sql);

$sql="SELECT DISTINCT n_anonymat FROM eb_copies WHERE id_epreuve='$id_epreuve';";
$test2=mysqli_query($GLOBALS["mysqli"], $sql);
if(mysqli_num_rows($test1)!=mysqli_num_rows($test2)) {
	echo "<p style='color:red;'>Les numéros anonymats ne sont pas uniques sur l'épreuve (<i>cela ne devrait pas arriver</i>).<br />La saisie n'est pas possible.</p>\n";
	require("../lib/footer.inc.php");
	die();
}

$sql="SELECT login_ele FROM eb_copies WHERE n_anonymat='' AND id_epreuve='$id_epreuve';";
$test3=mysqli_query($GLOBALS["mysqli"], $sql);
if(mysqli_num_rows($test3)>0) {
	echo "<p style='color:red;'>Un ou des numéros anonymats ne sont pas valides sur l'épreuve&nbsp;: ";
	$cpt=0;
	while($lig=mysqli_fetch_object($test3)) {
		if($cpt>0) {echo ", ";}
		echo get_nom_prenom_eleve($lig->login_ele);
		$cpt++;
	}
	echo "<br />Cela ne devrait pas arriver.<br />La saisie n'est pas possible.</p>\n";
	require("../lib/footer.inc.php");
	die();
}

if($etat!='clos') {
	$sql="SELECT 1=1 FROM eb_groupes WHERE transfert='y' AND id_epreuve='$id_epreuve';";
	//echo "$sql<br />";
	$test4=mysqli_query($GLOBALS["mysqli"], $sql);
	if(mysqli_num_rows($test4)>0) {
		echo "<p style='color:red;'><b>Anomalie&nbsp;:</b> L'épreuve n'est pas close et le transfert des notes vers les carnets de notes a déjà été effectué pour un enseignement/groupe au moins.<br />Merci de prendre contact avec l'administrateur ou avec le responsable de l'épreuve (<i>en principe titulaire d'un compte 'scolarité'</i>) pour qu'il effectue à nouveau le transfert une fois les notes modifiées/corrigées.";
	}
}
//========================================================

// 20130406
if((isset($mode))&&($mode=='import_csv')) {
	if($etat!='clos') {

		echo "<form enctype='multipart/form-data' action='".$_SERVER['PHP_SELF']."' method='post' name='formulaire'>\n";
		$csv_file="";
		echo add_token_field();
		echo "
	<p>Fichier CSV à importer&nbsp;: <input type='file' name='csv_file' /></p>
	<p><input type='submit' value='Envoyer' /></p>
	<p>
		Si le fichier à importer comporte une première ligne d'en-tête (<em>non vide</em>) à ignorer, <br />cocher la case ci-contre&nbsp;
		<input type='checkbox' name='en_tete' value='yes' checked />
	</p>
	<input type='hidden' name='id_epreuve' value='" . $id_epreuve . "' />
	<input type='hidden' name='mode' value='upload_csv' />
	<p><br /></p>
	<p style='text-indent:-4em;margin-left:4em;'><em>NOTES&nbsp;:</em> Le format du CSV attendu est un CSV à deux colonnes<br />
	&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;N_ANONYMAT;NOTE;<br />
	Le premier champ est le numéro d'anonymat et le deuxième, la note.</p>
</form>\n";

		require("../lib/footer.inc.php");
		die();
	}
	else {
		echo "<p style='color:red'>L'épreuve est close.<br />L'import CSV n'est pas possible.</p>\n";
	}
}

// Couleurs utilisées
$couleur_devoirs = '#AAE6AA';
$couleur_fond = '#AAE6AA';
$couleur_moy_cn = '#96C8F0';

if($etat!='clos') {
	echo "<form method=\"post\" action=\"".$_SERVER['PHP_SELF']."\" name='form1'>\n";
	echo add_token_field();

	if(($_SESSION['statut']=='administrateur')||($_SESSION['statut']=='scolarite')) {
		if(isset($non_aff)) {
			echo "
			<p class='bold'>Saisie des copies non affectées&nbsp;:</p>
			<input type='hidden' name='non_aff' value='y' />";
		}
		elseif(isset($restreindre_prof)) {
			echo "
			<p class='bold'>Saisie des copies de ".civ_nom_prenom($restreindre_prof)."&nbsp;:</p>
			<input type='hidden' name='restreindre_prof' value=\"".$restreindre_prof."\" />";
		}

		$sql="SELECT DISTINCT u.nom, u.prenom, u.civilite, u.login FROM utilisateurs u, eb_profs ep, eb_copies ec WHERE ec.id_epreuve='".$id_epreuve."' AND ec.login_prof=ep.login_prof AND ep.login_prof=u.login AND ep.id_epreuve='".$id_epreuve."' ORDER BY u.nom, u.prenom;";
		$res_restr=mysqli_query($mysqli, $sql);
		$nb_profs=mysqli_num_rows($res_restr);

		$sql="SELECT * FROM eb_copies WHERE id_epreuve='".$id_epreuve."' AND login_prof='';";
		$test_non_aff=mysqli_query($mysqli, $sql);
		$nb_non_aff=mysqli_num_rows($test_non_aff);

		if(($nb_profs>1)||($nb_non_aff>0)) {
			echo "<div style='float:right; width:12em; padding:0.3em;' class='fieldset_opacite50'>
	<p>Saisir<br />
	<a href='".$_SERVER['PHP_SELF']."?id_epreuve=".$id_epreuve."'>toutes les notes</a>,<br />
	<a href='".$_SERVER['PHP_SELF']."?id_epreuve=".$id_epreuve."&non_aff=y'>les élèves non affectés</a>,<br />
	ou les copies de tel professeur<br />";
			while($lig_prof=mysqli_fetch_object($res_restr)) {
				echo "
	<a href='".$_SERVER['PHP_SELF']."?id_epreuve=".$id_epreuve."&restreindre_prof=".$lig_prof->login."'>".$lig_prof->civilite." ".casse_mot($lig_prof->nom, 'maj')." ".casse_mot($lig_prof->prenom, 'majf2')."</a><br />";
			}
			echo "
	</p>
</div>";
		}
	}
}

$acces_visu_eleve=acces("/eleves/visu_eleve.php", $_SESSION['statut']);

echo "<table border='1' cellspacing='2' cellpadding='1' class='boireaus boireaus_alt sortable resizable' summary='Saisie'>\n";
echo "<tr>\n";
echo "<th class='number'>Numéro anonymat</th>\n";
if(($_SESSION['statut']=='administrateur')||($_SESSION['statut']=='scolarite')) {
	$title_col_sp=" title='Colonne non affichée pour un professeur'";
	echo "<th$title_col_sp class='text'>Nom Prénom</th>\n";
}
//echo "<th width='100px'>Note</th>\n";
echo "<th style='width:5em;' class='none'>Note sur $note_sur</th>\n";
echo "<th style='width:5em;' class='none'>Note sur 20</th>\n";
echo "</tr>\n";

$cpt=0;
$alt=1;
while($lig=mysqli_fetch_object($res)) {
	$alt=$alt*(-1);
	echo "<tr class='lig$alt'>\n";
	echo "<td>\n";
	echo "<input type='hidden' name=\"n_anonymat[$cpt]\" value=\"$lig->n_anonymat\" />\n";
	echo "$lig->n_anonymat\n";
	echo "</td>\n";

	if(($_SESSION['statut']=='administrateur')||($_SESSION['statut']=='scolarite')) {
		echo "<td style='background-color:gray;'$title_col_sp>\n";
		if($acces_visu_eleve) {
			echo "<div style='float:right; width:16px'><a href='../eleves/visu_eleve.php?ele_login=".$lig->login_ele."' title=\"Voir le dossier élève dans un nouvel onglet.\" target='_blank'><img src='../images/icons/ele_onglets.png' class='icone16' alt='Voir' /></a></div>";
		}
		echo get_nom_prenom_eleve($lig->login_ele)."\n";
		echo "</td>\n";
	}

	echo "<td id=\"td_".$cpt."\">\n";
	if($etat!='clos') {
		echo "<input id=\"n".$cpt."\" onKeyDown=\"clavier(this.id,event);\" type=\"text\" size=\"4\" ";
		echo "autocomplete=\"off\" ";
		//echo "onfocus=\"javascript:this.select()\" onchange=\"verifcol($cpt);changement()\" ";
		echo "onfocus=\"javascript:this.select()\" onchange=\"verifcol($cpt);calcul_moy_med();changement();\" ";
		echo "name=\"note[$cpt]\" value='";
		if(($lig->statut=='v')) {echo "";}
		elseif($lig->statut!='') {echo "$lig->statut";}
		else {echo "$lig->note";}
		echo "' />\n";
	}
	else {
		if(($lig->statut=='v')) {echo "";}
		elseif($lig->statut!='') {echo "$lig->statut";}
		else {
			echo "$lig->note";
			// Pour le calcul javascript des moyennes,...
			echo "<input type='hidden' id=\"n".$cpt."\" name=\"note[$cpt]\" value=\"$lig->note\" />\n";
		}
	}
	echo "</td>\n";
	echo "<td id='td_sur_20_".$cpt."'></td>\n";
	echo "</tr>\n";
	$cpt++;
}
echo "</table>\n";

//echo "<div style='position: fixed; top: 200px; right: 200px;'>\n";
echo "<div style='position: fixed; top: 200px; right: 14em;'>\n";
javascript_tab_stat('tab_stat_',$cpt);
echo "</div>\n";


if($etat!='clos') {
	echo "<input type='hidden' name='id_epreuve' value='$id_epreuve' />\n";
	echo "<p style='margin-top:0.5em;'><input type='submit' name='saisie_notes' value='Valider' /></p>\n";
	echo "</form>\n";

	echo "
<script type='text/javascript' language='JavaScript'>

function verifcol(num_id){
	document.getElementById('n'+num_id).value=document.getElementById('n'+num_id).value.toLowerCase();
	if(document.getElementById('n'+num_id).value=='a'){
		document.getElementById('n'+num_id).value='abs';
	}
	if(document.getElementById('n'+num_id).value=='d'){
		document.getElementById('n'+num_id).value='disp';
	}
	if(document.getElementById('n'+num_id).value=='n'){
		document.getElementById('n'+num_id).value='-';
	}
	note=document.getElementById('n'+num_id).value;

	if((note!='-')&&(note!='disp')&&(note!='abs')&&(note!='')){
		if((note.search(/^[0-9.]+$/)!=-1)&&(note.lastIndexOf('.')==note.indexOf('.',0))) {
			if((note>$note_sur)||(note<0)){
				couleur='red';
				if(document.getElementById('td_sur_20_'+num_id)) {
					document.getElementById('td_sur_20_'+num_id).innerHTML='';
				}
			}
			else{
				couleur='$couleur_devoirs';
				if(document.getElementById('td_sur_20_'+num_id)) {
					document.getElementById('td_sur_20_'+num_id).innerHTML=eval(note*20/$note_sur);
				}
			}
		}
		else if((note.search(/^[0-9,]+$/)!=-1)&&(note.lastIndexOf(',')==note.indexOf(',',0))) {
			note=note.replace(',', '.');
			if((note>$note_sur)||(note<0)){
				couleur='red';
				if(document.getElementById('td_sur_20_'+num_id)) {
					document.getElementById('td_sur_20_'+num_id).innerHTML='';
				}
			}
			else{
				couleur='$couleur_devoirs';
				if(document.getElementById('td_sur_20_'+num_id)) {
					document.getElementById('td_sur_20_'+num_id).innerHTML=eval(note*20/$note_sur);
				}
			}
		}
		else{
			couleur='red';
			if(document.getElementById('td_sur_20_'+num_id)) {
				document.getElementById('td_sur_20_'+num_id).innerHTML='';
			}
		}
	}
	else{
		couleur='$couleur_devoirs';
		if(document.getElementById('td_sur_20_'+num_id)) {
			document.getElementById('td_sur_20_'+num_id).innerHTML=note;
		}
	}
	eval('document.getElementById(\'td_'+num_id+'\').style.background=couleur');
}

if(document.getElementById('n0')) {
	document.getElementById('n0').focus();
}

for(i=0;i<$cpt;i++) {
	verifcol(i);
}
</script>
";
}

//echo "<p style='color:red;'>Ajouter des confirm_abandon() sur les liens.</p>\n";
echo "<p><br /></p>\n";

require("../lib/footer.inc.php");
?>

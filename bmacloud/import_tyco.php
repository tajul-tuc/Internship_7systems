<?
include 'plugins/class.pdf2text.php';

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function file_get_contents_utf8($fn) {
		 $content = file_get_contents($fn);
		  return mb_convert_encoding($content, 'UTF-8',
			  mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
	}

function tyco_einlesen($fid, $aid){
	global $aid, $userinfo, $debug,$programFilesFolder;
	
	$Anzahl_Melder = 0;
	$q=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$q=mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$q=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");


$c = 0;
$fileid = 0;

$Anzahl_Meldergroups = 0;
$Anzahl_Melder = 0;
$Anzahl_Steuerungen = 0;
// $List_of_valid_types = array(224, 226, 230, 103, 196, 198, 15, 81, 60, 212,  54, 56, 18, 53, 55, 215, 78, 45, 83, 51, 100, 13, 214); #, 45, 89);
$List_of_valid_types = array(224, 226, 230, 103, 196, 198, 15, 81, 60, 212,  54, 56, 18, 53, 55, 215, 78, 45, 83, 51, 100, 13, 214, 45, 89, 248, 239,235,236,237,217,194,197,245,92,234,225,101,   197,86,252,16,17,253,199,22,251,47,102,104,105,84,106,232,220,221,254,46,44,228,249,250,107,108,109,110,255,256,219,63,119,117,118,113,115,247,19,87,88,90,91,99,218,3,2,14,111,112,217,52,62,245);
$List_of_alternative_types = array(196, 13, 224, 248, 239); #, 89);
$missing_array=array();
$List_of_csv_types = array("DKM", "DKM-2", "RAS", "OT", "OT-2", "OTDB", "TD", "Handmelder", "AM");

$Array_Groups_Added = [];
$Array_Controls_Added = [];
$Array_Melder_Added = [];

$Array_Conversion_Table = [];

// $debug = false;

$pattern_group = '@zone.no=(.*)zonelink@i';
$pattern_str = '@St(.*)rung@i';
$pattern_str2 = '@St(.*)rg@i';
$pattern_str3 = '@gest(.*)rt@i';

$pattern_conv = '@(.*)"(\d*)"(.+)physzone---(\d+)---(.*)@isx';

#$pattern_group_found = '@(.*)Meldergruppe.:.(\d*)(.*)tab(.*)tab(.*)@i';
#$pattern_group_found = '@(.*)Meldergruppe(.*):(.*?)(\d*?)(.*)tab(.*)tab(.*)@isx';
$pattern_group_found = '@(.*)Meldergruppe(.*):(.*?)(\d*?)(.*)tab(.*)tab(.*)AOB(.*)@isx';
$pattern_group_found_new_format = '@(.*)fs(.*)(\s)(.*)tab(.*)(.*)tab(.)AOB:(.*)@isx';
$pattern_group_found_mixed = '@(.*)Meldergruppe:(\d*?).tab(.*)tab.tab(.*)@isx';
#$pattern_melder_found = '@(.*)\((\d*)\)\\tab(.*)\\tab(.*)\[(\d*)\](.*)(\d*)\\tab(.*)@i';
$pattern_melder_found = '@(.*)\((\d*)\)(.*)tab(.*)tab(.*)\[(\d*)\](.*):(\d*)(.*)tab(.*)@isx';
$pattern_melder_found_2 = '@(.*)\((\d*)\)(.*)tab(.*)tab(.*):.(\d*)(.*)tab(.*)@isx';
$pattern_melder_found_new_format = '@par(.*?)tab(.*?)tab(.*)tab(.*)\[(.*):(.)(\d*)(\s*)(.*)@isx';

#File suchen
$q=mysql_query("SELECT * FROM `files` WHERE `fid`='$fid'  AND `ordner` = '$programFilesFolder' AND `mandant`='$userinfo[mandant]'");
if($debug)
{
echo("SELECT * FROM `files` WHERE `fid`='$fid'  AND `ordner` = '$programFilesFolder'  AND `mandant`='$userinfo[mandant]'<br>");

}

$FileIDs = array();
$FileNames = array();
$FileCounter = 0;

#File einlesen
while($file=mysql_fetch_array($q)){
	$FileIDs[$FileCounter] = $file[fid];
	$FileNames[$FileCounter] = $file[name];
	$FileCounter++;
	$c++;
}

for ($ic = 0; $ic < $FileCounter; $ic++){
	$fp = get_fid_path($FileIDs[$ic]);
	$line = file($fp);
	$numlines = sizeof($line);

	if (strpos($FileNames[$ic], '.csv')){
		for ($k = 1; $k < $numlines; $k++){
			$Elements = explode(";", $line[$k]);
			$Meldertype = $Elements[9];
			if (in_array($Meldertype, $List_of_csv_types)){
				$Meldernumber = $Elements[1];
				$Meldergroup = $Elements[5];
				$Address = $Elements[3];
				$Ring = $Elements[2];
				$RN = ord($Ring) - 64;
				$Text = $Elements[10];
				$Gruppentext = $Elements[18];
				$Group_Melder = $Meldergroup."_".$Meldernumber;

				if ($Meldergroup != 0){
					if (in_array($Meldergroup, $Array_Groups_Added)){
						if (!empty($Gruppentext)){
							$gu=mysql_query("UPDATE `technik_gruppe` SET `text`='$Gruppentext' WHERE `anlage`='$aid' AND `gruppe`='$Meldergroup'");
						}
					}
					else{
						$ga=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Meldergroup', `text`='$Text', `mandant`='$userinfo[mandant]'");
						array_push($Array_Groups_Added, $Meldergroup);
						$Anzahl_Meldergroups++;
					}
				}
				
				if ($Meldernumber != 0){
					if (in_array($Group_Melder, $Array_Melder_Added)){
						#Nothing to do, already in PP
					}
					else{
						array_push($Array_Melder_Added, $Group_Melder);
						
						if (strpos($Meldertype, 'DKM') !== false){
							$Type = 8101;
						}
						if (strpos($Meldertype, 'DKM-2') !== false){
							$Type = 8101;
						}
						if (strpos($Meldertype, 'Handmelder') !== false){
							$Type = 8101;
						}
						if (strpos($Meldertype, 'OT') !== false){
							$Type = 8103;
						}
						if (strpos($Meldertype, 'OT-2') !== false){
							$Type = 8103;
						}
						if (strpos($Meldertype, 'RAS') !== false){
							$Type = 8102;
						}
						if (strpos($Meldertype, 'TD') !== false){
							$Type = 8104;
						}
						if (strpos($Meldertype, 'AM') !== false){
							$Type = 8105;
						}								
						
						$hersteller = "Tyco";
						#Melderdetails aus Datenbank holen
						$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Type' AND `hersteller`='$hersteller'");
						
						$mtyp=mysql_fetch_array($q4);
						$serial = " ";
						
						#Melder in Datenbank eintragen
						$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Meldergroup', `melder`='$Meldernumber', `text`='$Text', `art`='$mtyp[kurztext]', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$RN', `typ`='$Type', `adresse`='$Address', `serial`='$serial', `mandant`='$userinfo[mandant]'");
						$Anzahl_Melder++;

						#Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
						$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$Meldergroup."' AND `melder` = '".$Meldernumber."' AND `mandant` = '$userinfo[mandant]'");
						if(mysql_num_rows($qm)==0){

							$i1=0;
							$i2=0;
							$i3=0;
							$i4=0;

							$mod = ($Meldernumber%4);
							if($mod==1){$i1='1';}
							if($mod==2){$i2='1';}
							if($mod==3){$i3='1';}
							if($mod==0){$i4='1';}

							#Wenn der Meldertyp einen vorgegebenen Prüfplan hat, dann diesen verwenden:
							if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1)){
								$i1=$mtyp[i1];
								$i2=$mtyp[i2];
								$i3=$mtyp[i3];
								$i4=$mtyp[i4];
							}
							#Prüfplan eintragen in Datenbank
							$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$Meldergroup."',
							`melder` = '".$Meldernumber."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
							$qm=mysql_query($sql);
						}
						$Found_Melder = 0;
					}
				}
			}
		}
	}
	elseif((strpos($FileNames[$ic], '.RTF')) || (strpos($FileNames[$ic], '.rtf'))){
		$current_group;
		for ($h = 3; $h < $numlines; $h++){
			$Meldernummer;
			$Gruppenname;
			
			$temp_elements = explode("\\tab", $line[$h]);
			if (preg_match($pattern_group_found, $line[$h], $subpattern)) {
				
				$Gruppenname = substr($subpattern[6], 0, -1);
				$current_group = $subpattern[5];
				
				$current_group = preg_replace("/[^0-9,.]/", "", $current_group);

				$qg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$current_group', `text`='$Gruppenname', `mandant`='$userinfo[mandant]'");
				$Anzahl_Meldergroups++;
			}
			if (preg_match($pattern_group_found_new_format, $line[$h], $subpattern)) {
				
				$Gruppenname = substr($subpattern[5], 0, -1);
				$current_group = substr($subpattern[4], 0, -1);
				
				$current_group = preg_replace("/[^0-9,.]/", "", $current_group);
				if ($current_group != 0){
					$qg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$current_group', `text`='$Gruppenname', `mandant`='$userinfo[mandant]'");
					$Anzahl_Meldergroups++;
				}
			}
			if (preg_match($pattern_group_found_mixed, $line[$h], $subpattern)) {
				$Gruppenname = substr($subpattern[3], 0, -1);
				$current_group = $subpattern[2];

				$qg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$current_group', `text`='$Gruppenname', `mandant`='$userinfo[mandant]'");
				$Anzahl_Meldergroups++;
				$line[$h] = "\par \\tab ".$subpattern[4];
			}
			if (preg_match($pattern_melder_found, $line[$h], $subpattern)) {
				$Meldernummer = $subpattern[2];
				$Meldername = substr($subpattern[4], 0, -1);
				$Ring = $subpattern[6];
				$Ring = str_replace(' ', '', $Ring);
				$Adresse = substr($subpattern[9], 0, -1);
				$Meldertype = $subpattern[10];
				$Typ = 8000;
				if (strpos($Meldertype, 'DIN820') !== false){ #Handmelder
					$Typ = 8101;
				}
				if (strpos($Meldertype, 'DIN830') !== false){ #Handmelder
					$Typ = 8101;
				}
				if (strpos($Meldertype, 'HM3-D1') !== false){ #Handmelder
					$Typ = 8101;
				}
				if (strpos($Meldertype, 'ADK/UAK') !== false){ #Handmelder
					$Typ = 8101;
				}
				if (strpos($Meldertype, '801PC') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, '801PH') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, '801 PH') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, '850PH') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, '850 PH') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, 'TY850PC') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, '813P') !== false){ #Optischer Melder
					$Typ = 8003;
				}
				if (strpos($Meldertype, 'OR3-S1') !== false){ #Optischer Melder
					$Typ = 8003;
				}
				if (strpos($Meldertype, '801H') !== false){ #Wärmemelder
					$Typ = 8002;
				}
				if (strpos($Meldertype, '801 H') !== false){ #Wärmemelder
					$Typ = 8002;
				}
				if (strpos($Meldertype, '850 H') !== false){ #Wärmemelder
					$Typ = 8002;
				}
				if (strpos($Meldertype, 'W3-S1') !== false){ #Wärmemelder
					$Typ = 8002;
				}
				if (strpos($Meldertype, 'PSB3000') !== false){ #Sirene
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'SNM800') !== false){ #Sirene
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'LPSY800') !== false){ #Sirene
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'LPSY865') !== false){ #Sirene
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'SAM800') !== false){ #Summer
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'MIM800') !== false){ #RAS
					$Typ = 45;
				}
				if (strpos($Meldertype, 'MIO800') !== false){ #RAS
					$Typ = 45;
				}
				if (strpos($Meldertype, 'APM800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'SIO800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'DIM800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'DIM 800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'RIM800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'RIM 800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'TSM800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'TSM 800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'PSM800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'QRM850(4)') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'QRM 850') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'EANUE') !== false){ #Übertragungseinheit
					$Typ = 8107;
				}
				
				$hersteller = "Tyco";
				#Melderdetails aus Datenbank holen
				$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='$hersteller'");

				$mtyp=mysql_fetch_array($q4);

				$ring = $g[pl];
				$art = $mtyp[kurztext];
				$adresse = $t[adresse];
				$serial = $t[serial];

				#Melder in Datenbank eintragen
				$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$current_group', `melder`='$Meldernummer', `text`='$Meldername', `art`='$art', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Ring', `typ`='$Typ', `adresse`='$Adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");
				$Anzahl_Melder++;
			
				#Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
				$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$current_group."' AND `melder` = '".$Meldernummer."' AND `mandant` = '$userinfo[mandant]'");
				if(mysql_num_rows($qm)==0){
					$i1=0;
					$i2=0;
					$i3=0;
					$i4=0;
					
					$mod = ($Meldernummer%4);
					if($mod==1){$i1='1';}
					if($mod==2){$i2='1';}
					if($mod==3){$i3='1';}
					if($mod==0){$i4='1';}

					#Wenn der Meldertyp einen vorgegebenen Prüfplan hat, dann diesen verwenden:
					if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1)){
						$i1=$mtyp[i1];
						$i2=$mtyp[i2];
						$i3=$mtyp[i3];
						$i4=$mtyp[i4];
					}
					#Prüfplan eintragen in Datenbank
					$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$current_group."',
					`melder` = '".$Meldernummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
					$qm=mysql_query($sql);
				}
			
			}
			
			elseif (preg_match($pattern_melder_found_2, $line[$h], $subpattern)) {
				$Meldernummer = $subpattern[2];
				$Meldername = substr($subpattern[4], 0, -1);
				$Ring = $subpattern[5];
				$Ring = str_replace(' ', '', $Ring);
				$Ring = ord($Ring) - 64;
				$Adresse = $subpattern[6];
				$Meldertype = $subpattern[8];
				$Typ = 8000;
				if (strpos($Meldertype, 'DIN820') !== false){ #Handmelder
					$Typ = 8101;
				}
				if (strpos($Meldertype, 'DIN830') !== false){ #Handmelder
					$Typ = 8101;
				}
				if (strpos($Meldertype, 'HM3-D1') !== false){ #Handmelder
					$Typ = 8101;
				}
				if (strpos($Meldertype, 'ADK/UAK') !== false){ #Handmelder
					$Typ = 8101;
				}
				if (strpos($Meldertype, '801PC') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, '801PH') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, '801 PH') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, '850 H') !== false){ #Wärmemelder
					$Typ = 8002;
				}
				if (strpos($Meldertype, '850PH') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, '850 PH') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, 'TY850PC') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, '813P') !== false){ #Optischer Melder
					$Typ = 8003;
				}
				if (strpos($Meldertype, 'OR3-S1') !== false){ #Optischer Melder
					$Typ = 8003;
				}
				if (strpos($Meldertype, '801H') !== false){ #Wärmemelder
					$Typ = 8002;
				}
				if (strpos($Meldertype, '801 H') !== false){ #Wärmemelder
					$Typ = 8002;
				}
				if (strpos($Meldertype, 'W3-S1') !== false){ #Wärmemelder
					$Typ = 8002;
				}
				if (strpos($Meldertype, 'PSB3000') !== false){ #Sirene
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'LPSY800-R/W') !== false){ #Sirene
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'SNM800') != false){ #Sirene
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'LPSY800') != false){ #Sirene
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'LPSY865') != false){ #Sirene
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'SAM800') !== false){ #Summer
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'MIM800') !== false){ #RAS
					$Typ = 45;
				}
				if (strpos($Meldertype, 'MIO800') !== false){ #RAS
					$Typ = 45;
				}
				if (strpos($Meldertype, 'APM800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'SIO800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'DIM800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'DIM 800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'RIM800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'RIM 800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'TSM800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'TSM 800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'PSM800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'QRM850(4)') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'QRM 850') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'EANUE') !== false){ #Übertragungseinheit
					$Typ = 8107;
				}
				
				$hersteller = "Tyco";
				#Melderdetails aus Datenbank holen
				$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='$hersteller'");

				$mtyp=mysql_fetch_array($q4);

				$ring = $g[pl];
				$art = $mtyp[kurztext];
				$adresse = $t[adresse];
				$serial = $t[serial];

				#Melder in Datenbank eintragen
				$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$current_group', `melder`='$Meldernummer', `text`='$Meldername', `art`='$art', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Ring', `typ`='$Typ', `adresse`='$Adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");
				$Anzahl_Melder++;
			
				#Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
				$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$current_group."' AND `melder` = '".$Meldernummer."' AND `mandant` = '$userinfo[mandant]'");
				if(mysql_num_rows($qm)==0){
					$i1=0;
					$i2=0;
					$i3=0;
					$i4=0;
					
					$mod = ($Meldernummer%4);
					if($mod==1){$i1='1';}
					if($mod==2){$i2='1';}
					if($mod==3){$i3='1';}
					if($mod==0){$i4='1';}

					#Wenn der Meldertyp einen vorgegebenen Prüfplan hat, dann diesen verwenden:
					if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1)){
						$i1=$mtyp[i1];
						$i2=$mtyp[i2];
						$i3=$mtyp[i3];
						$i4=$mtyp[i4];
					}
					#Prüfplan eintragen in Datenbank
					$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$current_group."',
					`melder` = '".$Meldernummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
					$qm=mysql_query($sql);
				}
				
			}
			
			elseif (preg_match($pattern_melder_found_new_format, $line[$h], $subpattern)) {
				$Meldernummer = substr($subpattern[2], 0, -1);
				$Meldername = substr($subpattern[3], 0, -1);
				$Ring = $subpattern[4];
				$Ring = str_replace(' ', '', $Ring);
				$Ring = ord($Ring) - 64;
				$Adresse = $subpattern[7];
				$Adresse = ltrim($Adresse, "0");
				$Meldertype = $subpattern[9];
				$Typ = 8000;
				if (strpos($Meldertype, 'DIN820') !== false){ #Handmelder
					$Typ = 8101;
				}
				if (strpos($Meldertype, 'DIN830') !== false){ #Handmelder
					$Typ = 8101;
				}
				if (strpos($Meldertype, 'HM3-D1') !== false){ #Handmelder
					$Typ = 8101;
				}
				if (strpos($Meldertype, 'ADK/UAK') !== false){ #Handmelder
					$Typ = 8101;
				}
				if (strpos($Meldertype, '801PC') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, '850 PC') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, '801PH') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, '801 PH') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, '850 H') !== false){ #Wärmemelder
					$Typ = 8002;
				}
				if (strpos($Meldertype, '850PH') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, '850 PH') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, 'TY850PC') !== false){ #Multimelder
					$Typ = 78;
				}
				if (strpos($Meldertype, '813P') !== false){ #Optischer Melder
					$Typ = 8003;
				}
				if (strpos($Meldertype, 'OR3-S1') !== false){ #Optischer Melder
					$Typ = 8003;
				}
				if (strpos($Meldertype, '801H') !== false){ #Wärmemelder
					$Typ = 8002;
				}
				if (strpos($Meldertype, '801 H') !== false){ #Wärmemelder
					$Typ = 8002;
				}
				if (strpos($Meldertype, 'W3-S1') !== false){ #Wärmemelder
					$Typ = 8002;
				}
				if (strpos($Meldertype, 'PSB3000') !== false){ #Sirene
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'LPSY 865 R/W') !== false){ #Sirene
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'SAM800') !== false){ #Summer
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'SNM800') !== false){ #Sirene
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'LPSY800') !== false){ #Sirene
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'LPSY865') !== false){ #Sirene
					$Typ = 8006;
				}
				if (strpos($Meldertype, 'MIM800') !== false){ #RAS
					$Typ = 45;
				}
				if (strpos($Meldertype, 'MIM 800') !== false){ #RAS
					$Typ = 45;
				}
				if (strpos($Meldertype, 'MIO800') !== false){ #RAS
					$Typ = 45;
				}
				if (strpos($Meldertype, 'APM800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'SIO800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'DIM800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'DIM 800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'DIN 820') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'RIM800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'RIM 800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'TSM800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'TSM 800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'PSM800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'QRM850(4)') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'QRM 850') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'SIO 800') !== false){ #Steuerung
					$Typ = 8107;
				}
				if (strpos($Meldertype, 'EANUE') !== false){ #Übertragungseinheit
					$Typ = 8107;
				}
				if (stripos($Meldertype, 'Zetfas') !== false){ #Übertragungseinheit
					$Typ = 8107;
				}	
				
				$hersteller = "Tyco";
				#Melderdetails aus Datenbank holen
				$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='$hersteller'");

				$mtyp=mysql_fetch_array($q4);

				$ring = $g[pl];
				$art = $mtyp[kurztext];
				$adresse = $t[adresse];
				$serial = $t[serial];

				#Melder in Datenbank eintragen
				$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$current_group', `melder`='$Meldernummer', `text`='$Meldername', `art`='$art', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Ring', `typ`='$Typ', `adresse`='$Adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");
				$Anzahl_Melder++;
			
				#Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
				$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$current_group."' AND `melder` = '".$Meldernummer."' AND `mandant` = '$userinfo[mandant]'");
				if(mysql_num_rows($qm)==0){
					$i1=0;
					$i2=0;
					$i3=0;
					$i4=0;
					
					$mod = ($Meldernummer%4);
					if($mod==1){$i1='1';}
					if($mod==2){$i2='1';}
					if($mod==3){$i3='1';}
					if($mod==0){$i4='1';}

					#Wenn der Meldertyp einen vorgegebenen Prüfplan hat, dann diesen verwenden:
					if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1)){
						$i1=$mtyp[i1];
						$i2=$mtyp[i2];
						$i3=$mtyp[i3];
						$i4=$mtyp[i4];
					}
					#Prüfplan eintragen in Datenbank
					$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$current_group."',
					`melder` = '".$Meldernummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
					$qm=mysql_query($sql);
				}
				
			}
			
		}
	}
	elseif(strpos($FileNames[$ic], '.pdf')){

		$data = new PDF2Text();
		$data->setFilename($fp); 
		$data->decodePDF();
		
		$pattern_wpn = '@Seite(\s)(\d*)(\s)MGR(\s)Text(\s)Zentrale(\s)AOB(\s)Elemente@isx';
		$datawpn = preg_replace($pattern_wpn, " ", $data->output());

		$pattern_gm = '@(\d)(\d)(\d)(\d)(\d)@';
		$pattern_m = '@(\d)(\d)(\d)(\d)(\d)(.*?)(\s)(\d*)(\s)(\d)(\d)(\d)(.*)@isx';
		$pattern_melder = '@(\d)(\d)(\d)(\s)(.*)(\[)(\d*)(\])(:).(\d*)@isx';
		$pattern_single_melder = '@(.*)(]:)(\s)(\d*)(\s)(.*)@isx';

		$temp_elements_p = preg_split($pattern_gm, $datawpn, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
		
		$numplines = sizeof($temp_elements_p);
		$combine_group = "";
		$group_array = [];
		$group_counter = 0;
		
		$melder_counter = 0;
		$meldername_array = [];
		$meldergruppe_array = [];
		$meldernummer_array = [];
		$melderart_array = [];
		$meldertyp_array = [];
		$melderring_array = [];
		$melderadresse_array = [];

		$internal_lc = 0;
		for ($u = 1; $u < $numlines; $u++){
			$internal_lc++;
			$combine_group .= "$temp_elements_p[$u]";
			if ($internal_lc == 6){
				$internal_lc = 0;
				$group_array[$group_counter] = $combine_group;
				$group_counter++;
				$combine_group = "";
			}
		}
		for ($k = 0; $k < $group_counter; $k++){
			if (preg_match($pattern_m, $group_array[$k], $subpattern)) {
				$temp_elements_m = preg_split($pattern_m, $group_array[$k]);
				
				$Meldergruppe = $subpattern[1].$subpattern[2].$subpattern[3].$subpattern[4].$subpattern[5];
				$Meldergruppentext = $subpattern[6];
				
				$qg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Meldergruppe', `text`='$Meldergruppentext', `mandant`='$userinfo[mandant]'");
				$Anzahl_Meldergroups++;

				$all_melders = $subpattern[10].$subpattern[11].$subpattern[12].$subpattern[13];
				
				$output = preg_split("/HM3-D1|OR3-S1|RIM 800|Zetfas|TSM 800|801 PH|PSM800|SIO 800 Multi|850 PH|DIM 800|W3-S1|801 H|UAK|SIO 800|DIN 820|QRM 850 (4DP)|850 PC|MIM 800/", $all_melders);
				$num_melders = sizeof($output);
				for ($w = 0; $w < $num_melders - 1; $w++){
					
					if ($num_melders == 2){
						if (preg_match($pattern_single_melder, $all_melders, $subpattern4)) {
							$melderart_array[$melder_counter] = $subpattern4[6];
						}
					}
					
					if (preg_match($pattern_melder, $output[$w], $subpattern2)) {
						$meldernummer = $subpattern2[1].$subpattern2[2].$subpattern2[3];
						$meldername = $subpattern2[5];
						$ring = $subpattern2[7];
						$adresse = $subpattern2[10];
						
						$meldername_array[$melder_counter] = $meldername;
						$meldergruppe_array[$melder_counter] = $Meldergruppe;
						$meldernummer_array[$melder_counter] = $meldernummer;
						$melderring_array[$melder_counter] = $ring;
						$melderadresse_array[$melder_counter] = $adresse;
	
						$output2 = preg_split("/(?=HM3-D1)|(?=OR3-S1)|(?=RIM 800)|(?=Zetfas)|(?=TSM 800)|(?=801 PH)|(?=PSM800)|(?=SIO 800 Multi)|(?=850 PH)|(?=DIM 800)|(?=W3-S1)|(?=801 H)|(?=UAK)|(?=SIO 800)|(?=DIN 820)|(?=QRM 850 (4DP))|(?=850 PC)|(?=MIM 800)/", $all_melders);
						$num_melders_2 = sizeof($output2);
						for ($z = 0; $z < $num_melders_2 - 1; $z++){
							$pattern_melder_2 = "@(.*)$meldernummer(.*)@isx";
							
							if (preg_match($pattern_melder_2, $output2[$z], $subpattern3)) {
								$melderart = $subpattern3[1];
								$meldertyp = "";
								
								$melder_counter_prev = $melder_counter - 1;
								if (empty($melderart)){
								}
								else {
									$melderart_array[$melder_counter_prev] = $melderart;
									$meldertyp_array[$melder_counter_prev] = $meldertyp;
									$melderart_array[$melder_counter] = $melderart;
									$meldertyp_array[$melder_counter] = $meldertyp;
								}
							}
						}
						$melder_counter++;
					}
				}
				
			}
		}
		
		for ($c = 0; $c < $melder_counter; $c++){
			$Typ = 8000;
			if (strpos($melderart_array[$c], 'HM3-D1') !== false){ #Handmelder
				$Typ = 8101;
			}
			if (strpos($melderart_array[$c], 'OR3-S1') !== false){ #Optischer Melder
				$Typ = 8003;
			}
			if (strpos($melderart_array[$c], 'SIO 800 Multi') !== false){ #Multimelder
				$Typ = 78;
			}
			if (strpos($melderart_array[$c], '801 PH') !== false){ #Multimelder
				$Typ = 78;
			}
			if (strpos($melderart_array[$c], '850 PH') !== false){ #Multimelder
				$Typ = 78;
			}
			if (strpos($melderart_array[$c], '850 PC') !== false){ #Multimelder
				$Typ = 78;
			}
			if (strpos($melderart_array[$c], 'Zetfas') !== false){ #Übertragungseinheit
				$Typ = 8107;
			}	
			if (strpos($melderart_array[$c], 'RIM 800') !== false){ #Steuerung
				$Typ = 8107;
			}
			if (strpos($melderart_array[$c], 'PSM800') !== false){ #Steuerung
				$Typ = 8107;
			}
			if (strpos($melderart_array[$c], 'TSM 800') !== false){ #Steuerung
				$Typ = 8107;
			}
			if (strpos($melderart_array[$c], 'DIM 800') !== false){ #Steuerung
				$Typ = 8107;
			}
			if (strpos($melderart_array[$c], 'UAK') !== false){ #Steuerung
				$Typ = 8107;
			}
			if (strpos($melderart_array[$c], 'SIO 800') !== false){ #Steuerung
				$Typ = 8107;
			}
			if (strpos($melderart_array[$c], 'DIN 820') !== false){ #Steuerung
				$Typ = 8107;
			}
			if (strpos($melderart_array[$c], 'QRM 850 (4DP)') !== false){ #Steuerung
				$Typ = 8107;
			}
			if (strpos($melderart_array[$c], 'W3-S1') !== false){ #Wärmemelder
				$Typ = 8002;
			}
			if (strpos($melderart_array[$c], '801 H') !== false){ #Wärmemelder
				$Typ = 8002;
			}	
			if (strpos($melderart_array[$c], 'MIM 800') !== false){ #RAS
				$Typ = 45;
			}
			
			$hersteller = "Tyco";
			#Melderdetails aus Datenbank holen
			$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='$hersteller'");

			$mtyp=mysql_fetch_array($q4);

			$ring = $g[pl];
			$art = $mtyp[kurztext];
			$adresse = $t[adresse];
			$serial = $t[serial];

			#Melder in Datenbank eintragen
			$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$meldergruppe_array[$c]', `melder`='$meldernummer_array[$c]', `text`='$meldername_array[$c]', `art`='$melderart_array[$c]', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$melderring_array[$c]', `typ`='$Typ', `adresse`='$melderadresse_array[$c]', `serial`='$serial', `mandant`='$userinfo[mandant]'");
			$Anzahl_Melder++;
		
			#Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
			$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$meldergruppe_array[$c]."' AND `melder` = '".$meldernummer_array[$c]."' AND `mandant` = '$userinfo[mandant]'");
			if(mysql_num_rows($qm)==0){
				$i1=0;
				$i2=0;
				$i3=0;
				$i4=0;
				
				$mod = ($meldernummer_array[$c]%4);
				if($mod==1){$i1='1';}
				if($mod==2){$i2='1';}
				if($mod==3){$i3='1';}
				if($mod==0){$i4='1';}

				#Wenn der Meldertyp einen vorgegebenen Prüfplan hat, dann diesen verwenden:
				if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1)){
					$i1=$mtyp[i1];
					$i2=$mtyp[i2];
					$i3=$mtyp[i3];
					$i4=$mtyp[i4];
				}
				#Prüfplan eintragen in Datenbank
				$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$meldergruppe_array[$c]."',
				`melder` = '".$meldernummer_array[$c]."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
				$qm=mysql_query($sql);
			}
			
			$Anzahl_Melder++;
		}
	}elseif(strpos($FileNames[$ic], '.xml')){
		$xml=simplexml_load_file($fp) or die("Error: Cannot create object");
		$groupZeroFound = false;
		//MElderGroup loop
		
		//Conversion_Table
		for ($i = 2; $i < $numlines; $i++){
			$line_mod = str_replace("<","---",$line[$i]);
			$line_mod = str_replace(">","---",$line_mod);
			#Meldergruppen
			$temp_elements_conv = explode("conversions", $line_mod);
			$number_of_conversions = sizeof($temp_elements_conv);
			$conversions = $temp_elements_conv[1];
			$temp_conversions = explode("conversion", $conversions);
			$number_of_temp_conversions = sizeof($temp_conversions);
			for ($w = 1; $w < $number_of_temp_conversions; $w++){
				if (preg_match($pattern_conv, $temp_conversions[$w], $subpattern_result)) {
					$logical_addr = $subpattern_result[2];
					$physical_addr = $subpattern_result[4];
					array_push ($Array_Conversion_Table, $logical_addr);
					array_push ($Array_Conversion_Table, $physical_addr );
				}
			}
			
		}
		
		
		
		for ($i = 2; $i < $numlines; $i++){
			$line_mod = str_replace("<","---",$line[$i]);
			$line_mod = str_replace(">","---",$line_mod);
			
			#Meldergruppen
			$temp_elements = explode("zones", $line_mod);
			$number_of_zones = sizeof($temp_elements);
			for ($w = 0; $w < $number_of_zones; $w = $w + 2){
				$temp_groups = explode("/zone", $temp_elements[$w + 1]);
				$number_groups = sizeof($temp_groups);
				for ($t = 0; $t < $number_groups; $t = $t + 2){

					if (preg_match($pattern_group, $temp_groups[$t])) {
							$Groupelements = explode("\"", $temp_groups[$t]);
							$Groupnumber = $Groupelements[1];
							$Groupname = $Groupelements[3];

							if ($Groupnumber != 0){
								if (in_array($Groupnumber, $Array_Groups_Added)){
									#Group already in array, nothing to do here
								}
								else{
									$Not_found = 0;
									$Counter_Conversions = sizeof($Array_Conversion_Table);
									for ($a = 0; $a < $Counter_Conversions; $a++){
										if (($Groupnumber == $Array_Conversion_Table[$a]) && ($Not_found == 0)){
											$NGN = $Array_Conversion_Table[$a - 1];
											$Not_found = 1;
											$Groupnumber = $NGN;
										}
									}
									$Not_found = 0;
									$qg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Groupnumber', `text`='$Groupname', `mandant`='$userinfo[mandant]'");
									array_push($Array_Groups_Added, $Groupnumber);
									$Anzahl_Meldergroups++;
								}
							}
					}
					
				}
			
			}
		}
		
		//Melder loop
		foreach ($xml->netnodes->node->points->point as $a)// go through all the point
		{

			if (in_array($a->devtype, $List_of_valid_types) ){ //select only the valid types

				$hersteller = "Tyco";
				#Melderdetails aus Datenbank holen
				$type = $a->devtype;
				
				$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$type' AND `hersteller`='$hersteller'");

				$mtyp=mysql_fetch_array($q4);
				$melderGroup = $a->zone;
				$melderNumber = $a->logicalno;
				$melderName = $a['lang1'];
				$ring = $a->ctrlgrp;
				$art = $mtyp[kurztext];
				$adresse = $a['no'];

				if ($melderNumber != 0){
					if($melderGroup =="0"){ //prove if group 9999 exist if not ,create it
						$melderGroup ="9999";
						$groupZeroFound = true;
					}
					
					$Not_found_2 = 0;
					$Counter_Conversions_2 = sizeof($Array_Conversion_Table);
					for ($a = 0; $a < $Counter_Conversions_2; $a++){
						if (($melderGroup == $Array_Conversion_Table[$a]) && ($Not_found_2 == 0)){
							$NGN = $Array_Conversion_Table[$a - 1];

							$Not_found_2 = 1;
							$melderGroup = $NGN;
						}
					}
					$Not_found_2 = 0;
					
					#Melder in Datenbank eintragen
					$sql1 = "INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$melderGroup', `melder`='$melderNumber', 
					`text`='$melderName', `art`='$art', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$ring', `typ`='$type', `adresse`='$adresse', `mandant`='$userinfo[mandant]'";

					
					$qsql1=mysql_query($sql1);
					$Anzahl_Melder++;
					if($debug){
						echo "A:".$sql1."<br>";
					}
				
					#Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
					$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$melderGroup."' AND `melder` = '".$melderNumber."' AND `mandant` = '$userinfo[mandant]'");
					if(mysql_num_rows($qm)==0)
					{
						$i1=0;
						$i2=0;
						$i3=0;
						$i4=0;
						$mod = ($melderNumber%4);
						if($mod==1){$i1='1';}
						if($mod==2){$i2='1';}
						if($mod==3){$i3='1';}
						if($mod==0){$i4='1';}
						#Wenn der Meldertyp einen vorgegebenen Prüfplan hat, dann diesen verwenden:
						if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1))
						{
							$i1=$mtyp[i1];
							$i2=$mtyp[i2];
							$i3=$mtyp[i3];
							$i4=$mtyp[i4];
						}
						#Prüfplan eintragen in Datenbank
						$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$melderGroup."',
						`melder` = '".$melderNumber."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
						$qm=mysql_query($sql);
					}
				}
			}elseif(!in_array($a->devtype, $List_of_valid_types) && !in_array($a->devtype, $missing_array)){ //list of missing devtypes
				$missing_array[]=(string)$a->devtype;
			}
		}
		if($groupZeroFound)//if group 0 was found insert the group
		{
			$qgr=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='9999', `text`='Group 0 Elements', `mandant`='$userinfo[mandant]'");
			$Anzahl_Meldergroups++;
		}
		if($debug){
			echo "missing types";
			print_r($missing_array);
			echo "<br>";
		}
		
		  
		 #Steuerungen
			$temp_elements3 = explode("ctrlgroups", $line_mod);
			$number_of_points2 = sizeof($temp_elements3);
			for ($h = 0; $h < $number_of_points2; $h = $h + 2){
				$temp_points2 = explode("/ctrlgroup", $temp_elements3[$h + 1]);
				$number_points2 = sizeof($temp_points2);
				for ($v = 0; $v < $number_points2; $v = $v + 1){
					$curr_line_3 = $temp_points2[$v];

					$temp_name = explode("lang1=\"", $curr_line_3);
					$temp_name_2 = explode("\"", $temp_name[1]);
					$Steuerungsname = $temp_name_2[0];
					
					$temp_number = explode("no=\"", $curr_line_3);
					$temp_number_2 = explode("\"", $temp_number[1]);
					$Steuerungsnummer = $temp_number_2[0];
					
					$ansteuerung = 0;
					$ereignis = 0;
					
					if ((strpos($Steuerungsname, 'Steuerung') !== false) 
						|| (strpos($Steuerungsname, 'steuerung') !== false)
						|| (strpos($Steuerungsname, 'anst.') !== false) 
						|| (strpos($Steuerungsname, 'ansteu') !== false) 
						|| (strpos($Steuerungsname, 'Ansteu') !== false) 
						|| (strpos($Steuerungsname, 'Steu') !== false)
																			){
						if ((preg_match($pattern_str, $Steuerungsname))
						|| (preg_match($pattern_str2, $Steuerungsname))
						|| (preg_match($pattern_str3, $Steuerungsname))){
							#Filter Störungen
						}
						else{
							$Steuerungsname = substr($Steuerungsname, 1);
							
							if (in_array($Steuerungsnummer, $Array_Controls_Added)){
								#Steuerung already in array, nothing to do
							}
							else{
								$sg=mysql_query("INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$Steuerungsnummer', `text`='$Steuerungsname', `ansteuerung`='$ansteuerung', `ereignis`='$ereignis', `ausloesung`='', `mandant`='$userinfo[mandant]'");
								array_push($Array_Controls_Added, $Steuerungsnummer);
								$Anzahl_Steuerungen++;
						
								//Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
								$qman=mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr` = '".$Steuerungsnummer."' AND `mandant` = '$userinfo[mandant]'");
								if(mysql_num_rows($qman)==0){
									//Steuergruppen immer in jedem Quartal
									$i1=1;
									$i2=1;
									$i3=1;
									$i4=1;

									$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr` = '".$Steuerungsnummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
									$qman=mysql_query($sql);
								}
							
							}
						}
					
					}

				}
			} 
		/* for ($i = 2; $i < $numlines; $i++){
			$line_mod = str_replace("<","---",$line[$i]);
			$line_mod = str_replace(">","---",$line_mod);
			
			#Meldergruppen
			$temp_elements = explode("zones", $line_mod);
			$number_of_zones = sizeof($temp_elements);
			for ($w = 0; $w < $number_of_zones; $w = $w + 2){
			// echo "potato 1<br>";
				$temp_groups = explode("/zone", $temp_elements[$w + 1]);
				$number_groups = sizeof($temp_groups);
				for ($t = 0; $t < $number_groups; $t = $t + 2){

					if (preg_match($pattern_group, $temp_groups[$t])) {
							$Groupelements = explode("\"", $temp_groups[$t]);
							$Groupnumber = $Groupelements[1];
							$Groupname = $Groupelements[3];

							if ($Groupnumber != 0){
								if (in_array($Groupnumber, $Array_Groups_Added)){
									#Group already in array, nothing to do here
								}
								else{
								// echo "INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Groupnumber', `text`='$Groupname', `mandant`='$userinfo[mandant]'<br>";
									$qg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Groupnumber', `text`='$Groupname', `mandant`='$userinfo[mandant]'");
									array_push($Array_Groups_Added, $Groupnumber);
									$Anzahl_Meldergroups++;
								}
							}
					}
					
				}
			
			}

			
			#Melder
			$temp_elements2 = explode("points", $line_mod);
			$number_of_points = sizeof($temp_elements2);
			for ($z = 0; $z < $number_of_points; $z = $z + 2){
			
				$temp_points = explode("/point", $temp_elements2[$z + 1]);
				$number_points = sizeof($temp_points);
				for ($u = 0; $u < $number_points; $u = $u + 1){
					$curr_line_2 = $temp_points[$u];
					$curr_line_elements_2 = explode("\"", $curr_line_2);
					$Meldername = $curr_line_elements_2[3];
					#$Meldernumber = $curr_line_elements_2[1];
					$Adresse = $curr_line_elements_2[1];
					
					$Meldergroup = 0;
					
					$curr_line_elements_ring_t = explode("ctrlgrp", $curr_line_2);
					$curr_line_elements_ring = explode("---", $curr_line_elements_ring_t[1]);
					$Leitung = $curr_line_elements_ring[1];
							
					$curr_line_elements_adresse_t = explode("logicalno", $curr_line_2);
					$curr_line_elements_adresse = explode("---", $curr_line_elements_adresse_t[1]);
					#$Adresse = $curr_line_elements_adresse[1];
					$Meldernumber = $curr_line_elements_adresse[1];
							
					$curr_line_elements_mg_t = explode("zone", $curr_line_2);
					$curr_line_elements_mg = explode("---", $curr_line_elements_mg_t[1]);
					$Meldergroup = $curr_line_elements_mg[1];
					
					$Kategorie = 0;
					$Kanal = 0;
					$Split = 0;
					$DevTypeTemp = 0;
					$DevType = 0;
					$Other_Format = 0;
					$Typ;
					$Found_Melder = 0;
					
					if (strpos($curr_line_elements_2[4], 'lang2') !== false){
						$Meldername .= " - ";
						$Meldername .= $curr_line_elements_2[5];
						$Kategorie = $curr_line_elements_2[11];
						$Kanal = $curr_line_elements_2[7];
						$Split = $curr_line_elements_2[12];
						$DevTypeTemp = explode("---", $Split);
						$DevType = $DevTypeTemp[3];
						#$Adresse = $curr_line_elements_2[9];
						$Other_Format = 1;
					}				
					else{
						if (strpos($curr_line_elements_2[10], 'split') !== false){
							$Kategorie = $curr_line_elements_2[9];
							$Kanal = $curr_line_elements_2[5];
							$Split = $curr_line_elements_2[12];
							$DevTypeTemp = explode("---", $Split);
							$DevType = $DevTypeTemp[3];
						}
						else{
							$Kategorie = $curr_line_elements_2[9];
							$Kanal = $curr_line_elements_2[5];
							$Split = $curr_line_elements_2[10];
							$DevTypeTemp = explode("---", $Split);
							$DevType = $DevTypeTemp[3];
						}
					}
					
					if ((in_array($DevType, $List_of_valid_types)) && ($Other_Format == 0)){
						$Typ = $DevType;
						$Found_Melder = 1;
					}
					elseif ((in_array($DevType, $List_of_alternative_types)) && ($Other_Format == 1)){
						$Typ = $DevType;
						$Found_Melder = 1;
					}

					if ($Found_Melder == 1){
						if (preg_match($pattern_str, $Meldername)){
						}
						else{
							$hersteller = "Tyco";
							#Melderdetails aus Datenbank holen
							$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='$hersteller'");

							$mtyp=mysql_fetch_array($q4);

							$ring = $g[pl];
							$art = $mtyp[kurztext];
							$adresse = $t[adresse];
							$serial = $t[serial];

							#Melder in Datenbank eintragen
							$sql1 = "INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Meldergroup', `melder`='$Meldernumber', 
							`text`='$Meldername', `art`='$art', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Leitung', `typ`='$Typ', `adresse`='$Adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'";
							$qsql1=mysql_query($sql1);
							// echo "A:".$sql1."<br>";
							$Anzahl_Melder++;
						
							#Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
							$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$Meldergroup."' AND `melder` = '".$Meldernumber."' AND `mandant` = '$userinfo[mandant]'");
							if(mysql_num_rows($qm)==0)
							{

							$i1=0;
							$i2=0;
							$i3=0;
							$i4=0;
							
							$mod = ($Meldernumber%4);
							if($mod==1){$i1='1';}
							if($mod==2){$i2='1';}
							if($mod==3){$i3='1';}
							if($mod==0){$i4='1';}

							#Wenn der Meldertyp einen vorgegebenen Prüfplan hat, dann diesen verwenden:
							if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1))
							{
								$i1=$mtyp[i1];
								$i2=$mtyp[i2];
								$i3=$mtyp[i3];
								$i4=$mtyp[i4];
							}
							#Prüfplan eintragen in Datenbank
							$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$Meldergroup."',
							`melder` = '".$Meldernumber."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
							$qm=mysql_query($sql);
							}
							$Found_Melder = 0;
						}
					}

				}
			}
					
			
			#Steuerungen
			$temp_elements3 = explode("ctrlgroups", $line_mod);
			$number_of_points2 = sizeof($temp_elements3);
			for ($h = 0; $h < $number_of_points2; $h = $h + 2){
				$temp_points2 = explode("/ctrlgroup", $temp_elements3[$h + 1]);
				$number_points2 = sizeof($temp_points2);
				for ($v = 0; $v < $number_points2; $v = $v + 1){
					$curr_line_3 = $temp_points2[$v];

					$temp_name = explode("lang1=\"", $curr_line_3);
					$temp_name_2 = explode("\"", $temp_name[1]);
					$Steuerungsname = $temp_name_2[0];
					
					$temp_number = explode("no=\"", $curr_line_3);
					$temp_number_2 = explode("\"", $temp_number[1]);
					$Steuerungsnummer = $temp_number_2[0];
					
					$ansteuerung = 0;
					$ereignis = 0;
					
					if ((strpos($Steuerungsname, 'Steuerung') !== false) 
						|| (strpos($Steuerungsname, 'steuerung') !== false)
						|| (strpos($Steuerungsname, 'anst.') !== false) 
						|| (strpos($Steuerungsname, 'ansteu') !== false) 
						|| (strpos($Steuerungsname, 'Ansteu') !== false) 
						|| (strpos($Steuerungsname, 'Steu') !== false)
																			){
						if ((preg_match($pattern_str, $Steuerungsname))
						|| (preg_match($pattern_str2, $Steuerungsname))
						|| (preg_match($pattern_str3, $Steuerungsname))){
							#Filter Störungen
						}
						else{
							$Steuerungsname = substr($Steuerungsname, 1);
							
							if (in_array($Steuerungsnummer, $Array_Controls_Added)){
								#Steuerung already in array, nothing to do
							}
							else{
								$sg=mysql_query("INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$Steuerungsnummer', `text`='$Steuerungsname', `ansteuerung`='$ansteuerung', `ereignis`='$ereignis', `ausloesung`='', `mandant`='$userinfo[mandant]'");
								array_push($Array_Controls_Added, $Steuerungsnummer);
								$Anzahl_Steuerungen++;
						
								//Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
								$qman=mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr` = '".$Steuerungsnummer."' AND `mandant` = '$userinfo[mandant]'");
								if(mysql_num_rows($qman)==0){
									//Steuergruppen immer in jedem Quartal
									$i1=1;
									$i2=1;
									$i3=1;
									$i4=1;

									$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr` = '".$Steuerungsnummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
									$qman=mysql_query($sql);
								}
							
							}
						}
					
					}

				}
			}
		} 
		*/
	}//end xml

	msg($Anzahl_Melder." Melder Importiert");
	msg($Anzahl_Meldergroups." Meldergruppen Importiert");
	msg($Anzahl_Steuerungen." Steuerungen Importiert");
	
}//end while file num
}//end function

?>

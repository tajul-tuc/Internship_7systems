<?

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function file_get_contents_utf8($fn) {
	$content = file($fn);
	return mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
}

function hekatron_einlesen($fid, $aid){
	global $aid, $userinfo,$programFilesFolder; 
	
	$Anzahl_Meldegruppen = 0;
	$Anzahl_Melder = 0;
	$Anzahl_Ansteuerungen = 0; 

$c = 0;
$fileid = 0;

$debug = false;

$GroupIDs = array();

#Alte Eintragungen aus der Datenbank loeschen
$qd1=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
$qd2=mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
$qd3=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
$qd4=mysql_query("DELETE FROM `technik_ansteuer` WHERE `anlage`='$aid' AND `mandant` = '$userinfo[mandant]'");

$q=mysql_query("SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='$programFilesFolder' AND `mandant`='$userinfo[mandant]'");
if($debug)
{
	
echo("SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='$programFilesFolder' AND `mandant`='$userinfo[mandant]'");
}



#Suchen nach Files
while($file=mysql_fetch_array($q))
{

    if($debug)
{echo "<br>$c: File: $file[name] aid: $file[aid] fid: $file[fid] ";}
$fileid = $file[fid];

$fp = get_fid_path($fileid);

$Meldernummern;

$lines = file($fp);

$numlines = sizeof($lines);

$pattern_eingangsfile_found = '@Daten(.*)BMZ.Elemente(.*)Eingang@isx';
$pattern_externfile_found = '@Daten(.*)BMZ.Elemente(.*)Extern@isx';
$pattern_peripherieassistant_melder_found = '@PeripherieAssistant(.*)Meldergruppe@isx';
$pattern_peripherieassistant_steuerungen_found = '@PeripherieAssistant(.*)Steuerung@isx';
$Testline = preg_replace("/[^A-Za-z0-9 -]/","",$lines[0]);

#Found Eingangsfile
if (preg_match($pattern_eingangsfile_found, $Testline)){
	for ($i = 2; $i < $numlines; $i++){
		$eingang_line = preg_replace("/[^A-Za-z0-9 - \t]/","",$lines[$i]);
		$eingang_line = utf8_encode($eingang_line);
		$single_elements = explode("\t", $eingang_line);
		$Eingang = $single_elements[4];
		$Text = "Eingang - ".$single_elements[6];
		$Ereignis = $single_elements[9];
		$Ansteuerung = $single_elements[10];
		
		#Ansteuerungen in Datenbank eintragen
		mysql_query("INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$Eingang', `text`='$Text', `ansteuerung`='$Ansteuerung', `ereignis`='$Ereignis', `mandant`='$userinfo[mandant]'");
		$Anzahl_Ansteuerungen++;
		
		#Pruefplan berechnen fuer die Ansteuerung, nur wenn es noch keine manuellen Zeilen dafuer gibt
		$qman=mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr` = '".$Gruppennummer."' AND `mandant` = '$userinfo[mandant]'");
		if(mysql_num_rows($qman)==0){
			#Steuergruppen immer in jedem Quartal
			$i1=1;
			$i2=1;
			$i3=1;
			$i4=1;

			#Pruefplan erstellen und eintragen
			$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr` = '".$Gruppennummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
			$qman=mysql_query($sql);
		}

	}
	
}

#Found Extern File
if (preg_match($pattern_externfile_found, $Testline)){
	for ($i = 2; $i < $numlines; $i++){
		$eingang_line = preg_replace("/[^A-Za-z0-9 - \t]/","",$lines[$i]);
		$eingang_line = utf8_encode($eingang_line);
		$single_elements = explode("\t", $eingang_line);
		$Eingang = $single_elements[4];
		$Text = "Extern - ".$single_elements[6];
		$Ereignis = $single_elements[7];
		$Ansteuerung = $single_elements[9];
		
		#Ansteuerungen in Datenbank eintragen
		mysql_query("INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$Eingang', `text`='$Text', `ansteuerung`='$Ansteuerung', `ereignis`='$Ereignis', `mandant`='$userinfo[mandant]'");
		$Anzahl_Ansteuerungen++;
		
		#Pruefplan berechnen fuer die Ansteuerung, nur wenn es noch keine manuellen Zeilen dafuer gibt
		$qman=mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr` = '".$Eingang."' AND `mandant` = '$userinfo[mandant]'");
		if(mysql_num_rows($qman)==0){
			#Steuergruppen immer in jedem Quartal
			$i1=1;
			$i2=1;
			$i3=1;
			$i4=1;

			#Pruefplan erstellen und eintragen
			$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr` = '".$Eingang."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
			$qman=mysql_query($sql);
		}

	}
	
}

if (preg_match($pattern_peripherieassistant_melder_found, $Testline)){ #New format Melder
	for ($i = 2; $i < $numlines; $i++){
		$Elements = explode("\t", $lines[$i]);

		$Typ = $Elements[1];
		$Art = $Elements[2];
		$Gruppe = $Elements[3];
		$Melder = $Elements[4];
		$Ring = $Elements[5];
		$Text = $Elements[6];
		
		$Typ = preg_replace("/[^0-9a-zA-Z ]/","",$Typ);
		$Text = preg_replace("/[^0-9a-zA-Z ]/","",$Text);
		$Art = preg_replace("/[^0-9a-zA-Z ]/","",$Art);
		$Gruppe = preg_replace("/[^0-9]/","",$Gruppe);
		$Melder = preg_replace("/[^0-9]/","",$Melder);
		$Ring = preg_replace("/[^0-9]/","",$Ring);
		
		$RingPosition = "0";
		
		if (in_array($Gruppe, $GroupIDs)){
		}
		else{
			$Gruppentext = "Gruppe ".$Gruppe;
			mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppe', `text`='$Gruppentext', `mandant` = '$userinfo[mandant]'");
			$Anzahl_Meldegruppen++;
			array_push($GroupIDs, $Gruppe);
		}
		
		#Automatischer Melder gefunden
		$Type = "7000";
		if (strpos($Typ,'Mehrkriterien') !== false) {
			$Type = "7001";
		}
		if (strpos($Typ,'MaxDif') !== false) {
			$Type = "7001";
		}
		#Handfeuermelder gefunden
		if (strpos($Typ,'DKM') !== false) {
			$Type = "7002";
		}

		#Meldertypen aus Datenbank
		$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Type' AND `hersteller`='Hekatron'");
		$mtyp=mysql_fetch_array($q4);

		#Melder in Datenbank eintragen
		$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Gruppe', `melder`='$Melder', 
		`text`='$Text', `art`='$Art', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Ring', `typ`='$Type', `adresse`='$RingPosition', `serial`='$serial', `mandant` = '$userinfo[mandant]'");
		$Anzahl_Melder++;
		
		#Prfplan berechnen fr den Melder, nur wenn es noch keine manuellen Zeilen dafr gibt
		$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$Gruppe."' AND `melder` = '".$Melder."' AND `mandant` = '$userinfo[mandant]'");
		if(mysql_num_rows($qm)==0){
			$i1=0;
			$i2=0;
			$i3=0;
			$i4=0;
			$mod = ($Melder%4);

			if($mod==1){$i1='1';}
			if($mod==2){$i2='1';}
			if($mod==3){$i3='1';}
			if($mod==0){$i4='1';}

			#Wenn der Meldertyp einen vorgegebenen Prfplan hat, dann diesen verwenden:
			if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1)){
				$i1=$mtyp[i1];
				$i2=$mtyp[i2];
				$i3=$mtyp[i3];
				$i4=$mtyp[i4];
			}
			
			
			#Prfplan erstellen und eintragen
			$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$Gruppe."', `melder` = '".$Melder."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant` = '$userinfo[mandant]'";
			$qm=mysql_query($sql);
		}
		
	}
}
else if (preg_match($pattern_peripherieassistant_steuerungen_found, $Testline)){ #New format Ansteuerungen
	for ($i = 2; $i < $numlines; $i++){
		$Elements = explode("\t", $lines[$i]);

		$Gruppennummer = $Elements[3];
		$Text = $Elements[2];
		$KundenText = $Elements[6];
		$Ansteuerung = $Elements[5];

		$Gruppennummer = preg_replace("/[^0-9]/","",$Gruppennummer);
		$Text = preg_replace("/[^0-9a-zA-Z ]/","",$Text);
		$KundenText = preg_replace("/[^0-9a-zA-Z ]/","",$KundenText);
		$Ansteuerung = "Ring ".preg_replace("/[^0-9a-zA-Z ]/","",$Ansteuerung);
			
		#Ansteuerungen in Datenbank eintragen
		mysql_query("INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$Gruppennummer', `text`='$KundenText', `ansteuerung`='$Ansteuerung', `ereignis`='$Text', `mandant`='$userinfo[mandant]'");
		$Anzahl_Ansteuerungen++;
		
		#Pruefplan berechnen fuer die Ansteuerung, nur wenn es noch keine manuellen Zeilen dafuer gibt
		$qman=mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr` = '".$Gruppennummer."' AND `mandant` = '$userinfo[mandant]'");
		if(mysql_num_rows($qman)==0){
			#Steuergruppen immer in jedem Quartal
			$i1=1;
			$i2=1;
			$i3=1;
			$i4=1;

			#Pruefplan erstellen und eintragen
			$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr` = '".$Gruppennummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
			$qman=mysql_query($sql);
		}
	}
}
else{ #Old format
	for ($i = 2; $i < $numlines; $i++){
		$Elements = explode("\t", $lines[$i]);
		
			$Typ = $Elements[0];
			$SubTyp = $Elements[1];
			$IntegralSubTyp = $Elements[2];
		$Ueberwacht = $Elements[3];
			$Gruppennummer = $Elements[4];
			$Meldernummer = $Elements[5];
			$Text = $Elements[6];
		$Anzahl = $Elements[7];
		$Indikator = $Elements[8];
		$SockelSirene = $Elements[9];
		$Hardware = $Elements[10];
		$Alarmzwischenspeicher = $Elements[11];
		$Verzoegerungsebene = $Elements[12];
		$Nacht = $Elements[13];
		$Tag = $Elements[14];
		$Intervention = $Elements[15];
		$Teilzentrale = $Elements[16];
		$IntegralModul = $Elements[17];
		$ModulPosition = $Elements[18];
		$Anschluss = $Elements[19];
			$Ring = $Elements[20];
			$RingPosition = $Elements[21];
		$AngeschlossenAnModul = $Elements[22];
		$ModulAnschluss = $Elements[23];
		$CAD = $Elements[24];
		$PA = $Elements[25];
		$Projekt = $Elements[26];
		$TextOK = $Elements[27];	

		$Typ = preg_replace("/[^0-9a-zA-Z ]/","",$Typ);
		$SubTyp = preg_replace("/[^0-9a-zA-Z ]/","",$SubTyp);
		$Text = preg_replace("/[^0-9a-zA-Z ]/","",$Text);
		#if(stristr($Typ, 'Meldergruppe') === FALSE) {
		if (strpos($Typ,'Meldergruppe') !== false) {

			#Melder und Meldergruppen einlesen
			if (strcspn($Meldernummer, '0123456789') == strlen($Meldernummer)){
				#Gruppennummer "nachbearbeiten", alles was keine Zahl ist entfernen
				$Gruppennummer = preg_replace("/[^0-9]/","",$Gruppennummer);
				
				#Meldegruppe gefunden
				mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppennummer', `text`='$Text', `mandant` = '$userinfo[mandant]'");
				$Anzahl_Meldegruppen++;
			}
			else{
				#Gruppen- und Meldernummer "nachbearbeiten", alles was keine Zahl ist entfernen
				$Gruppennummer = preg_replace("/[^0-9]/","",$Gruppennummer);
				$Meldernummer = preg_replace("/[^0-9]/","",$Meldernummer);
				
				$Ring = preg_replace("/[^0-9]/","",$Ring);
				$RingPosition = preg_replace("/[^0-9]/","",$RingPosition);
				
				$Type = 7000;	
				#Melder gefunden
				$IntegralSubTyp = preg_replace("/[^0-9a-zA-Z ]/","",$IntegralSubTyp);
				#Automatischer Melder gefunden
				if (strpos($IntegralSubTyp,'automatischer') !== false) {
					$Type = "7001";
				}
				if (strpos($IntegralSubTyp,'Sonderbrandmelder') !== false){
					$Type = "7001";
				}
				if (strpos($IntegralSubTyp,'Firebeam') !== false){
					$Type = "7001";
				}
				if (strpos($IntegralSubTyp,'Fireray') !== false){
					$Type = "7001";
				}
				if (strpos($SubTyp,'Mehrkriterien') !== false) {
					$Type = "7001";
				}
				if (strpos($SubTyp,'MaxDif') !== false) {
					$Type = "7001";
				}
				if (strpos($SubTyp,'Optisch') !== false) {
					$Type = "7001";
				}
				#Handfeuermelder gefunden
				if (strpos($IntegralSubTyp,'Handfeuermelder') !== false) {
					$Type = "7002";
				}
				if (strpos($SubTyp,'DKM') !== false) {
					$Type = "7002";
				}
				
				if ($Type == 7000){ #No meldertype in file found, analysing text
					if (strpos($Text,'Fireray') !== false) {
						$Type = "7001";
						$IntegralSubTyp = "Fireray";
					}
					if (strpos($Text,'Firebeam') !== false) {
						$Type = "7001";
						$IntegralSubTyp = "Firebeam";
					}
					if (strpos($Text,'AM') !== false) {
						$Type = "7001";
						$IntegralSubTyp = "Automatischer Melder";
					}
					if (strpos($Text,'Wrme') !== false) {
						$Type = "7001";
						$IntegralSubTyp = "Waermemelder";
					}
					if (strpos($Text,'Druckschalter') !== false) {
						$Type = "7002";
						$IntegralSubTyp = "DKM";
					}
				}

				#Meldertypen aus Datenbank
				$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Type' AND `hersteller`='Hekatron'");
				$mtyp=mysql_fetch_array($q4);

				#Melder in Datenbank eintragen
				$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Gruppennummer', `melder`='$Meldernummer', 
				`text`='$Text', `art`='$IntegralSubTyp', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Ring', `typ`='$Type', `adresse`='$RingPosition', `serial`='$serial', `mandant` = '$userinfo[mandant]'");
				$Anzahl_Melder++;
				
				#Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
				$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$Gruppennummer."' AND `melder` = '".$Meldernummer."' AND `mandant` = '$userinfo[mandant]'");
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

					#Wenn der Meldertyp einen vorgegebenen Prfplan hat, dann diesen verwenden:
					if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1)){
						$i1=$mtyp[i1];
						$i2=$mtyp[i2];
						$i3=$mtyp[i3];
						$i4=$mtyp[i4];
					}
					
					
					#Prfplan erstellen und eintragen
					$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$Gruppennummer."', `melder` = '".$Meldernummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant` = '$userinfo[mandant]'";
					$qm=mysql_query($sql);
				}
			}
		}
		elseif (strpos($Typ,'Steuerung') !== false) {
			#Ansteuerungen
			
			#Gruppennummer "nachbearbeiten", alles was keine Zahl ist entfernen
			$Gruppennummer = preg_replace("/[^0-9]/","",$Gruppennummer);
				
			#Ansteuerungen in Datenbank eintragen
			mysql_query("INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$Gruppennummer', `text`='$Text', `ansteuerung`='0', `ereignis`='0', `mandant`='$userinfo[mandant]'");
			$Anzahl_Ansteuerungen++;
			
			#Pruefplan berechnen fuer die Ansteuerung, nur wenn es noch keine manuellen Zeilen dafuer gibt
			$qman=mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr` = '".$Gruppennummer."' AND `mandant` = '$userinfo[mandant]'");
			if(mysql_num_rows($qman)==0){
				#Steuergruppen immer in jedem Quartal
				$i1=1;
				$i2=1;
				$i3=1;
				$i4=1;

				#Pruefplan erstellen und eintragen
				$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr` = '".$Gruppennummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
				$qman=mysql_query($sql);
			}
		}
			
			
	}
}
}	

msg($Anzahl_Melder." Melder Importiert");
msg($Anzahl_Meldegruppen." Meldegrupen Importiert");
msg($Anzahl_Ansteuerungen." Ansteuerungen Importiert");



}
?>

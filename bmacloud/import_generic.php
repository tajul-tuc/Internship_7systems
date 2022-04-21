<?

$debug=false;

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function file_get_contents_utf8($fn) {
		 $content = file_get_contents($fn);
		  return mb_convert_encoding($content, 'UTF-8',
			  mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
	}

//aid wird benötigt, da mehere Files pro Anlage gebraucht werden
function generic_einlesen($fid,$aid){


global $aid, $userinfo,$programFilesFolder;

#Delete old entries
$qd1=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
$qd2=mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
$qd3=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");

#File suchen
$q=mysql_query("SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='$programFilesFolder' AND `mandant`='$userinfo[mandant]'");
if($debug)
{
echo("SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='$programFilesFolder' AND `mandant`='$userinfo[mandant]'");
}

$FileIDs = array();
$FileNames = array();
$FileCounter = 0;

while($file=mysql_fetch_array($q)){
	$FileIDs[$FileCounter] = $file[fid];
	$FileNames[$FileCounter] = $file[name];
	$FileCounter++;
	$c++;
}

	#File suchen für Melder, Meldergruppen und Steuergruppen
	for ($ic = 0; $ic < $FileCounter; $ic++){
		$fp = get_fid_path($FileIDs[$ic]);
		if(strpos($FileNames[$ic],'Melder') !== false){
			//Datei enthält Melder
			$Anzahl_Melder +=melderdatei($fp);
		}
		if(strpos($FileNames[$ic],'Gruppen') !== false){
			//Datei enthält Meldergruppen
			$Anzahl_Meldergruppen += gruppendatei($fp);
		}
		if(strpos($FileNames[$ic],'Steuerungen') !== false){
			//Datei enthält Steuergruppen
			$Anzahl_Steuerungen += steuerungsdatei($fp);
		}
		
		
	}

	msg($Anzahl_Melder." Melder Importiert");
	msg($Anzahl_Meldergruppen." Meldergruppen Importiert");
	msg($Anzahl_Steuerungen." Steuerungen Importiert");
}


function melderdatei($fp){
	global $aid, $userinfo;
	$Number_Melders = 0;
	
	$line = file_get_contents_utf8($fp);
	$single_lines = preg_split('/\n|\r\n?/', $line);
	
	$numb_lines = sizeof($single_lines);
	for ($i = 1; $i < $numb_lines - 1; $i++){
		$split_temp = explode(";", $single_lines[$i]);
		
		$Melder = $split_temp[1];
		$Gruppe = $split_temp[0];
		$Name = $split_temp[2];
		$Leitung = $split_temp[3];
		$Adresse = $split_temp[4];
		$Art = $split_temp[5];
		$Bezeichnung = $split_temp[6];
		$Kommentar = $split_temp[7];

		
		
		#Meldertypen klassifizieren
		$Typ = "2";
		if(strpos($Art,'MULTI') !== false){ #Multisensor
			$Typ = "1";
		}
		if(strpos($Art,'SONSTIGES') !== false){ #Sonstiges
			$Typ = "2";
		}
		if(strpos($Art,'STEUER') !== false){ #Steuermodul
			$Typ = "3";
		}
		if(strpos($Art,'SIRENE') !== false){ #Sirene
			$Typ = "4";
		}
		if(strpos($Art,'OT') !== false){ #Optisch-Thermischer Melder
			$Typ = "5";
		}
		if(strpos($Art,'DKM') !== false){ #Druckknopfmelder
			$Typ = "6";
		}
		if(strpos($Art,'THERMISCH') !== false){ #Thermischer Melder
			$Typ = "7";
		}
		
	
		#Melderdetails aus Datenbank holen
		$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='BMA'");
		$mtyp=mysql_fetch_array($q4);

		$art = $mtyp[kurztext];
		$adresse = $t[adresse];
		$serial = $t[serial];

		#Melder in Datenbank eintragen
		$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Gruppe', `melder`='$Melder', `text`='$Name', `art`='$Bezeichnung', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Leitung', `typ`='$Typ', `adresse`='$Adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");

		$Number_Melders++;
				
		#Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
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
			
			#Wenn der Meldertyp einen vorgegebenen Prüfplan hat, dann diesen verwenden:
			if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1))
			{
				$i1=$mtyp[i1];
				$i2=$mtyp[i2];
				$i3=$mtyp[i3];
				$i4=$mtyp[i4];
			}
			#Prüfplan eintragen in Datenbank
			$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '$Gruppe',
			`melder` = '$Melder', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
			$qm=mysql_query($sql);
		}

	}

	return $Number_Melders;
}


function gruppendatei($fp){
	global $aid, $userinfo;
	$Number_Groups = 0;

	$line = file_get_contents_utf8($fp);
	$single_lines = preg_split('/\n|\r\n?/', $line);
	
	$numb_lines = sizeof($single_lines);
	for ($i = 1; $i < $numb_lines - 1; $i++){
		$split_temp = explode(";", $single_lines[$i]);
		
		$Gruppe = $split_temp[0];
		$Text = $split_temp[1];
		
		$mg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppe', `text`='$Text', `mandant`='$userinfo[mandant]'");
		$Number_Groups++;
	}

	return $Number_Groups;
}


function steuerungsdatei($fp){
	global $aid, $userinfo;
	$Number_Steuerungen = 0;

	$line = file_get_contents_utf8($fp);
	$single_lines = preg_split('/\n|\r\n?/', $line);
	
	$numb_lines = sizeof($single_lines);
	for ($i = 1; $i < $numb_lines - 1; $i++){
		$split_temp = explode(";", $single_lines[$i]);
		
		$Steuerung = $split_temp[0];
		$Name = $split_temp[1];
		$Ereignis = $split_temp[2];
		$Ansteuerung = $split_temp[3];
		$Empty = " ";
		
		#Steuerungen in Datenbank eintragen
		$sql = "INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$Steuerung', `g1`='$Empty', `text`='$Name', `ansteuerung`='$Ansteuerung', `ereignis`='$Ereignis', `mandant`='$userinfo[mandant]'";
		
		$st = mysql_query($sql);
		//Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
		$qman=mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr`='$Index_int' AND `mandant` = '$userinfo[mandant]'");
		if(mysql_num_rows($qman)==0){
			//Steuergruppen immer in jedem Quartal
			$i1=1;
			$i2=1;
			$i3=1;
			$i4=1;

			$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr`='$Steuerung', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
			$qman=mysql_query($sql);
		}

		$Number_Steuerungen++;

	}

	return $Number_Steuerungen;
}







?>



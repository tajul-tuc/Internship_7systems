<?

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function file_get_contents_utf8($fn) {
	$content = file_get_contents($fn);
	return mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
}

function detectomat_einlesen($fid, $aid){
	global $aid, $userinfo, $Array_Values_1, $Array_Values_2, $Array_Values_3;

	$GruppenNummernArray = array();

	$Array_Values_1 = array();
	$Array_Values_2 = array();
	$Array_Values_3 = array();
	$FOUND_prg = 0;
	$FOUND_ktx = 0;
	$No_MG_File_Found = 0;
	$nlcf1 = 0;
	$nlcf2 = 0;
	
	$Anzahl_Meldegruppen = 0;
	$Anzahl_Melder = 0;
	$Anzahl_Ansteuerungen = 0;
	
	$File_Hashes = array();
	$File_Names = array();
	$Array_Doubles = array();
	$Array_Doubles_Original = array();

	$pattern_meldergruppenfile_id = '@(.*)-Alarmzwischenspeicherung-(.*)@isx';
	$pattern_melderfile_id = '@(.*)-Alarmschwelle-(.*)@isx';
	$pattern_steuergruppenfile_id = '@(.*)-Nur.bei.Hauptalarm-(.*)@isx';
	
$c = 0;
$fileid = 0;

$debug = false;
#Alte Eintragungen aus der Datenbank loeschen
$qd1=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
$qd2=mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
$qd3=mysql_query("DELETE o FROM `technik_ansteuer` o WHERE EXISTS(
                    SELECT 'x' FROM `technik_steuergruppen` i WHERE i.`anlage` = '$aid' AND i.`useradd` <> '1' AND i.`mandant` = '$userinfo[mandant]' AND i.`sid` = o.`sgid`)");
                    // ^^^^^^ delete Ansteuerungen for which the steuergruppen will be deleted
$qd4=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");

$q=mysql_query("SELECT * FROM `files` WHERE `aid`='$aid'  AND `ordner` = '0' AND `mandant`='$userinfo[mandant]' ORDER BY `name` ASC");
$qs=mysql_query("SELECT * FROM `files` WHERE `aid`='$aid'  AND `ordner` = '0' AND `mandant`='$userinfo[mandant]' ORDER BY `name` ASC");
if($debug)
{
	
echo("SELECT * FROM `files` WHERE `aid`='$aid' AND `mandant`='$userinfo[mandant]'");
}

#Suche nach Meldegruppenfiles for new ktx+prg version
while($file_search=mysql_fetch_array($qs)){
	if (strpos($file[name],'Meldegruppe') !== false) {
		$No_MG_File_Found = 1;
	}
}

#Suchen nach Files
while($file=mysql_fetch_array($q))
{

    if($debug)
{echo "<br>$aid $c: File: $file[name] aid: $file[aid] fid: $file[fid]";}


$fileid = $file[fid];

$fp = get_fid_path($fileid);

$hvmd5 = hash_file('md5', $fp);

if (false !== $key = array_search($hvmd5, $File_Hashes)) {
	#Do not read in file again and add it to Array_Doubles for user output
	array_push($Array_Doubles,$file[name]);
	array_push($Array_Doubles,$File_Names[$key]);
}
else{
	#Insert current file hash into array
	array_push($File_Hashes,$hvmd5);
	array_push($File_Names,$file[name]);
	
	$line_t = file_get_contents_utf8($fp);
	$line_test = str_replace("<","---",$line_t);
	$line_test = str_replace(">","---",$line_test);
	$line_array = explode("Worksheet", $line_test);
	$first_line_temp = $line_array[1];
	$first_split_line = explode("Row", $line_test);
	$first_line = $first_split_line[1];
	$Found_correct_filename = 0;

	#Old Fileexport found
	if (strpos($file[name],'.prg') !== false) {
		$nlcf1 = old_input_parse_prg($fp, $aid, $userinfo[mandant], $Array_Values_1);
		$FOUND_prg = 1;
		$Found_correct_filename = 1;
	}
	if (strpos($file[name],'.ktx') !== false) {
		$nlcf2 = old_input_parse_ktx($fp, $aid, $userinfo[mandant],$Array_Values_2);
		$nlcf2_alt = old_input_parse_ktx_alt($fp, $aid, $userinfo[mandant],$Array_Values_3);
		$FOUND_ktx = 1;
		$Found_correct_filename = 1;
	}

	if (($FOUND_prg == 1) & ($FOUND_ktx == 1)){
		if ($nlcf1 == $nlcf2){
			$Version = 1;
			$Anzahl_Melder_new_temp = old_input_parse($fp, $aid, $userinfo[mandant], $nlcf1, $No_MG_File_Found, $Version);
			$Anzahl_Melder_Gruppen = explode("-", $Anzahl_Melder_new_temp);
			$Anzahl_Melder_new = $Anzahl_Melder_Gruppen[0];
			$Anzahl_Gruppen_new = $Anzahl_Melder_Gruppen[1];
			$Anzahl_Meldegruppen = $Anzahl_Meldegruppen + $Anzahl_Gruppen_new;
			$Anzahl_Melder = $Anzahl_Melder + $Anzahl_Melder_new;
			$FOUND_prg = 0;
			$FOUND_ktx = 0;
		}
		elseif ($nlcf1 == $nlcf2_alt){
			#Trying alternative mode
			$Version = 2;
			$Anzahl_Melder_new_temp = old_input_parse($fp, $aid, $userinfo[mandant], $nlcf1, $No_MG_File_Found, $Version);
			$Anzahl_Melder_Gruppen = explode("-", $Anzahl_Melder_new_temp);
			$Anzahl_Melder_new = $Anzahl_Melder_Gruppen[0];
			$Anzahl_Gruppen_new = $Anzahl_Melder_Gruppen[1];
			$Anzahl_Meldegruppen = $Anzahl_Meldegruppen + $Anzahl_Gruppen_new;
			$Anzahl_Melder = $Anzahl_Melder + $Anzahl_Melder_new;
			$FOUND_prg = 0;
			$FOUND_ktx = 0;
		}
		else{
			#Alternative mode also failed
			err("Dateien passen nicht zusammen! prg: $nlcf1, ktx: $nlcf2, ktx2: $nlcf2_alt");
		}
	}

	#Anlagenfiles im csv Format
	if ((strpos($file[name],'.csv') !== false) || (strpos($file[name],'.CSV') !== false)) {
		$Anzahl_Melder_Meldergruppen = parse_csv($fp, $aid, $userinfo[mandant]);
		$Split_Result = explode("-", $Anzahl_Melder_Meldergruppen);
		$Anzahl_Melder += $Split_Result[0];
		$Anzahl_Meldegruppen += $Split_Result[1];
		$Anzahl_Ansteuerungen += $Split_Result[2];
		$Found_correct_filename = 1;
	}

	#Anlagenfiles im txt Format
	if (strpos($file[name],'.txt') !== false) {
		$Anzahl_Melder += parse_txt($fp, $aid);
	}

	#Melder gefunden, File einlesen
	if ((strpos($file[name],'Melder') !== false) & (strpos($file[name],'.xls') !== false)) {
		$Anzahl_Melder = melder_einlesen($fp, $aid, $userinfo[mandant], $Anzahl_Melder);
		$Found_correct_filename = 1;
	}
	#Meldegruppen gefunden, File einlesen
	if ((strpos($file[name],'Meldegruppe') !== false) & (strpos($file[name],'.xls') !== false)) {
		$Anzahl_Meldegruppen = meldegruppen_einlesen($fp, $aid, $userinfo[mandant], $Anzahl_Meldegruppen);
		$Found_correct_filename = 1;
	}
	#Ansteuerungen gefunden, File einlesen
	if ((strpos($file[name],'ansteuerungen') !== false) & (strpos($file[name],'.xls') !== false)) {
		$Anzahl_Ansteuerungen = ansteuerungen_einlesen($fp, $aid, $userinfo[mandant], $Anzahl_Ansteuerungen);
		$Found_correct_filename = 1;
	}
	if ((strpos($file[name],'Ansteuerungen') !== false) & (strpos($file[name],'.xls') !== false)) {
		$Anzahl_Ansteuerungen = ansteuerungen_einlesen($fp, $aid, $userinfo[mandant], $Anzahl_Ansteuerungen);
		$Found_correct_filename = 1;
	}
	
	if ($Found_correct_filename == 0){
		#Filetype by content
		if (preg_match($pattern_meldergruppenfile_id, $first_line)){
			$Anzahl_Meldegruppen = meldegruppen_einlesen($fp, $aid, $userinfo[mandant], $Anzahl_Meldegruppen);
		}
		if (preg_match($pattern_melderfile_id, $first_line)){
			$Anzahl_Melder = melder_einlesen($fp, $aid, $userinfo[mandant], $Anzahl_Melder);
		}
		if (preg_match($pattern_steuergruppenfile_id, $first_line)){
			$Anzahl_Ansteuerungen = ansteuerungen_einlesen($fp, $aid, $userinfo[mandant], $Anzahl_Ansteuerungen);
		}
	}
}
$c++;
}

if (!empty($File_Hashes)) {
	$ADF = count($Array_Doubles);
	for ($i = 0; $i < $ADF; $i = $i + 2){
		err("Die Datei '".$Array_Doubles[$i]."' ist identisch mit der Datei '".$Array_Doubles[$i+1]."' und wird deshalb nicht erneut eingelesen.");
	}
}



msg($Anzahl_Melder." Melder Importiert");
msg($Anzahl_Meldegruppen." Meldergrupen Importiert");
msg($Anzahl_Ansteuerungen." Ansteuerungen Importiert");

}

#Parse txt-File
function parse_txt($fp, $aid){
	global $aid, $userinfo;
	
	$Anzahl_Melder_txt = 0;
	$lines = file($fp);
	$numlines = sizeof($lines);
	
	for ($i = 1; $i < $numlines; $i++){
		$elements = preg_split('/[\t]/', $lines[$i]);
		
		$Melder = $elements[4];
		$Gruppe = $elements[3];
		$Text = $elements[6]." ".$elements[12];
		$Type = $elements[5];
		$Ring = $elements[1];
		$Adresse = $elements[2];
		
		$Typ = 6000;
		
		#Meldertypen klassifizieren
		if (strpos($Type, 'Optischer Rauchmelder') !== false){
			$Typ = "6001"; #Optischer Melder
		}
		if (strpos($Type, 'Handfeuermelder') !== false){
			$Typ = "6002"; #Handfeuermelder
		}
		if (strpos($Type, 'Multimelder') !== false){
			$Typ = "6003"; #Multi
		}
		if (strpos($Type, 'Signalgeber') !== false){
			$Typ = "6004"; #Akustik
		}
		if (strpos($Type, 'Ein- Ausgangsmodul') !== false){
			$Typ = "6005"; #Steuermodul
		}
		
		#Meldertypen aus Datenbank
		$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='Detectomat'");
		$mtyp=mysql_fetch_array($q4);

		$gruppe = $t[gruppe];
		$melder = $t[melder];
		$art = $mtyp[kurztext];
		$serial = $t[serial];

		if ($Melder != 0){
			#Melder in Datenbank eintragen
			$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Gruppe', `melder`='$Melder', `text`='$Text', `art`='$Type', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Ring', `typ`='$Typ', `adresse`='$Adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");
			$Anzahl_Melder_txt++;
			
			#Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
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

				#Wenn der Meldertyp einen vorgegebenen Pruefplan hat, dann diesen verwenden:
				if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1)){
					$i1=$mtyp[i1];
					$i2=$mtyp[i2];
					$i3=$mtyp[i3];
					$i4=$mtyp[i4];
				}
				
				#Pruefplan erstellen und eintragen
				$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$Gruppe."', `melder` = '".$Melder."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
				$qm=mysql_query($sql);
			}
		}	
	}
	return $Anzahl_Melder_txt;
}

#Parse csv-File
function parse_csv($fp, $aid, $mandant){
	global $aid, $userinfo;

	$pattern_new_csv_gruppenfile = '@(.*)Gruppe(.)Typ(.)Kundentext(.*)@isx';
	$pattern_new_csv_melderfile = '@(.*)Index(.)Segment(.)Adresse(.*)@isx';
	$pattern_new_csv_steuerungsfile = '@(.*)Ziel\\sEreignis(.)Nur\\sbei\\sHauptalarm(.*)@isx';
	$pattern_new_csv_file = '@(.*)Element(.)Verzeichnis(.)Meldernummer(.)Linktyp(.)Gruppentyp(.*)@isx';
	
	$Anzahl_Melder;
	$Anzahl_Meldegruppen;
	$Anzahl_Steuerungen;
	
	$Group_Num_Counter = 0;
	$csv_version = 0;
	
	$lines = file($fp);
	$numlines = sizeof($lines);
	
	$first_line = $lines[0];
    $first_line = preg_replace("/[^A-Za-z0-9; - \t]/","",$first_line);

	$Last_Line = 1;
	
	if (preg_match($pattern_new_csv_gruppenfile, $first_line)){
		$csv_version = 1;
		$Last_Line = 0;
	}
	if (preg_match($pattern_new_csv_melderfile, $first_line)){
		$csv_version = 2;
		$Last_Line = 0;
	}
	if (preg_match($pattern_new_csv_steuerungsfile, $first_line)){
		$csv_version = 3;
		$Last_Line = 0;
	}
	if (preg_match($pattern_new_csv_file, $first_line)){
		$csv_version = 4;
		$Last_Line = 1;
	}

	for ($i = 1; $i < $numlines - $Last_Line; $i++){
		if ($csv_version == 1){
			$AllElements = explode(";", utf8_encode($lines[$i]));
			$Gruppe = $AllElements[0];
			$Text = $AllElements[2];
			if (empty($Text)){
				$Text = " ";
			}
			$qg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppe', `text`='$Text', `mandant`='$userinfo[mandant]'");
			$Anzahl_Meldegruppen++;
			$Anzahl_Melder = 0;
			$Anzahl_Steuerungen = 0;
		}
		elseif ($csv_version == 2){
			$AllElements = explode(";", utf8_encode($lines[$i]));
			$Melder = $AllElements[4];
			$Gruppe	= $AllElements[3];
			$Text = $AllElements[6];
			$Type = $AllElements[5];
			$Ring = $AllElements[1];
			$Adresse = $AllElements[2];
			$EmptyText = " ";
		
			$Typ = 6000;
			#Meldertypen klassifizieren
			if (strpos($Type, 'Optischer Rauchmelder') !== false){
				$Typ = "6001"; #Optischer Melder
			}
			if (strpos($Type, 'Handfeuermelder') !== false){
				$Typ = "6002"; #Handfeuermelder
			}
			if (strpos($Type, 'Multimelder') !== false){
				$Typ = "6003"; #Multi
			}
			if (strpos($Type, 'Signalgeber') !== false){
				$Typ = "6004"; #Akustik
			}
			if (strpos($Type, 'Ein- Ausgangsmodul') !== false){
				$Typ = "6005"; #Steuermodul
			}
			if (strpos($Type, 'Thermomelder') !== false){
				$Typ = "6006"; #Thermomelder
			}
			
			#Meldertypen aus Datenbank
			$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='Detectomat'");
			$mtyp=mysql_fetch_array($q4);

			$gruppe = $t[gruppe];
			$melder = $t[melder];
			$art = $mtyp[kurztext];
			$serial = $t[serial];

			if ($Melder != 0){
				#Melder in Datenbank eintragen
				$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Gruppe', `melder`='$Melder', `text`='$Text', `art`='$Type', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Ring', `typ`='$Typ', `adresse`='$Adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");
				$Anzahl_Melder++;
				
				#Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
				$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$Gruppennummer."' AND `melder` = '".$Melder."' AND `mandant` = '$userinfo[mandant]'");
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

					#Wenn der Meldertyp einen vorgegebenen Pruefplan hat, dann diesen verwenden
					if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1)){
						$i1=$mtyp[i1];
						$i2=$mtyp[i2];
						$i3=$mtyp[i3];
						$i4=$mtyp[i4];
					}
					
					#Pruefplan erstellen und eintragen
					$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$Gruppe."', `melder` = '".$Melder."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
					$qm=mysql_query($sql);
				}
			}
			$Anzahl_Meldegruppen = 0;
			$Anzahl_Steuerungen = 0;
		}
		elseif ($csv_version == 3){
			$AllElements = explode(";", utf8_encode($lines[$i]));
			$Gruppennummer_Temp = $AllElements[1];
			$Gruppennummer = preg_replace("/[^0-9]/","",$Gruppennummer_Temp);
			$Text = $AllElements[3];
			$Ansteuerung = $AllElements[4]." ".$AllElements[5]." ".$AllElements[6];
			$Ereignis = $AllElements[7];
			
			#Ansteuerungen in Datenbank eintragen
			mysql_query("INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$Gruppennummer', `text`='$Text', `ansteuerung`='$Ansteuerung', `ereignis`='$Ereignis', `mandant`='$userinfo[mandant]'");
			$Anzahl_Steuerungen++;	
			#Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
			$qman=mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr` = '".$Meldegruppe."' AND `mandant` = '$userinfo[mandant]'");
			if(mysql_num_rows($qman)==0){
				#Steuergruppen immer in jedem Quartal
				$i1=1;
				$i2=1;
				$i3=1;
				$i4=1;
				#Pruefplan erstellen und eintragen
				$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr` = '".$Meldegruppe."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
				$qman=mysql_query($sql);
			}
			$Anzahl_Melder = 0;
			$Anzahl_Meldegruppen = 0;

            # Add ansteuerung data to steuergruppe
            mysql_query("INSERT INTO `technik_ansteuer`
                         SET `anlage`='$aid', `sgid`=(SELECT MAX(`sid`) FROM `technik_steuergruppen`), `art`='$Gruppennummer', `ereignis`='$Ereignis', `ausl`='', `g1`=0, `g2`=0, `mandant`='$userinfo[mandant]'");
        }
		elseif ($csv_version == 4){
			$AllElements = explode("-", utf8_encode($lines[$i]));
			$Gruppennummer_Temp = $AllElements[3];
			$Gruppennummer = preg_replace("/[^0-9]/","",$Gruppennummer_Temp);
			$Text = $AllElements[5];
			$Ansteuerung = $AllElements[1]." ".$AllElements[2]." ".$AllElements[4];
			$Ereignis = $AllElements[6];
			
			#Ansteuerungen in Datenbank eintragen
			mysql_query("INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$Gruppennummer', `text`='$Text', `ansteuerung`='$Ansteuerung', `ereignis`='$Ereignis', `mandant`='$userinfo[mandant]'");
			$Anzahl_Steuerungen++;	
			#Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
			$qman=mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr` = '".$Meldegruppe."' AND `mandant` = '$userinfo[mandant]'");
			if(mysql_num_rows($qman)==0){
				#Steuergruppen immer in jedem Quartal
				$i1=1;
				$i2=1;
				$i3=1;
				$i4=1;
				#Pruefplan erstellen und eintragen
				$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr` = '".$Meldegruppe."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
				$qman=mysql_query($sql);
			}
			$Anzahl_Melder = 0;
			$Anzahl_Meldegruppen = 0;
		}
		else {
			$AllElements = explode(";", $lines[$i]);
			$Melder = $AllElements[5];
			$Gruppe	= $AllElements[4];
			$Text = $AllElements[6];
			$Type = $AllElements[7];
			$Ring = $AllElements[0];
			$Adresse = $AllElements[1];
			
			$EmptyText = " ";
		
			#Gruppen eintragen
			if (in_array($Gruppe, $GruppenNummernArray)) {
			}
			else{
				$qg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppe', `text`='$EmptyText', `mandant`='$userinfo[mandant]'");
				$Anzahl_Meldegruppen++;
				$GruppenNummernArray[] = $Gruppe;
			}
				
				
			$Typ = 6000;
		
			#Meldertypen klassifizieren
			if (strpos($Type, 'PBD') !== false){
				$Typ = "6002";
			}
			if (strpos($Type, 'AMD') !== false){
				$Typ = "6003";
			}
			if (strpos($Type, 'LS 3300') !== false){
				$Typ = "6004";
			}
			if (strpos($Type, 'OMS 3301') !== false){
				$Typ = "6004";
			}
			if (strpos($Type, '00 O') !== false){
				$Typ = "6001";
			}
			if (strpos($Type, 'IOM') !== false){
				$Typ = "6001";
			}
			if (strpos($Type, '00 T') !== false){
				$Typ = "6006";
			}
			
			#Meldertypen aus Datenbank
			$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='Detectomat'");
			$mtyp=mysql_fetch_array($q4);

			$gruppe = $t[gruppe];
			$melder = $t[melder];
			$art = $mtyp[kurztext];
			$serial = $t[serial];

			#Melder in Datenbank eintragen
			$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Gruppe', `melder`='$Melder', `text`='$Text', `art`='$Type', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Ring', `typ`='$Typ', `adresse`='$Adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");
			$Anzahl_Melder++;
			
			#Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
			$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$Gruppennummer."' AND `melder` = '".$Meldernummer."' AND `mandant` = '$userinfo[mandant]'");
			if(mysql_num_rows($qm)==0){
				$i1=0;
				$i2=0;
				$i3=0;
				$i4=0;
				$mod = ($melder%4);

				if($mod==1){$i1='1';}
				if($mod==2){$i2='1';}
				if($mod==3){$i3='1';}
				if($mod==0){$i4='1';}

				#Wenn der Meldertyp einen vorgegebenen Pruefplan hat, dann diesen verwenden:
				if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1)){
					$i1=$mtyp[i1];
					$i2=$mtyp[i2];
					$i3=$mtyp[i3];
					$i4=$mtyp[i4];
				}
				
				#Pruefplan erstellen und eintragen
				$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$Gruppe."', `melder` = '".$Melder."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
				$qm=mysql_query($sql);
			}
			$Anzahl_Steuerungen = 0;
		}
	}
	
	$Komb_M_MG = $Anzahl_Melder."-".$Anzahl_Meldegruppen."-".$Anzahl_Steuerungen;
	return $Komb_M_MG;
}


#PRG Dateien im alten Format parsen
function old_input_parse_prg($fp, $aid, $mandant, $Anzahl_Melder){
	global $aid, $userinfo, $Array_Values_1, $Array_Values_2, $Array_Values_3;
	$nlcf1 = 0;
	
	$lines = file($fp);
	$numlines = sizeof($lines);
	
	for ($i = 0; $i < $numlines; $i++){
		$CK = substr_count($lines[$i], ',');
		$CZ = substr_count($lines[$i], '0');
		if (($CK == 5) & ($CZ != 6)){
			$Array_Values_1[$nlcf1] = $lines[$i];
			$nlcf1++;
		}
	}
	return $nlcf1;
}

#KTX Dateien im alten Format parsen
function old_input_parse_ktx($fp, $aid, $mandant, $Anzahl_Melder, $Array_Values_2){
	global $aid, $userinfo, $Array_Values_1, $Array_Values_2, $Array_Values_3;
	$nlcf2 = 0;
	
	$lines = file($fp);
	$numlines = sizeof($lines);
	
	for ($i = 0; $i < $numlines; $i++){
		$TempLine = explode("Segment", $lines[$i]);
		if (preg_match('/[a-zA-Z0-9.,]/',$TempLine[0])){
			$Array_Values_2[$nlcf2] = $lines[$i];
			$nlcf2++;
		}
	}
	return $nlcf2;
}

#KTX Dateien im alten Format parsen - Alternative mode
function old_input_parse_ktx_alt($fp, $aid, $mandant, $Anzahl_Melder, $Array_Values_3){
	global $aid, $userinfo, $Array_Values_1, $Array_Values_2, $Array_Values_3;
	$nlcf2 = 0;
	
	$lines = file($fp);
	$numlines = sizeof($lines);
	$nlcf2 = 0;
	for ($i = 0; $i < $numlines; $i++){
		$TempLine = explode("Segment", $lines[$i]);
		if (preg_match('/[a-zA-Z0-9$]/',$TempLine[0])){
			$Array_Values_3[$nlcf2] = $lines[$i];
			$nlcf2++;
		}
	}
	return $nlcf2;
}

#KTX und PRG Dateien zusammenfuehren und Melder extrahieren
function old_input_parse($fp, $aid, $mandant, $number_lines, $No_MG_File_Found, $Version){
	global $aid, $userinfo, $Array_Values_1, $Array_Values_2, $Array_Values_3;
	$Meldernummer;
	$Gruppennummer;
	$Text;
	$Segment;
	$Adresse;
	$AM = 0;
	$AG = 0;
	$A2Split;
	$Array_Groups_Added = array();
	for ($i = 0; $i < $number_lines; $i++){
	
		$A1Split = explode(",", $Array_Values_1[$i]);
		$Meldernummer = $A1Split[0];
		$Gruppennummer = $A1Split[1];
		$MeldTyp = $A1Split[5];

		if ($Version == 1){
			$A2Split = explode("Segment", $Array_Values_2[$i]);
		}
		elseif ($Version == 2){
			$A2Split = explode("Segment", $Array_Values_3[$i]);
		}
		else{
			err("Fehler beim lesen der .ktx und .prg Dateien");
			return;
		}
		$Temp = explode("Melder", $A2Split[1]);
		$Text = $A2Split[0];
		$Text = substr($Text, 1, -1);
		$Segment = $Temp[0];
		$Adresse = $Temp[1];

		if ($No_MG_File_Found == 0){
			if (in_array($Gruppennummer, $Array_Groups_Added)){
				#Nothing to do, already in PP
			}
			else{
				array_push($Array_Groups_Added, $Gruppennummer);
				$Gruppentext = " ";
				#No other Meldrgruppenfile, insert at least the group numbers, even though we have no text for them
				$qge=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppennummer', `text`='$Gruppentext', `mandant`='$userinfo[mandant]'");
				$AG++;
			}
		}
		else {
			#Found Meldegruppen File, do nothing
		}
		
		$Typ = 6001;
	
		#Meldertypen klassifizieren
		if (($MeldTyp == 16) || ($MeldTyp == 3) || ($MeldTyp == 64)){	#Handmelder
			$Typ = 6002;
		}
		if (($MeldTyp == 23) || ($MeldTyp == 24) || ($MeldTyp == 25) || ($MeldTyp == 17) || ($MeldTyp == 26) || ($MeldTyp == 20) || ($MeldTyp == 19) || ($MeldTyp == 21) || ($MeldTyp == 22) || ($MeldTyp == 15) || ($MeldTyp == 18)){	#Automatischer Melder
			$Typ = 6003;
		}
		if ($MeldTyp == 4){							#Sirene
			$Typ = 6007;
		}
		
		#Meldertypen aus Datenbank
		$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='Detectomat'");
		$mtyp=mysql_fetch_array($q4);

		$gruppe = $t[gruppe];
		$melder = $t[melder];
		$ring = $g[pl];
		$art = $mtyp[kurztext];
		$adresse = $t[adresse];
		$serial = $t[serial];

		if (($Typ == 6001) && ($Text = " ")){
			#Do nothing, not a real melder
		}
		else{
			#Melder in Datenbank eintragen
			$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Gruppennummer', `melder`='$Meldernummer', 
			`text`='$Text', `art`='$Elementtyp', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Segment', `typ`='$Typ', `adresse`='$Adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");
			$AM++;
			
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

				#Wenn der Meldertyp einen vorgegebenen Pruefplan hat, dann diesen verwenden:
				if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1)){
					$i1=$mtyp[i1];
					$i2=$mtyp[i2];
					$i3=$mtyp[i3];
					$i4=$mtyp[i4];
				}
				
				#Pruefplan erstellen und eintragen
				$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$Gruppennummer."', `melder` = '".$Meldernummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
				$qm=mysql_query($sql);
			}
		}
	
	}
	$RV = $AM."-".$AG;
	return $RV;
}


function melder_einlesen($fp, $aid, $mandant, $Anzahl_Melder){
	global $aid, $userinfo;
	
	$Index;
	$Segment;
	$Adresse;
	$Gruppe;
	$Nummer;
	$Type;
	$Kundentext;
	$EmpTag;
	$EmpNacht;
	$Alarmschwelle;
	$Voralarm;
	$Alarm;
	$Kommentar;

	#XLS File parsen
	$lines = file($fp);
	$numlines = sizeof($lines);
	$TempTrim = $lines[$numlines-1];
	$Temp = str_replace("</Row>", "%", $TempTrim);
	$Temp1 = str_replace("<", "=<", $Temp);
	$Temp2 = strip_tags($Temp1);
	$TempEF = str_replace("========", "==== ====", $Temp2);
	
	$data = preg_replace('/={2,}/','=',$TempEF);
	
	$SL = explode("%", $data);
	$NSL = sizeof($SL);

	#Melder durchgehen
	for ($i = 1; $i < $NSL - 1; $i++){
		
		$LTS = explode("=", $SL[$i]);
		
		$Index[$i] = $LTS[1];
		$Segment[$i] = $LTS[2];
		$Adresse[$i] = $LTS[3];
		$Gruppe[$i] = $LTS[4];
		$Nummer[$i] = $LTS[5];
		$Type[$i] = $LTS[6];
		$Kundentext[$i] = $LTS[7];
		$EmpTag[$i] = $LTS[8];
		$EmpNacht[$i] = $LTS[9];
		$Alarmschwelle[$i] = $LTS[10];
		$Voralarm[$i] = $LTS[11];
		$Alarm[$i] = $LTS[12];
		$Kommentar[$i] = $LTS[13];
		
		$Adress = $LTS[3];
		$Ortsbezeichnung = $LTS[7];

		$Leitung = $LTS[2];
		$Elementtyp = $LTS[6];
		$Gruppennummer = $LTS[4];
		$Meldernummer = $LTS[5];
		
		#Meldertypen klassifizieren
		if (strpos($Elementtyp, 'Hand') !== false){
			$Typ = "6002";
		}
		if (strpos($Elementtyp, 'Automatisch') !== false){
			$Typ = "6001";
		}
		if (strpos($Elementtyp, 'Multi') !== false){
			$Typ = "6003";
		}
		if (strpos($Elementtyp, 'Signal') !== false){
			$Typ = "6004";
		}
		if (strpos($Elementtyp, 'CO-Melder') !== false){
			$Typ = "6001";
		}
		if (strpos($Elementtyp, 'Optisch') !== false){
			$Typ = "6001";
		}
		if (strpos($Elementtyp, 'Ionisation') !== false){
			$Typ = "6001";
		}
		if (strpos($Elementtyp, 'Thermomelder') !== false){
			$Typ = "6006";
		}
		if (strpos($Elementtyp, 'linie') !== false){
			$Typ = "6005";
		}

		#Meldertypen aus Datenbank
		$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='Detectomat'");
		$mtyp=mysql_fetch_array($q4);

		$gruppe = $t[gruppe];
		$melder = $t[melder];
		$ring = $g[pl];
		$art = $mtyp[kurztext];
		$adresse = $t[adresse];
		$serial = $t[serial];

		#Melder in Datenbank eintragen
		$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Gruppennummer', `melder`='$Meldernummer', 
		`text`='$Ortsbezeichnung', `art`='$Elementtyp', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Leitung', `typ`='$Typ', `adresse`='$Adress', `serial`='$serial', `mandant`='$userinfo[mandant]'");
		
		//echo($sql);
		
		$Anzahl_Melder++;
		
		#Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
		$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$Gruppennummer."' AND `melder` = '".$Meldernummer."' AND `mandant` = '$userinfo[mandant]'");
		if(mysql_num_rows($qm)==0)
		{

		$i1=0;
		$i2=0;
		$i3=0;
		$i4=0;
		$mod = ($Meldernummer%4);

		if($mod==1){$i1='1';}
		if($mod==2){$i2='1';}
		if($mod==3){$i3='1';}
		if($mod==0){$i4='1';}

		#Wenn der Meldertyp einen vorgegebenen Pruefplan hat, dann diesen verwenden:
		if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1))
		{
			$i1=$mtyp[i1];
			$i2=$mtyp[i2];
			$i3=$mtyp[i3];
			$i4=$mtyp[i4];
		}
		
		#Pruefplan erstellen und eintragen
		$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$Gruppennummer."', `melder` = '".$Meldernummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
		$qm=mysql_query($sql);
		}
	
	
	}
	
	return $Anzahl_Melder;
}

function meldegruppen_einlesen($fp, $aid, $mandant, $Anzahl_Meldegruppen){
	global $aid, $userinfo;
	#XLS File parsen
	$lines = file($fp);
	$numlines = sizeof($lines);
	$TempTrim = $lines[$numlines-1];
	$Temp = str_replace("</Row>", "%", $TempTrim);
	$Temp1 = str_replace("<", "-<", $Temp);
		$Temp2 = strip_tags($Temp1);
	$data = preg_replace('/-{2,}/','-',$Temp2);
	
	$SL = explode("%", $data);
	$NSL = sizeof($SL);
	
	$Gruppe;
	$Typ;
	$Kundentext;
	$Anzahl_Melder;
	$Teilnahme_Verzoegerung;
	$Zweimelderabhaengigkeit;
	$FSA_Gruppe;
	$Zweigruppenabhaengigkeit;
	$Alarmzwischenspeicherung;
	$Kommentar;
	
	#Meldegruppen durchgehen
	for ($i = 1; $i < $NSL - 1; $i++){
		$LTS = explode("-", $SL[$i]);
		
		$Gruppe[$i] = $LTS[1];
		$Typ[$i] = $LTS[2];
		$Kundentext[$i] = $LTS[3];
		$Anzahl_Melder[$i] = $LTS[4];
		$Teilnahme_Verzoegerung[$i] = $LTS[5];
		$Zweimelderabhaengigkeit[$i] = $LTS[6];
		$FSA_Gruppe[$i] = $LTS[7];
		$Zweigruppenabhaengigkeit[$i] = $LTS[8];
		$Alarmzwischenspeicherung[$i] = $LTS[9];
		$Kommentar[$i] = $LTS[10];

		$Gruppennummer = $LTS[1];
		$Comment = $LTS[2];
		
		#Meldegruppen in Datenbank eintragen
		$qg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppennummer', `text`='$Comment', `mandant`='$userinfo[mandant]'");
		$Anzahl_Meldegruppen++;

	}
	
	return $Anzahl_Meldegruppen;
}

function ansteuerungen_einlesen($fp, $aid, $mandant, $Anzahl_Ansteuerungen){
	global $aid, $userinfo;
	#XLS File parsen
	$lines = file($fp);
	$numlines = sizeof($lines);
	$TempTrim = $lines[$numlines-1];
	$Temp = str_replace("</Row>", "%", $TempTrim);
	$Temp1 = str_replace("<", "--<", $Temp);
	$Temp2 = strip_tags($Temp1);
	$data = preg_replace('/-{2,}/','--',$Temp2);
	
	$SL = explode("%", $data);
	$NSL = sizeof($SL);
	
	$pattern_ansteuerung = '@(.*)MG(\D*)(\d*)(.*?)--(.*?)--(.*?)--(.*?)--(.*)@is';
	$pattern_ereignis = '@\/(.*?)--@is';
	
	#Ansteuerungen durchgehen
	for ($i = 1; $i < $NSL - 1; $i++){

		if ($result = preg_match($pattern_ansteuerung, $SL[$i], $subpattern)){
			$Meldegruppe = $subpattern[3];
			$Text = $subpattern[5];
			$Ansteuerung = $subpattern[6];
			
			$Ereignis = "0";
			if ($result = preg_match($pattern_ereignis, $SL[$i], $subpattern2)){
				$Ereignis = $subpattern2[1];
			}
			
			#Ansteuerungen in Datenbank eintragen
			mysql_query("INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$Meldegruppe', `text`='$Text', `ansteuerung`='$Ansteuerung', `ereignis`='$Ereignis', `mandant`='$userinfo[mandant]'");
			$Anzahl_Ansteuerungen++;	
	
			#Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
			$qman=mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr` = '".$Meldegruppe."' AND `mandant` = '$userinfo[mandant]'");
			if(mysql_num_rows($qman)==0){
				if($debug){print "Steuergruppen nicht manuell vorhanden<br>";}
				#Steuergruppen immer in jedem Quartal
				$i1=1;
				$i2=1;
				$i3=1;
				$i4=1;
				#Pruefplan erstellen und eintragen
				$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr` = '".$Meldegruppe."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
				$qman=mysql_query($sql);
			}
		}
	}
	
	return $Anzahl_Ansteuerungen;
}

?>

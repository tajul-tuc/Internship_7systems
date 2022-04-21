<?

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function file_get_contents_utf8($fn) {
		 $content = file_get_contents($fn);
		  return mb_convert_encoding($content, 'UTF-8',
			  mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
	}


function sigmasys_einlesen($fid, $aid){
	global $aid, $userinfo,$programFilesFolder;
	
	$Anzahl_Melder = 0;
	$Anzahl_Meldergruppen = 0;
	$Anzahl_Steuerungen = 0;
	$data = array();
	$steuerungen = array();

	$q=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$q=mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$q=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$q=mysql_query("DELETE FROM `technik_ansteuer` WHERE `anlage` = '$aid' AND `mandant` = '$userinfo[mandant]'");

	$c = 0;
	$fileid = 0;


	$debug = false;
	#File suchen
	$q=mysql_query("SELECT * FROM `files` WHERE `aid`='$aid' AND`fid`='$fid'   AND `ordner` = '$programFilesFolder'  AND `mandant`='$userinfo[mandant]'");
	if($debug)
	{
		echo("SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner` = '$programFilesFolder'  AND `mandant`='$userinfo[mandant]'");
	}

	$FileIDs = array();
	$FileNames = array();
	$FileCounter = 0;

	$pattern_gruppe_melder = '@(.*)MG(\D*)(\d*)\/(\d*)(.*)-(.*)St(.*)r.Verz.:(.*)@is';
	$pattern_melder = '@(.*)MG(\D*)(\d*)\/(\d*)(.*)Angeschl..an:(.*)@is';
	$pattern_gruppe = '@(.*)MG(\D*)(\d*)(.*)Typ:(.*)@is';

	$pattern_linie = '@(.*)Linie (\d*):(.*)@is';

	$block = "";
	$status = "";
	while($file=mysql_fetch_array($q)){
		if($debug)
		{
			echo "<br>$c: File: $file[name]<br>\n";
			echo "<br>aid: $file[aid]<br>\n";
			echo "<br>fid: $file[fid]<br>\n";
		}
		$FileIDs[$FileCounter] = $file[fid];
		$FileNames[$FileCounter] = $file[name];
		$FileCounter++;
		$c++;
	}

	#File suchen
	$fp = get_fid_path($fid);
	$line = file_get_contents_utf8($fp);
	
	$single_lines = explode(PHP_EOL, $line);
	$num_lines = sizeof($single_lines);
	for ($i = 1; $i < $num_lines; $i++){
		$current_line = preg_replace( '/[^[:print:]]/', '',$single_lines[$i]);
		switch($status){
			case "steuerung": 
				$stline = preg_replace( '/\t/', '-tab-',$single_lines[$i]);
				$stline = preg_replace( '/[^[:print:]]/', '',$stline);
				$block .= preg_replace( '/-tab-/', ' ',$stline);
				if ($steuerung = getSteuerung($block)){
					$steuerungen[] = $steuerung;
					$block ="";
				}
				break;
		}
		#Melder gefunden
		if ($result = preg_match($pattern_melder, $current_line, $subpattern)){
			$Gruppe = $subpattern[3];
			$Melder = $subpattern[4];
			$Meldertyp_Temp = $subpattern[5];
			$Meldername = $subpattern[6];
			
			$TempMelder = explode(" - ", $Meldertyp_Temp);
			$Meldertyp = $TempMelder[0];
			$TempMelderText = explode("AlarmFeuer", $TempMelder[1]);
			$Name = $TempMelderText[0]." - ".$Meldername;
			
			if ($Melder != 0){
			
				#Meldertypen klassifizieren
				$Typ = "9100";
				if((strpos($Meldertyp,'FDOOT241') !== false) || (strpos($Meldertyp,'FDO221') !== false)){ #Optischer Melder
					$Typ = "9101";
				}
				if((strpos($Meldertyp,'FDOOT221') !== false) || (strpos($Meldertyp,'FDOOT241-x') !== false)){ #Multisensor
					$Typ = "9102";
				}
				if((strpos($Meldertyp,'DR8') !== false) || (strpos($Meldertyp,'FDM223') !== false)){ #Handfeuermelder
					$Typ = "9103";
				}
				if(strpos($Meldertyp,'FDT221') !== false){ #Wärmemelder 
					$Typ = "9104";
				}
									
				if(strpos($Meldertyp,'Virtueller Eingang') !== false){ #Virtueller Eingang
					$Typ = "9105";
					$Name = "Virtueller Eingang";
					$Meldertyp = "Virtueller Eingang";
				}
				else if(strpos($Meldertyp,'RAS') !== false){ #RAS
					$Typ = "9104";
					$NameTemp = $Meldertyp;
					$NameTemp2 = explode("Alarm", $NameTemp);
					$Name = $NameTemp2[0];
					$Meldertyp = "RAS";
				}
				
				if(strpos($Meldertyp,'berwachter') !== false){ #Ueberwachter Kontakt
					$Typ = "9105";
					$Name = "Ueberwachter Kontakt";
					$Meldertyp = "Ueberwachter Kontakt";
				}


			
				#Melderdetails aus Datenbank holen
				$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='Sigmasys'");
				$mtyp=mysql_fetch_array($q4);

				$art = $mtyp[kurztext];
				$adresse = $t[adresse];
				$serial = $t[serial];

				$Empty_Var = " ";
				
				#Melder in Datenbank eintragen
				$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Gruppe', `melder`='$Melder', 
				`text`='$Name', `art`='$Meldertyp', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Empty_Var', `typ`='$Typ', `adresse`='$Empty_Var', `serial`='$serial', `mandant`='$userinfo[mandant]'");
				$Anzahl_Melder++;
						
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
					$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$Gruppe."',
					`melder` = '".$Melder."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
					$qm=mysql_query($sql);
				}

			}
		}
		
		#Gruppe gefunden
		if ($result = preg_match($pattern_gruppe, $current_line, $subpattern)){
			$Gruppe = $subpattern[3];
			$Gruppenname = $subpattern[5];
			$GruppennameTemp = explode("MG", $Gruppenname);
			$GN = $GruppennameTemp[1];
			$mg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppe', `text`='$GN', `mandant`='$userinfo[mandant]'");	

			$Anzahl_Meldergruppen++;
		}
		#steuerung section
		if($result = preg_match('@Ausg.?nge@s', $current_line, $subpattern)){
			$status = "steuerung";
		}

		if($result = preg_match('@Gefahrenobjekte@s', $current_line, $subpattern)){
			$status = "meldergroup";
		}
		
	}
	
	
	$current_ring = 0;
	#Ring und Ringposition nachtragen
	for ($i = 1; $i < $num_lines; $i++){
		$current_line = $single_lines[$i];
		$current_line = preg_replace( '/[^[:print:]]/', '',$current_line);
		
		if ($result = preg_match($pattern_linie, $current_line, $subpattern)){
			$current_ring = $subpattern[2]; 
		}
		if ($result = preg_match($pattern_gruppe_melder, $current_line, $subpattern)){
			$current_adresse = $subpattern[1];
			$current_group = $subpattern[3];
			$current_melder = $subpattern[4];
			
			$sql_add = "UPDATE `technik_melder` SET `ring`='$current_ring', `adresse`='$current_adresse' WHERE `anlage`='$aid' AND `gruppe` = '$current_group' AND `melder` = '$current_melder' AND `mandant` = '$userinfo[mandant]'";
			$sql_add_done = mysql_query($sql_add);
		}
		
	}
	insertSteuerung($steuerungen,$aid,$userinfo);
	
	msg($Anzahl_Melder." Melder Importiert");
	msg($Anzahl_Meldergruppen." Meldergruppen Importiert");
	msg(count($steuerungen)." Steuerungen Importiert");
}


function getSteuerung($block){
	$text = trim(explode("Angeschl. an",$block)[0]);
	$ansteuerung = trim(explode("Angesteuert durch:",$block)[1]);
	if($text != "" && $ansteuerung != ""){
		$return["text"] = $text;
		$return["ansteuerung"] = $ansteuerung;
		return $return;
	}
	return false;
}

function insertSteuerung($steuerungen,$aid,$userinfo){
	$nr = 1;
	foreach($steuerungen as $steuerung){
		$sql = "INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$nr', `text`='$steuerung[text]', `ansteuerung`='$steuerung[ansteuerung]', `mandant`='$userinfo[mandant]'";
		$st = mysql_query($sql);

		$qman=mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr`='$nr' AND `mandant` = '$userinfo[mandant]'");
		if(mysql_num_rows($qman)==0){
			$i1=1;
			$i2=1;
			$i3=1;
			$i4=1;
			$sql2 = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr`='$nr', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
			$qman=mysql_query($sql2);
		}
		$sql = "INSERT INTO `technik_ansteuer` SET `anlage`='$aid',`art`='', `ausl`='$nr', `ereignis`='', `g1`='', `g2`='', `mandant`='$userinfo[mandant]'";
		$query=mysql_query($sql);

		$nr++;
	}

}

?>



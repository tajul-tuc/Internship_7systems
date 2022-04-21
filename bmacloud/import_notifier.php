<?
mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function file_get_contents_utf8($fn) {
	$content = file_get_contents($fn);
	return mb_convert_encoding($content, 'UTF-8',
	mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
}

function notifier_einlesen($fid, $aid){
	global $aid, $userinfo,$programFilesFolder;

	$Anzahl_Melder = 0;
	$Anzahl_Meldergruppen = 0;
	$Anzahl_Steuerungen = 0;
	$q=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$q=mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$q=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$q=mysql_query("DELETE FROM `technik_ansteuer` WHERE `anlage` = '$aid' AND `mandant` = '$userinfo[mandant]'");

	$Array_Groups_Added = [];
	
	
	$c = 0;
	$fileid = 0;

	// $debug = true;

	#File suchen
	$q=mysql_query("SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='$programFilesFolder' AND `mandant`='$userinfo[mandant]'");
	if($debug){
		echo("SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='$programFilesFolder' AND `mandant`='$userinfo[mandant]'");
	}

	$FileIDs = array();
	$FileCounter = 0;

	#File einlesen
	while($file=mysql_fetch_array($q)){
		if($debug)
		{echo "<br>$c: File: $file[name]<br>\n";echo "<br>aid: $file[aid]<br>\n";echo "<br>fid: $file[fid]<br>\n";}
		$FileIDs[$FileCounter] = $file[fid];
		$FileName[$FileCounter] = $file[name];
		$FileCounter++;
		$c++;
	}

	$pattern_lang = '@(.*)Panel\sName:(.*)\s@i';

	for ($ic = 0; $ic < $FileCounter; $ic++){
		$fp = get_fid_path($FileIDs[$ic]);

		if($debug){
			echo "<br>$ic - $FileCounter Fileid: $FileIDs[$ic]<br>fp: $fp<br>$FileName[$ic]<br>";
		}

		$line = file_get_contents_utf8($fp);
		if (strpos($line,'Steuermatrixregeln') !== false) {
			$line = file($fp);
			$numlines = sizeof($line);
			for ($i = 4; $i < $numlines; $i++){
				$rules = explode("\t", utf8_encode($line[$i]));
				$Rulenumber = $rules[0];
				$Ereignis = $rules[1];
				$Ereignis = substr($Ereignis, 1, -1);
				$Verzoegerung = $rules[8];
				$Verzoegerung = substr($Verzoegerung, 1, -1);
				$Ausnahmen = $rules[9];
				$Ausnahmen = substr($Ausnahmen, 1, -1);
				$Ausgang = $rules[11];
				$Ausgang = substr($Ausgang, 1, -1);
				$Ereignis_new = explode(" ", $Ereignis);

				$sg=mysql_query("INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$Rulenumber', `text`='$Ereignis', `ansteuerung`='$Ausgang', `ereignis`='$Ereignis_new[0]', `ausloesung`='', `mandant`='$userinfo[mandant]'");
				$sg=mysql_query("INSERT INTO `technik_ansteuer` SET `anlage`='$aid', `art`='$Rulenumber', `mandant`='$userinfo[mandant]'");
								
				$Anzahl_Steuerungen++;
			}
			$sql = "SELECT * FROM `technik_steuergruppen` WHERE `anlage`='$aid'  AND `mandant`='$userinfo[mandant]' ORDER BY `nr` ASC";			
			$query=mysql_query($sql);
			while($res = mysql_fetch_array($query)){
				$q=mysql_query("UPDATE `technik_ansteuer` SET `sgid`='$res[sid]' WHERE `anlage`='$aid'  AND `art`='$res[nr]' AND `mandant`='$userinfo[mandant]'");
			}	
		}
		else if (preg_match($pattern_lang, $line)) {
			$lines = explode("Loop", $line);
			$numlines = sizeof($lines);
			for ($i = 0; $i < $numlines; $i++){
				$lines[$i] = "Loop".$lines[$i];
			}

			$FTV == 0;

			#Melder und Meldegruppen identifizieren und sortieren
					

				for ($k = 1; $k < $numlines; $k++){
					if (strpos($lines[$k],';;') !== false) {
						$lines[$k] = str_replace(';;',"\"\t\t\"",$lines[$k]);
					}

					$elements = explode("\t", $lines[$k]);	

					$Adresse = $elements[0];
					$Elementtyp = $elements[2];
					$OPAL = $elements[4];
					$Ortsbezeichnung = $elements[6];
					$Gruppennummer = $elements[10];
					$Meldernummer = $elements[12];
					$Gruppentext = $elements[14];
					$Untergruppe = $elements[16];

					$Adresse = ltrim ($Adresse, '"');
					$Adresse = rtrim ($Adresse, '"');
					$Elementtyp = ltrim ($Elementtyp, '"');
					$Elementtyp = rtrim ($Elementtyp, '"');
					$OPAL = ltrim ($OPAL, '"');
					$OPAL = rtrim ($OPAL, '"');
					$Ortsbezeichnung = ltrim ($Ortsbezeichnung, '"');
					$Ortsbezeichnung = rtrim ($Ortsbezeichnung, '"');
					$Gruppennummer = ltrim ($Gruppennummer, '"');
					$Gruppennummer = rtrim ($Gruppennummer, '"');
					$Meldernummer = ltrim ($Meldernummer, '"');
					$Meldernummer = rtrim ($Meldernummer, '"');
					$Gruppentext = ltrim ($Gruppentext, '"');
					$Gruppentext = rtrim ($Gruppentext, '"');
					$Untergruppe = ltrim ($Untergruppe, '"');
					$Untergruppe = rtrim ($Untergruppe, '"');

					$CompleteText = $Gruppentext." - ".$Ortsbezeichnung;
					if (empty($Ortsbezeichnung)){
						$CompleteText = substr($CompleteText, 0, -3);
					}
					$CompleteText = mysql_real_escape_string($CompleteText);
					$Gruppentext = mysql_real_escape_string($Gruppentext);

					$Temp = $Adresse;
					$Temp1 = explode("/", $Temp);
					$Leitung = preg_replace('/[a-zA-Z]/', '', $Temp1[0]);
					$Temp2 = explode(":", $Temp1[1]);
					$Adresse = $Temp2[1];
					
						if ($Gruppennummer != 0){
							if (in_array($Gruppennummer, $Array_Groups_Added)){
								#Do nothing, group already in PP
							}else{
								$qg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppennummer', `text`='$Gruppentext', `mandant`='$userinfo[mandant]'");
								$Anzahl_Meldergruppen++;
								array_push($Array_Groups_Added, $Gruppennummer);
							}
						}
						#Meldertypen klassifizieren
						$Typ = "5000";
						$submelder ='0';
						if ($Elementtyp == "OPTICAL"){
							$Typ = "5001";
						}
						if ($Elementtyp == "MCP"){
							$Typ = "5002";
						}
						if ($Elementtyp == "MULTI"){
							$Typ = "5003";
						}
						if ($Elementtyp == "BELL"){
							$Typ = "5004";
							$submelder ='1';
						}
						if ($Elementtyp == "ASPR"){
							$Typ = "5005";
						}
						if ($Elementtyp == "THERMO"){
							$Typ = "5006";
						}

						#Melderdetails aus Datenbank holen
						$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='Notifier'");
						$mtyp=mysql_fetch_array($q4);

						$gruppe = $t[gruppe];
						$melder = $t[melder];
						$ring = $g[pl];
						$art = $mtyp[kurztext];
						$adresse = $t[adresse];
						$serial = $t[serial];
						
						#Melder in Datenbank eintragen
						$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Gruppennummer', `melder`='$Meldernummer', `submelder`='".$submelder."',
						`text`='$CompleteText', `art`='$Elementtyp', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Leitung', `typ`='$Typ', `adresse`='$Adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");
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

							#Wenn der Meldertyp einen vorgegebenen Pruefplan hat, dann diesen verwenden:
							if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1)){
								$i1=$mtyp[i1];
								$i2=$mtyp[i2];
								$i3=$mtyp[i3];
								$i4=$mtyp[i4];
							}
							#Pruefplan eintragen in Datenbank
							$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$Gruppennummer."',`submelder`='".$submelder."',
							`melder` = '".$Meldernummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
							$qm=mysql_query($sql);
						}
					}
				
			
		}
		else{
			$lines = explode("Ring", $line);
			$numlines = sizeof($lines);
			for ($i = 0; $i < $numlines; $i++){
				$lines[$i] = "Ring".$lines[$i];
			}

			$FTV == 0;
			#Melder und Meldegruppen identifizieren und sortieren

				for ($k = 1; $k < $numlines; $k++){
					if (strpos($lines[$k],';;') !== false) {
						$lines[$k] = str_replace(';;',"\"\t\t\"",$lines[$k]);
					}
				
					$elements = explode("\t", $lines[$k]);	

					$Adresse = $elements[0];
					$Elementtyp = $elements[2];
					$OPAL = $elements[4];
					$Ortsbezeichnung = $elements[6];
					$Gruppennummer = $elements[10];
					$Meldernummer = $elements[12];
					$Gruppentext = $elements[14];
					$Untergruppe = $elements[16];

					$Adresse = ltrim ($Adresse, '"');
					$Adresse = rtrim ($Adresse, '"');
					$Elementtyp = ltrim ($Elementtyp, '"');
					$Elementtyp = rtrim ($Elementtyp, '"');
					$OPAL = ltrim ($OPAL, '"');
					$OPAL = rtrim ($OPAL, '"');
					$Ortsbezeichnung = ltrim ($Ortsbezeichnung, '"');
					$Ortsbezeichnung = rtrim ($Ortsbezeichnung, '"');
					$Gruppennummer = ltrim ($Gruppennummer, '"');
					$Gruppennummer = rtrim ($Gruppennummer, '"');
					$Meldernummer = ltrim ($Meldernummer, '"');
					$Meldernummer = rtrim ($Meldernummer, '"');
					$Gruppentext = ltrim ($Gruppentext, '"');
					$Gruppentext = rtrim ($Gruppentext, '"');
					$Untergruppe = ltrim ($Untergruppe, '"');
					$Untergruppe = rtrim ($Untergruppe, '"');

					$CompleteText = $Gruppentext." - ".$Ortsbezeichnung;
					if (empty($Ortsbezeichnung)){
						$CompleteText = substr($CompleteText, 0, -3);
					}
					$CompleteText = mysql_real_escape_string($CompleteText);
					$Gruppentext = mysql_real_escape_string($Gruppentext);

					$Temp = $Adresse;
					$Temp1 = explode("/", $Temp);
					$Leitung = preg_replace('/[a-zA-Z]/', '', $Temp1[0]);
					$Temp2 = explode(":", $Temp1[1]);
					$Adresse = $Temp2[1];

					if ($Gruppennummer != 0){
							if (in_array($Gruppennummer, $Array_Groups_Added)){
								#Do nothing, group already in PP
							}else{
								$qg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppennummer', `text`='$Gruppentext', `mandant`='$userinfo[mandant]'");
								$Anzahl_Meldergruppen++;
								array_push($Array_Groups_Added, $Gruppennummer);
							}
						}
						#Meldertypen klassifizieren
						$Typ = "5000";
						$submelder = '0';
						if ($Elementtyp == "Optisch"){
							$Typ = "5001";
						}
						if ($Elementtyp == "DKM"){
							$Typ = "5002";
						}
						if ($Elementtyp == "MULTI"){
							$Typ = "5003";
						}
						if ($Elementtyp == "AKUSTIK"){
							$Typ = "5004";
							$submelder = '1';
						}
						if ($Elementtyp == "Steuer"){
							$Typ = "5005";
						}
						if ($Elementtyp == "THERMO"){
							$Typ = "5006";
						}
						if ($Elementtyp == "BOOSTER"){
							$Typ = "5005";
						}
						if (strpos($Elementtyp, 'BW') !== false){
							$Typ = "5007";
						}

						#Melderdetails aus Datenbank holen
						$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='Notifier'");
						$mtyp=mysql_fetch_array($q4);

						$gruppe = $t[gruppe];
						$melder = $t[melder];
						$ring = $g[pl];
						$art = $mtyp[kurztext];
						$adresse = $t[adresse];
						$serial = $t[serial];

						#Melder in Datenbank eintragen
						$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Gruppennummer', `melder`='$Meldernummer', `submelder`='".$submelder."', 
						`text`='$CompleteText', `art`='$Elementtyp', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Leitung', `typ`='$Typ', `adresse`='$Adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");
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

							#Wenn der Meldertyp einen vorgegebenen Pruefplan hat, dann diesen verwenden:
							if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1)){
								$i1=$mtyp[i1];
								$i2=$mtyp[i2];
								$i3=$mtyp[i3];
								$i4=$mtyp[i4];
							}
							#Pruefplan eintragen in Datenbank
							$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$Gruppennummer."',`submelder`='".$submelder."',
							`melder` = '".$Meldernummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
							$qm=mysql_query($sql);
						}

				}

		}

	}

	msg($Anzahl_Melder." Melder Importiert");
	msg($Anzahl_Meldergruppen." Meldergruppen Importiert");
	msg($Anzahl_Steuerungen." Steuergruppen Importiert");


}


?>



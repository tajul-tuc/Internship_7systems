<?

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function file_get_contents_utf8($fn) {
	 $content = file_get_contents($fn);
	  return mb_convert_encoding($content, 'UTF-8',
		  mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
}
//function to compare group and melder, so when sorted, first group and then melder with usort
 function compare_number($a, $b)
  {
    $retval = strnatcmp($a['group'], $b['group']);
    if(!$retval) $retval = strnatcmp($a['melder'], $b['melder']);
    return $retval;
  }
 function compare_steuer($a, $b)
  {
    $retval = strnatcmp($a['nr'], $b['nr']);
    return $retval;
  }
  // sort alphabetically by name
 
  

function securiton_einlesen($fid, $aid){
	global $aid, $userinfo,$programFilesFolder;

	
	$Anzahl_Meldegruppen = 0;
	$Anzahl_Melder = 0;
	$Anzahl_Ansteuerungen = 0;
	$objekttexte_found = false;
	$c = 0;
	$fileid = 0;
	$debug = false;
	$order ;
	$groupArrayFull = array();
	$melderArrayFull = array();
	$melderArray = array();
	$steuerArrayFull = array();
	$steuerArray = array();
	$pattern_melder = '@(.*?);0;(.*?);(.*?);(.*?);(.*?);(.*?);@is';

	#Alte Eintragungen aus der Datenbank löschen
	$qd1=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$qd2=mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$qd3=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$qs4=mysql_query("DELETE FROM `technik_ansteuer` WHERE `anlage` = '$aid' AND `mandant` = '$userinfo[mandant]'");
	
	$q=mysql_query("SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner` = '$programFilesFolder' AND `mandant`='$userinfo[mandant]' ORDER BY  CASE WHEN `name` LIKE '%Objekttexte%' THEN 1 when `name` LIKE '%Konfiguration%' THEN 2 ELSE 3 END");
	if($debug){

	
		echo("SELECT * FROM `files` WHERE `aid`='$aid'  AND `ordner` = '$programFilesFolder' AND `mandant`='$userinfo[mandant]' ORDER BY  CASE WHEN `name` LIKE '%Objekttexte%' THEN 1 when `name` LIKE '%Konfiguration%' THEN 2 ELSE 3 END");
	}

	$gruppenarray = []; 

	#Suchen nach Files
	
	// print_r($fileArray);
	// echo "file count is ".count($fileArray)."<br><br>";
	
	
	while($file=mysql_fetch_array($q)){
	// foreach($fileSortedArray as $file){

		if($debug)
	{echo "<br>$c: File: $file[name] aid: $file[aid] fid: $file[fid]";}
	$fileid = $file[fid];
	
	if (strpos($file[name],'bjekttexte') !== false ) {
		$fp = get_fid_path($fileid);
		$lines = file($fp);
		$numlines = sizeof($lines);
		$groupIndex = 0;
		$melderIndex = 0;
		$mcount = count($melderArray);
		for ($i = 1; $i < $numlines; $i++){
			$linesplit = explode(";", $lines[$i]);
			//remove strange characters from the numeric cells that could create problems
			for($z = 0; $z <count($linesplit);$z++){
				$linesplit[$z] = preg_replace('/[^A-Za-z0-9\-\ \,\.]/', '', $linesplit[$z]);
			} 
			$textemg =$linesplit[4]." ".$linesplit[5]." ".$linesplit[6]." ".$linesplit[7];
			if($linesplit[1] === "0"){//element type = 0 then it refers to  Melder/gruppe
				if($linesplit[3] ==""){
					mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$linesplit[2]', `text`= '$textemg', `mandant` = '$userinfo[mandant]'");
					$Anzahl_Meldegruppen++;
				}else{
					
					$sql = "INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$linesplit[2]', `melder`='$linesplit[3]',`text`='$textemg', `mandant` = '$userinfo[mandant]'";
					mysql_query($sql);
					// echo $sql."<br>";
					$Anzahl_Melder++;
				}
			}else if($linesplit[1] === "2"){
				if ($linesplit[2] != 0){
					$sqlst ="INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$linesplit[2]', `text`='$textemg', `ansteuerung`='', `ereignis`='', `mandant`='$userinfo[mandant]'";
					mysql_query($sqlst);
					$sgid = mysql_insert_id();
					$sqlanst ="INSERT INTO `technik_ansteuer` SET `sgid`='$sgid', `anlage`='$aid', `art`='$linesplit[2]', `mandant`='$userinfo[mandant]'";
					mysql_query($sqlanst);
					// echo $sqlst."<br>";
					$Anzahl_Ansteuerungen++;
					
					#Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
					$qman=mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr` = '".$linesplit[2]."' AND `mandant` = '$userinfo[mandant]'");
					if(mysql_num_rows($qman)==0){
						#Steuergruppen immer in jedem Quartal
						$i1=1;
						$i2=1;
						$i3=1;
						$i4=1;

						#Prüfplan erstellen und eintragen
						$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr` = '".$linesplit[2]."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
						$qman=mysql_query($sql);
					}
				}
			}
		}
		$objekttexte_found = true;
		
	}
	// print_r($melderArray);
	if (strpos($file[name],'Melder') !== false) {
		$fp = get_fid_path($fileid);
		#$lines = file($fp);
		$linesall=file_get_contents_utf8($fp);
		$lines = explode("\n", $linesall);
		$numlines = sizeof($lines);
		$GroupArrayExist = [];
		$Version = 0;
		for ($i = 0; $i < 1; $i++){
			$linesplit = explode(";", $lines[$i]);
			$FE = $linesplit[$i];		
			$FE = preg_replace("/[^0-9a-zA-Z]/","",$FE);
			
			if (strpos($FE,'SCPNumber') !== false) {
				$Version = 1;
				echo("<div class=\"alert alert-warning\" role=\"alert\">ACHTUNG: Keine Meldertypenzuordnung in Datei vorhanden</div>");
			}

		}
		for ($i = 1; $i < $numlines - 1; $i++){
			$linesplit = explode(";", $lines[$i]);
			
			$Gruppe = 0;
			$Melder = 0;
			$Name = 0;
			$Leitung = 0;
			$Adresse = 0;
			$Art = 0;
			$Bezeichnung = 0;
			
			$Text_for_group = " ";
		
			if ($Version == 0){
				$Gruppe = $linesplit[0];
				$Melder = $linesplit[1];
				$Name = $linesplit[2];
				$Leitung = $linesplit[3];
				$Adresse = $linesplit[4];
				$Art = $linesplit[5];
				$Bezeichnung = $linesplit[6];
			}
			else{
				$line_cleaned = preg_replace("/[^0-9a-zA-Z;]/","",$lines[$i]);
				if ($result = preg_match($pattern_melder, $line_cleaned, $subpattern)){
					$Melder = $subpattern[3];
					$Gruppe = $subpattern[2];
					$Complete_Text = $subpattern[4]." ".$subpattern[5]." ".$subpattern[6];
					$Text_for_group = $Complete_Text;
					$Name = $subpattern[4];
					$Leitung = $subpattern[1];
				}	
			}
			
			#Gruppen eintragen
			if ((!in_array($Gruppe, $GroupArrayExist)) && ($Gruppe != "0")){
				array_push($GroupArrayExist, $Gruppe);


				mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppe', `text`= '$Text_for_group', `mandant` = '$userinfo[mandant]'");
				$Anzahl_Meldegruppen++;

			}

			$Type = -1;
			if (strpos($Art,'DKM') !== false) {
				$Type = "7002";
			}
			if (strpos($Art,'MULTI') !== false) {

				$Type = "7001";
			}

			#Meldertypen aus Datenbank
			$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Type' AND `hersteller`='Hekatron'");
			$mtyp=mysql_fetch_array($q4);

			#Melder in Datenbank eintragen
			if ((is_numeric($Melder)) && ($Melder != "0")){

				$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Gruppe', `melder`='$Melder', `text`='$Name', `art`='$Bezeichnung', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Leitung', `typ`='$Type', `adresse`='$Adresse', `serial`='$serial', `mandant` = '$userinfo[mandant]'");
				$Anzahl_Melder++;
			}
			
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
				if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1)){
					$i1=$mtyp[i1];
					$i2=$mtyp[i2];
					$i3=$mtyp[i3];
					$i4=$mtyp[i4];
				}
				
				#Prüfplan erstellen und eintragen
				$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$Gruppe."', `melder` = '".$Melder."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant` = '$userinfo[mandant]'";
				$qm=mysql_query($sql);
			}
	
		}
	}

	if (strpos($file[name],'Gruppen') !== false) {
		$fp = get_fid_path($fileid);
		$lines = file($fp);
		$numlines = sizeof($lines);
		$gruppenarray[0] = 0;
		for ($i = 1; $i < $numlines; $i++){
			$linesplit = explode(";", $lines[$i]);
			$gruppenarray[$i] = $linesplit[0];
			$Gruppe = $gruppenarray[$i];
			
			#Gruppen eintragen
			if ($Gruppe != 0){
				$TextGruppe = " ";
				mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppe', `text`= '$TextGruppe', `mandant` = '$userinfo[mandant]'");
				$Anzahl_Meldegruppen++;
			}
		}
	}


	if (strpos($file[name],'Konfiguration') !== false && $objekttexte_found == true) {
		$fp = get_fid_path($fileid);
		$lines = file($fp);
		$numlines = sizeof($lines);
		$gruppenarray[0] = 0;

		for ($i = 1; $i < $numlines; $i++){
			$linesplit = explode(";", $lines[$i]);
			
			$Name = $linesplit[0];
			$Gruppe = $linesplit[1];
			$Melder = $linesplit[2];
			$Ring = $linesplit[3];
			$Typ = $linesplit[4];
			$Subtyp = $linesplit[5];
			$Ringposition = $linesplit[6];
			
			#Melder gefunden
			if ($Melder != 0){
				
				$Type = -1;
				if (strpos($Subtyp,'Handfeuermelder') !== false) {
					$Type = "7002";
				}
				if (strpos($Subtyp,'Multikriterium') !== false) {
					$Type = "7001";
				}
				if (strpos($Subtyp,'Spezial') !== false) {
					$Type = "7001";
				}
				if (strpos($Subtyp,'Rauchansaugsystem') !== false) {
					$Type = "7001";
				}
				if (strpos($Subtyp,'Optisch') !== false) {
					$Type = "7001";
				}

				#Meldertypen aus Datenbank
				$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Type' AND `hersteller`='Hekatron'");
				$mtyp=mysql_fetch_array($q4);
				//preparing arrays of melder 

				#Melder in Datenbank eintragen
				$qdm = mysql_query("UPDATE `technik_melder` SET `art`='$Subtyp', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Ring', `typ`='$Type', `adresse`='$Ringposition', `serial`='$serial' WHERE `anlage`='$aid' AND `gruppe`='$Gruppe' AND `melder`='$Melder' AND `mandant` = '$userinfo[mandant]'");
				
				#Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
				$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$Gruppe."' AND `melder` = '".$Melder."' AND `mandant` = '$userinfo[mandant]'");
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


					#Wenn der Meldertyp einen vorgegebenen Prüfplan hat, dann diesen verwenden:
					if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1)){
						$i1=$mtyp[i1];
						$i2=$mtyp[i2];
						$i3=$mtyp[i3];
						$i4=$mtyp[i4];
					}
					
					#Prüfplan erstellen und eintragen
					$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$Gruppe."', `melder` = '".$Melder."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant` = '$userinfo[mandant]'";
					if($debug){
						
						echo $sql."<br>";
					}
					$qm=mysql_query($sql);
				}
			}	
				
		#AUSGANG gefunden
		/* if (strpos($Typ,'Ausgang') !== false){
				$Nummer = $Gruppe;
				$Ansteuerung = " ";
				$Ereignis = " ";
				#Ansteuerungen in Datenbank eintragen
				 // mysql_query("INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$Nummer', `text`='$Name', `ansteuerung`='$Ansteuerung', `ereignis`='$Ereignis', `mandant`='$userinfo[mandant]'");
				$Anzahl_Ansteuerungen++;
				#Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
				$qman=mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr` = '".$Nummer."' AND `mandant` = '$userinfo[mandant]'");
				if(mysql_num_rows($qman)==0){
					#Steuergruppen immer in jedem Quartal
					$i1=1;
					$i2=1;
					$i3=1;
					$i4=1;

					#Prüfplan erstellen und eintragen
					$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr` = '".$Nummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
					$qman=mysql_query($sql);
				}
			} 
			 */
	
		}
	}
}
msg($Anzahl_Melder." Melder Importiert");
msg($Anzahl_Meldegruppen." Meldegrupen Importiert");
msg($Anzahl_Ansteuerungen." Ansteuerungen Importiert");



}
?>

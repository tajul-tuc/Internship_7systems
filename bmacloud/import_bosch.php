<?

$debug=false;

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

//aid wird benötigt, da mehere Files pro Anlage gebraucht werden
function bosch_einlesen($fid,$aid)
{

global $userinfo, $debug,$programFilesFolder;
$qd1=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
$qd2=mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
$qd3=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");

    if (!$handle2 = fopen(get_fid_path($fid), "r+")) {
         print "Kann die Datei $fname nicht oeffnen";
         exit;
    }
	
$lines = file_get_contents(get_fid_path($fid));
		$qfn=mysql_query("SELECT * FROM `files` WHERE `mandant`='$userinfo[mandant]' AND `fid`='$fid'  AND `ordner` = '$programFilesFolder' ");
		$qfnu=mysql_fetch_array($qfn);
		$filename = $qfnu[name];
		
		if (strpos($filename,'.csv') !== false){
		
			$zeile = explode("\n", $lines);
			$count = count($zeile);
		
			if($debug)
			{
				echo("Import: $fid ".$zeile[0]);
			}



			for ($i=1; ($i<=($count)); $i++)
			{
			
				$feld = explode(";", utf8_encode($zeile[$i]));
				$gruppe = trim($feld[0]); //BKS-Adresse
				$melder = trim($feld[1]); //BKS-UA
				$bosch_adresse = trim($feld[2]);
				$bosch_ua = trim($feld[3]);
				$zd = trim($feld[4]);
				$gebaeude = trim($feld[5]);
				$ebene = trim($feld[6]);
				$raumnr = trim($feld[7]);
				$raumbez = trim($feld[8]);
				$melderart = trim($feld[9]);
				$typ = trim($feld[10]);
				$zentrale = trim($feld[11]);
				$anlagenverbund = trim($feld[12]);
				$fw_einsatzraum = trim($feld[13]);
				$ring = trim($feld[14]);
				
				$text = $ebene.$raumnr.' - '.$raumbez;
				$adresse = $bosch_adresse.'/'.$bosch_ua;
				
				//Sonderregel RAS
				if($melderart=='Rauchansaugsystem IT'){
					$text .= ' - '.$typ;
					$typ='Rauchansaugsystem IT';
					}
				
						//Prüfe ob es die Gruppe schon gibt
			if($gruppe>0)
			{
			$q=mysql_query("SELECT * FROM `technik_gruppe` WHERE `mandant`='$userinfo[mandant]' AND `anlage`='$aid' AND `gruppe`='$gruppe'");
				if(mysql_num_rows($q)==0)
				{
				if($debug)
				{echo("INSERT INTO `technik_gruppe` SET `mandant`='$userinfo[mandant]', `anlage`='$aid', `gruppe`='$gruppe', `text`='$gtext'<br>");}

				$q2=mysql_query("INSERT INTO `technik_gruppe` SET `mandant`='$userinfo[mandant]', `anlage`='$aid', `gruppe`='$gruppe', `text`='$text'");
				} else {
				$q2=mysql_query("UPDATE `technik_gruppe` SET `text`='$text' WHERE `mandant`='$userinfo[mandant]' AND `anlage`='$aid' AND `gruppe`='$gruppe'");
				}
				
				
				$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$typ' AND `hersteller`='BOSCH'");
				if(mysql_num_rows($q4)==1)
				{
				$mtyp=mysql_fetch_array($q4);
				
				$sql = "INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$gruppe', `melder`='$melder', 
	`text`='$text', `art`='$art', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$ring', `typ`='$typ', `adresse`='$adresse', `mandant`='$userinfo[mandant]'";
				mysql_query($sql);

				
				//Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
	$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$gruppe."' AND `melder` = '".$melder."' AND `mandant` = '$userinfo[mandant]'");
	if(mysql_num_rows($qm)==0)
	{
	$i1=0;
	$i2=0;
	$i3=0;
	$i4=0;
	$mod = ($melder%4);

	if($mod==1){$i1='1';}
	if($mod==2){$i2='1';}
	if($mod==3){$i3='1';}
	if($mod==0){$i4='1';}

	//Wenn der Meldertyp einen vorgegebenen Prüfplan hat, dann diesen verwenden:
	if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1))
	{
		$i1=$mtyp[i1];
		$i2=$mtyp[i2];
		$i3=$mtyp[i3];
		$i4=$mtyp[i4];
	}

	$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$gruppe."',
	`melder` = '".$melder."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
	$qm=mysql_query($sql);
	}
				
				$fehler=0;
			} else {
			err("Der Meldertyp $typ von Melder $gruppe/$melder wurde nicht gefunden");
			}
			}
				
			}
	}
	elseif((strpos($filename,'.mpp') !== false)||(strpos($filename,'.MPP') !== false)) {
		MPP($lines);
	}
	elseif((strpos($filename,'.Data') !== false) || (strpos($filename,'.DATA' !== false)) || (strpos($filename,'.data' !== false))){
		DTA($lines);
	}
	else{
		err("Falsche Datei");
	}

	//delete  groups without any melder
	$qmg=mysql_query("SELECT * FROM `technik_gruppe` WHERE `anlage`='$aid' AND `useradd`='0' AND `mandant` = '$userinfo[mandant]'");
	
	while($groups=mysql_fetch_array($qmg)){
		$sql ="SELECT * FROM `technik_melder` WHERE `anlage`='$aid' AND `gruppe`='$groups[gruppe]' AND `mandant` = '$userinfo[mandant]'";
		$qmd=mysql_query($sql);
		$numMelder = mysql_num_rows($qmd);
		if($numMelder ==0){
			$sql="DELETE FROM `technik_gruppe` WHERE `id`='$groups[id]' AND `anlage`='$aid' AND `mandant` = '$userinfo[mandant]' LIMIT 1";
			$dmg=mysql_query($sql);
	
		}
	}
}

//--------------------DATA--------------------------------------------
function DTA($lines){
	global $aid, $userinfo, $debug;

	$lines = str_replace("<","---",$lines);
	$lines = str_replace(">","---",$lines);
	$zeile = explode("\n", $lines);
	
	$CurrentGroup = 0;
	$CurrentGroupName = "";
	$CurrentMelder = 0;
	$CurrentMelderName = "";
	$pattern_group = '@(.*)siNumber(.*?)(\d+)(.*)siNumber(.*)@isx';
	$pattern_groupname = '@(.*)rpsDisplayName="(.*)"---@isx';
	$pattern_melder = '@(.*)subNumber(.*?)(\d+)(.*)subNumber(.*)@isx';
	$pattern_meldername = '@(.*)rpsDisplayName="(.*)"---@isx';
	
	$count = count($zeile);
	for ($i=1; ($i<=($count)); $i++){
		$Previous_Line = $zeile[$i-1];
		$Current_Line = $zeile[$i];

		if (strpos($Previous_Line,"---GROUP ") !== false){
			if($result = preg_match($pattern_groupname, $Previous_Line, $subpattern)){
				$CurrentGroupName = $subpattern[2];
			}
			if($result = preg_match($pattern_group, $Current_Line, $subpattern)){
				$CurrentGroup = $subpattern[3];
				mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$CurrentGroup', `text`= '$CurrentGroupName', `mandant` = '$userinfo[mandant]'");
				$Anzahl_Meldegruppen++;
			}
		}	
		if (strpos($Previous_Line,"---POINT ") !== false){
			if($result = preg_match($pattern_meldername, $Previous_Line, $subpattern)){
				$CurrentMelderName = $subpattern[2];
			}
			if($result = preg_match($pattern_melder, $Current_Line, $subpattern)){
				$CurrentMelder = $subpattern[3];
				
				$Type = -1;
		
				#Meldertypen aus Datenbank
				$sql="SELECT * FROM `technik_meldertypen` WHERE `typ`='$Type' AND `hersteller`='BOSCH'";
				$q4=mysql_query($sql);
				$mtyp=mysql_fetch_array($q4);

				#Melder eintragen
				if (($CurrentMelder != 0) && ($CurrentGroup != 0)){

					$sql = "INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$CurrentGroup', `melder`='$CurrentMelder', `text`='$CurrentMelderName', `art`='$bezeichnung', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$ring', `typ`='$Type', `adresse`='$adresse', `mandant`='$userinfo[mandant]'";
					mysql_query($sql);
					$Anzahl_Melder++;
					
					#Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
					$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$CurrentGroup."' AND `melder` = '".$CurrentMelder."' AND `mandant` = '$userinfo[mandant]'");
					if(mysql_num_rows($qm)==0){
						$i1=0;
						$i2=0;
						$i3=0;
						$i4=0;
						$mod = ($CurrentMelder%4);

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
						$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$CurrentGroup."', `melder` = '".$CurrentMelder."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant` = '$userinfo[mandant]'";
						$qm=mysql_query($sql);
					}
				}
			}
		}	
	}
	

	msg("$Anzahl_Meldegruppen Meldergruppen importiert");
	msg("$Anzahl_Melder Melder importiert");
} 

//--------------------MPP--------------------------------------------
function MPP($lines){
	global $aid, $userinfo, $debug;

	$GroupArrayExist = array();
	$zeile = explode("\n", $lines);
	$count = count($zeile);
	$Anzahl_Meldegruppen = 0;
	$Anzahl_Melder = 0;

	for ($i=7; $i < $count - 1; $i++){
		$feld = explode("\t", utf8_encode($zeile[$i]));
		$gruppe = $feld[0];
		$melder = $feld[1];
		$adresse = $feld[3];
		$ring = $feld[4];
		$bezeichnung = $feld[5];
		$typ = $feld[6];
		$name = $feld[10];
		$meldertyp = $feld[8]; 
		$gText = "Gruppe ".$gruppe;
		#Gruppen eintragen
		if ((!in_array($gruppe, $GroupArrayExist)) && ($melder != 0)){
			array_push($GroupArrayExist, $gruppe);
			mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$gruppe', `text`= '$gText', `mandant` = '$userinfo[mandant]'");
			$Anzahl_Meldegruppen++;
		}

		$Type = -1;
		
		#Handmelder
		if ((strpos($typ,'MANUALCALLPOINT') !== false) ||
			(strpos($bezeichnung,'DM/SM/FMC210/FMC420') !== false) ||
			(strpos($bezeichnung,'Druckknopfmelder') !== false)){ 
				$Type = "BOSCH1";
		}
		
		#Multimelder
		if ((strpos($typ,'MULTISENSOR') !== false) ||
			(strpos($typ,'MP_FT_GENERAL_DETECTOR') !== false) ||
			(strpos($bezeichnung,'Mehrsensormelder') !== false) ||
			(strpos($bezeichnung,'Multisensormelder') !== false)){ 
				$Type = "BOSCH2";
		}
	
		#RAS
		if (strpos($typ,'RAS') !== false) { 
				$Type = "BOSCH3";
		}
		
		#Optischer Melder
		if ((strpos($typ,'OPTICAL') !== false) ||
			(strpos($typ,'MP_FT_NAC_STROBE') !== false) ||
			(strpos($bezeichnung,'OT400/420') !== false) ||
			(strpos($bezeichnung,'O400/420') !== false) ||
			(strpos($bezeichnung,'Optischer') !== false)){ 
				$Type = "BOSCH4";
		}
		
		#Sounder
		if ((strpos($typ,'SOUNDER') !== false) ||
			(strpos($typ,'MP_FT_UNLOCKAPPLIANCE') !== false) ||
			(strpos($bezeichnung,'MSS401') !== false)){ 
				$Type = "BOSCH5";
		}

		#Thermomelder
		if ((strpos($typ,'THERMODIFFERENTIAL') !== false) ||
			(strpos($bezeichnung,'Thermodifferential') !== false) ||
			(strpos($bezeichnung,'T400/420') !== false) ||
			(strpos($bezeichnung,'rmemelder') !== false)){ 
			$Type = "BOSCH6";
		}

		#Funkmelder
		if (strpos($bezeichnung,'Funkmelder') !== false) { 
				$Type = "BOSCH7";
		}
		
		#Steurmodul
		if ((strpos($bezeichnung,'FLM-420-RLV1-D') !== false) ||
			(strpos($bezeichnung,'NSB100') !== false) ||
			(strpos($bezeichnung,'NAK100') !== false) ||
			(strpos($bezeichnung,'NKK100') !== false) ||
			(strpos($bezeichnung,'KA1-KA2/KR-R-RR') !== false) ||
			(strpos($bezeichnung,'SA-SB') !== false) ||
			(strpos($bezeichnung,'Relais') !== false)){
				$Type = "BOSCH8";	
		}
		
		#Undefined, no melder
		if ((strpos($typ,'MP_FT_UNDEFINED') !== false) ||
			(strpos($typ,'MP_FT_SYSTEMSTATUS') !== false) ||
			(strpos($typ,'MP_FT_CANBUS') !== false) ||
			(strpos($typ,'MP_FT_NETWORK')!== false) ||
			(strpos($typ,'MP_FT_NETADDRESS')!== false) ||
			(strpos($bezeichnung,'UEA-UEB')!== false) ||
			(strpos($bezeichnung,'FBF100')!== false) ||
			(strpos($bezeichnung,'Eingang')!== false)){
				$Type = "-2";
		}

		
		
		#Meldertypen aus Datenbank
		$sql="SELECT * FROM `technik_meldertypen` WHERE `typ`='$Type' AND `hersteller`='BOSCH'";
		$q4=mysql_query($sql);
		$mtyp=mysql_fetch_array($q4);

		#Melder eintragen
		if (($melder != 0) && ($gruppe != 0) && ($Type != -2)){
			$sql = "INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$gruppe', `melder`='$melder', `text`='$name', `art`='$bezeichnung', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$ring', `typ`='$Type', `adresse`='$adresse', `mandant`='$userinfo[mandant]'";
			mysql_query($sql);
			$Anzahl_Melder++;
			#Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
			$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$gruppe."' AND `melder` = '".$melder."' AND `mandant` = '$userinfo[mandant]'");
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
				$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$gruppe."', `melder` = '".$melder."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant` = '$userinfo[mandant]'";
				$qm=mysql_query($sql);
			}
		}
	}
	msg("$Anzahl_Meldegruppen Meldergruppen importiert");
	msg("$Anzahl_Melder Melder importiert");
}   


//--------------------Gruppenimport--------------------------------------------

function gruppen($fname2)                                                          #Datei ist vorhanden
{
		global $aid, $userinfo, $debug;
		$grp2=file_get_contents($fname2);

	//Definition von Arraygroessen
		$doppel=0;

		$zeile = explode("\n", $grp2);
		$count = count($zeile);


		for ($i=1; ($i<=($count)); $i++)
		{
		
		if($debug)
{echo("Gruppe: $gruppe Zeile: $zeile[$i]<br>");}

			$feld = explode("\t", $zeile[$i]);
			$gruppe = trim($feld[5]);
			$melder = trim($feld[6]);
			$text = utf8_encode(trim($feld[10]));
			$gtext = utf8_encode(trim($feld[8]));
			$adresse = trim($feld[7]);
			$ring = trim($feld[2]);
			$typ = trim($feld[19]);
			

		}
}
	
	
//.Teil--------------------SteuerGruppenimport--------------------------------------------

function sgruppen($fname2)                                                        #Datei ist vorhanden
{
		global $aid, $userinfo, $debug;
		$grp2=file_get_contents($fname2);

	//Definition von Arraygroessen
		$doppel=0;

		$zeile = explode("\n", $grp2);
		$count = count($zeile);

		for ($i=1; ($i<=($count)-1); $i++)
		{
		$text = "";
		if($debug)
		{echo("Steuerung: $zeile[$i]<br>");}

			$feld = explode("\t", $zeile[$i]);
			$text = utf8_encode(trim($feld[9]));
			if($text=="")
			{
			$text = utf8_encode(trim($feld[13]));
			}
			
			$tansteuerung = utf8_encode(trim($feld[4]));
			$ereignis = utf8_encode(trim($feld[4]));
			$fehler=0;
			
		if(($text!="")&&($tansteuerung!=""))
		{
		$sql = "INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$nr', `g1`='$von', `text`='$text', `ansteuerung`='$tansteuerung', `ereignis`='$ereignis', `mandant`='$userinfo[mandant]'";
$c_stg++;
if($debug)
{echo("Sub: ".$sql."<br>");}
$q4=mysql_query($sql);

//Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
$qman=mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `mandant`='$userinfo[mandant]' AND `nr` = '".$nr."' AND `mandant` = '$userinfo[mandant]'");
if(mysql_num_rows($qman)==0)
{

//Steuergruppen immer in jedem Quartal
$i1=1;
$i2=1;
$i3=1;
$i4=1;

$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `mandant`='$userinfo[mandant]', `nr` = '".$nr."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4'";
$qman=mysql_query($sql);
}
}
}
}


?>



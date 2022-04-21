<?

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function file_get_contents_utf8($fn) {
		 $content = file_get_contents($fn);
		  return mb_convert_encoding($content, 'UTF-8',
			  mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
	}

function notifier_einlesen_xps($fid, $aid){
	global $aid, $userinfo,$programFilesFolder;
	
	$Anzahl_Melder = 0;
	$q=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$q=mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");

	
	
$c = 0;
$fileid = 0;

$debug = false;

#File suchen
$q=mysql_query("SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='$programFilesFolder' AND `mandant`='$userinfo[mandant]'");
if($debug)
{
echo("SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='$programFilesFolder' AND `mandant`='$userinfo[mandant]'");
}

$FileIDs = array();
$FileCounter = 0;

#File einlesen
while($file=mysql_fetch_array($q))
{

    if($debug)
{echo "<br>$c: File: $file[name]<br>\n";echo "<br>aid: $file[aid]<br>\n";echo "<br>fid: $file[fid]<br>\n";}
$FileIDs[$FileCounter] = $file[fid];
$FileCounter++;


$c++;
}

$New_Group_Number = 0;

for ($ic = 0; $ic < $FileCounter; $ic++){
	$fp = get_fid_path($FileIDs[$ic]);


	   if($debug)
	   {echo "<br>$ic - $FileCounter Fileid: $FileIDs[$ic]<br>fp: $fp<br>";}



	#Zeile für Zeile das File durchgehen
	#$lines = file_get_contents($fp);

	
	$line = file_get_contents_utf8($fp);
	$line_new = str_replace("<", "---", $line);
	$single_line = explode("UnicodeString=\"", $line_new);
	
	$numberoflines = sizeof($single_line);
	for ($i = 2; $i < $numberoflines; $i++){
		$cur_line = $single_line[$i];
		#echo ("$cur_line<br>");
		$data_cur_line = explode("\" />", $cur_line);
		$data_xps = $data_cur_line[0];
		
		$elements = preg_split('/\s+/', $data_xps);
		
		#Melder gefunden
		if (strpos($elements[1],'S') !== false) {
			$MelderGruppe = $elements[0];
			$MelderGruppeSplit = explode ("/", $MelderGruppe);
			$Melder = $MelderGruppeSplit[1];
			$Gruppe = $MelderGruppeSplit[0];
			
			$Art;
			
			#Bezeichnung raussuchen
			$BezeichnungSplit1 = explode ("S", $data_xps, 2);
			$BezeichnungSplit2 = explode ("Netzwerkgruppe:", $BezeichnungSplit1[1]);
			$Bezeichnung;

			if (strstr($BezeichnungSplit2[0],'Optisch') !== false) {
				$Art = "Optisch";
				$Bezeichnung = substr($BezeichnungSplit2[0], 0, -7);
			}
			if (strpos($BezeichnungSplit2[0],'OPTIPLEX') !== false) {
				$Art = "Optisch";
				$Bezeichnung = substr($BezeichnungSplit2[0], 0, -8);
			}
			if (strpos($BezeichnungSplit2[0],'MULTI') !== false) {
				$Art = "Multi";
				$Bezeichnung = substr($BezeichnungSplit2[0], 0, -5);
			}
			if (strpos($BezeichnungSplit2[0],'THERMO') !== false) {
				$Art = "Thermo";
				$Bezeichnung = substr($BezeichnungSplit2[0], 0, -6);
			}
			
			
			#echo "Melder: $Melder, Gruppe: $Gruppe, Name: $Bezeichnung, Art: $Art<br>";
			
			$Meldertext = " ";
			#Gruppen eintragen
			if ($New_Group_Number < $Gruppe){ 
				$qg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppe', `text`='$Meldertext', `mandant`='$userinfo[mandant]'");
				$New_Group_Number++;
			}
			#Meldertypen klassifizieren
			$Typ = "RZ";
			if ($Art == "Optisch"){
				$Typ = "5001";
			}
			if ($Art == "DKM"){
				$Typ = "5002";
			}
			if ($Art == "Multi"){
				$Typ = "5003";
			}
			if ($Art == "AKUSTIK"){
				$Typ = "5004";
			}
			if ($Art == "Steuer"){
				$Typ = "5005";
			}
			if ($Art == "Thermo"){
				$Typ = "5006";
			}
			if ($Art == "BOOSTER"){
				$Typ = "5005";
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
			$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Gruppe', `melder`='$Melder', 
			`text`='$Bezeichnung', `art`='$Art', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='', `typ`='$Typ', `adresse`='', `serial`='$serial', `mandant`='$userinfo[mandant]'");
			$Anzahl_Melder++;



			#Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
			$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$Gruppe."' AND `melder` = '".$Melder."' AND `mandant` = '$userinfo[mandant]'");
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





			}


		msg($Anzahl_Melder." Melder Importiert");

}


?>



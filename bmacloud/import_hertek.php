<?

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function file_get_contents_utf8($fn) {
		 $content = file_get_contents($fn);
		  return mb_convert_encoding($content, 'UTF-8',
			  mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
	}

function hertek_einlesen($fid, $aid){
	global $aid, $userinfo,$programFilesFolder;

	$Anzahl_Melder = 0;
	$Anzahl_Meldergruppen = 0;
	$Anzahl_Ansteuerungen = 0; 
	$q=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$q=mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$q=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	
$c = 0;
$fileid = 0;

$delete_first_group_line = 0;

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

$pattern_melder = '@(\d*)\,(\d*)\,(\d*)@i';
$pattern_group = '@(\d*)\,(\w*)@i';
$pattern_end = '@Teilnehmerzusammenfassung@i';

$Teilnehmerzusammenfassung_Erreicht = 0;

$pattern_eingang = '@(.*)Eingang(.)\(INPUT\);(.*);(.*);(.*);(.*);(.*);(.*);@isx';
$pattern_ring = '@(.*)Ring(.)\(LOOP\);(.*);(.*);(.*);(.*);(.*);(.*);(.*)@isx';

for ($ic = 0; $ic < $FileCounter; $ic++){
	$fp = get_fid_path($FileIDs[$ic]);
		
		$Teilnehmerzusammenfassung_Erreicht = 0;
		
		$line = file($fp);
		$numlines = sizeof($line);

		#Check filetype
		$first_line_test = utf8_encode($line[0]);

		$Start_Counter_Value = 4;
		if (preg_match($pattern_eingang, $first_line_test)){
			$Start_Counter_Value = 0;
		}
		if (preg_match($pattern_ring, $first_line_test)){
			$Start_Counter_Value = 0;
		}
		
		for ($i = $Start_Counter_Value; $i < $numlines; $i++){
			$lineok = utf8_encode($line[$i]);
			
			if (preg_match($pattern_end, $lineok)){
				$Teilnehmerzusammenfassung_Erreicht = 1;
			}
			

			if ((preg_match($pattern_melder, $lineok)) && ($Teilnehmerzusammenfassung_Erreicht == 0)){
					$Elements = explode(",", $lineok);





					$Meldergruppe = $Elements[3];
					$Meldernummer = $Elements[7];
					$Knoten = $Elements[0];
					$Loop = $Elements[1];
					$Adresse = $Elements[2];
					$Text = $Elements[4];
					$Type = $Elements[5];
					$Info = $Elements[6];




					#Meldertypen klassifizieren
					$Typ = "9000";
					if ($Info == "2"){ # Optischer Rauchmelder
						$Typ = "9001";
					}
					if ($Info == "3"){ # Ionisationsmelder
						$Typ = "9001";
					}
					if ($Info == "28"){ # Rauchmelder
						$Typ = "9001";
					}
					if ($Info == "6"){ # Handmelder
						$Typ = "9002";
					}
					if ($Info == "15"){ # Druckknopfmelder
						$Typ = "9002";
					}
					if ($Info == "14"){ # Relais
						$Typ = "9003";
					}
					if ($Info == "19"){ # Koppler
						$Typ = "9003";
					}
					if ($Info == "4"){ # Multimelder
						$Typ = "9004";
					}
					if ($Info == "33"){ # Multimelder
						$Typ = "9004";
					}
					if ($Info == "47"){ # Multimelder
						$Typ = "9004";
					}
					if ($Info == "5"){ # Wärmemelder
						$Typ = "9005";
					}
					



					if (($Meldergruppe != 0) && ($Meldernummer != 0)){
						#Melderdetails aus Datenbank holen
						$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='Hertek'");
						$mtyp=mysql_fetch_array($q4);

						$art = $mtyp[kurztext];
						$adresse = $t[adresse];
						$serial = $t[serial];

						#Melder in Datenbank eintragen
						$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Meldergruppe', `melder`='$Meldernummer', 
						`text`='$Text', `art`='$Type', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Loop', `typ`='$Typ', `adresse`='$Adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");
						$Anzahl_Melder++;
								
						#Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
						$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$Meldergruppe."' AND `melder` = '".$Meldernummer."' AND `mandant` = '$userinfo[mandant]'");
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

						#Wenn der Meldertyp einen vorgegebenen Prüfplan hat, dann diesen verwenden:
						if(($mtyp[i1]==1)||($mtyp[i2]==1)||($mtyp[i3]==1)||($mtyp[i4]==1))
						{
							$i1=$mtyp[i1];
							$i2=$mtyp[i2];
							$i3=$mtyp[i3];
							$i4=$mtyp[i4];
						}
						#Prüfplan eintragen in Datenbank
						$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$Meldergruppe."',
						`melder` = '".$Meldernummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
						$qm=mysql_query($sql);
						}
					}
			}
			elseif ((preg_match($pattern_group, $lineok)) && ($Teilnehmerzusammenfassung_Erreicht == 0)){
				#Meldergruppen eintragen
				if ($delete_first_group_line != 0){
					$Elements = explode(",", $lineok);
					
					$Gruppentext = " ";
					$Gruppennummer = $Elements[0];
					$Gruppentext = $Elements[1];

					if ($Gruppennummer != 0){
						$mg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppennummer', `text`='$Gruppentext', `mandant`='$userinfo[mandant]'");
						$Anzahl_Meldergruppen++;
					}
				}
				else{
					$delete_first_group_line = 1;
				}
			}
			elseif (preg_match($pattern_eingang, $lineok, $match)){
				$Eingang = $match[3];
				$Text = "Eingang - ".$match[6];
				$Ereignis = $match[5];
				$Ansteuerung = $match[7];
				
				#Ansteuerung in die Datenbank eintragen
				mysql_query("INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$Eingang', `text`='$Text', `ansteuerung`='$Ansteuerung', `ereignis`='$Ereignis', `mandant`='$userinfo[mandant]'");
				$Anzahl_Ansteuerungen++;

				#Prüfplan berechnen für die Ansteuerungen, nur wenn es noch keine manuellen Zeilen dafür gibt
				$qman=mysql_query("SELECT * FROM  `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr`='$Eingang' AND `mandant`='$userinfo[mandant]'");
				
				if(mysql_num_rows(qman)==0){
					#Steuergruppen immer in jedem Quartal
					$i1=1;
					$i2=1;
					$i3=1;
					$i4=1;
					
					#Prüfplan erstellen und eintragen
					$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr`='$Eingang', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
					$qman=mysql_query($sql);
				}
			}	
			elseif (preg_match($pattern_ring, $lineok, $match)){
				$Eingang = $match[3];
				$Text = "Ring - ".$match[6];
				$Ereignis = " ";
				$Ansteuerung = " ";
				
				#Ansteuerung in die Datenbank eintragen 
				mysql_query("INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$Eingang', `text`='$Text', `ansteuerung`='$Ansteuerung', `ereignis`='$Ereignis', `mandant`='$userinfo[mandant]'");
				$Anzahl_Ansteuerungen++;
				
				#Prüfplan berechnen für die Ansteuerungen, nur wenn es noch keine manuellen Zeilen dafür gibt
				$qman=mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr`='$Eingang' AND `mandant`='$userinfo[mandant]'");
				
				if(mysql_num_rows(qman)==0){
					#Steuergruppen immer in jedem Quartal
					$i1=1;
					$i2=1;
					$i3=1;
					$i4=1;
					
					#Prüfplan erstellen und eintragen
					$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr`='$Eingang', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
					$qman=mysql_query($sql);
				}
			}
		}
	}

	msg($Anzahl_Melder." Melder Importiert");
	msg($Anzahl_Meldergruppen." Meldergruppen Importiert");
	msg($Anzahl_Ansteuerungen." Ansteuerungen Importiert");
	

}


?>



<?
mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function mbclassic_einlesen($fid, $aid){
	global $aid, $userinfo,$programFilesFolder;

	$Anzahl_Meldegruppen = 0;
	$Anzahl_Melder = 0;

	$Array_Gruppennummern = [];
	$Array_Gruppennamen = [];
	$Array_Gruppenbereich = [];
	$Array_Gruppentyp = [];
	$Array_Gruppennamen_Typ = [];
	
	$Melder_Counter_Nummer = 1;
	
	$Offset = 1100;
	
	#Alte Eintragungen aus der Datenbank loeschen
	$qd1=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$qd2=mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$qd3=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$qd4=mysql_query("DELETE FROM `technik_ansteuer` WHERE `anlage` = '$aid' AND `mandant` = '$userinfo[mandant]'");
	
	$q=mysql_query("SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='$programFilesFolder' AND `mandant`='$userinfo[mandant]'");

	while($file=mysql_fetch_array($q)){

		$fileid = $file[fid];
		
		$fp = get_fid_path($fileid);
		$lines = file($fp);
		$numlines = sizeof($lines);
		
		for ($i = 0; $i < $numlines; $i++){
			$conv_lines_utf8 = mb_convert_encoding($lines[$i], 'UTF-8', mb_detect_encoding($lines[$i], 'UTF-8, ISO-8859-1', true));
			$split_temp = explode(";", $conv_lines_utf8);
			$MB_Nummer = $split_temp[0];
			$Text = $split_temp[1];
			$Text = trim($Text);
			$Gruppentyp = $split_temp[2];
			$Bereich = $split_temp[4];
			
			$Text_Compare = $split_temp[3];
			
			#Only in the range between 1100 and 1611 in every system
			if (($MB_Nummer >= $Offset) && ($MB_Nummer <= 1611)){
				$Gruppennummer = $MB_Nummer - $Offset;
				array_push($Array_Gruppennummern, $Gruppennummer);
				array_push($Array_Gruppennamen, $Text);
				array_push($Array_Gruppenbereich, $Bereich);
				array_push($Array_Gruppentyp, $Gruppentyp);
				
				$Mixed_Name_Type = $Bereich.";".$Text;
				array_push($Array_Gruppennamen_Typ, $Mixed_Name_Type);

				$mg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppennummer', `text`='$Text', `mandant`='$userinfo[mandant]'");
				$Anzahl_Meldegruppen++;
			}

			#Melder IDs only in the range between 1618 and 4800, Funkmelder IDs only in the range between 5633 and 6688 in every system
			if ((($MB_Nummer >= 1618) && ($MB_Nummer <= 4800)) || (($MB_Nummer >= 5633) && ($MB_Nummer <= 6688))){
				$Search_Name = trim($Text_Compare);

				$Search_Name_Mixed = $Bereich.";".$Search_Name;
				$RV = array_search($Search_Name_Mixed, $Array_Gruppennamen_Typ);

				$Current_Element = $RV;
				
				if (in_array($Search_Name_Mixed, $Array_Gruppennamen_Typ)){
					$Corresponding_Group_Number = $Array_Gruppennummern[$RV];
					$Corresponding_Group_Area = $Array_Gruppenbereich[$RV];
					
					$Address = "$Melder_Counter_Nummer";
					$Type = preg_replace("/[^0-9]/", "", $Gruppentyp);
					$hersteller = "MBClassic";
					$Ring = "$Bereich";
					
					#Melderdetails aus Datenbank holen
					$melderabfrage=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Type' AND `hersteller`='MBClassic'");
					$mtyp=mysql_fetch_array($melderabfrage);

					#Melder in Datenbank eintragen
					$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Corresponding_Group_Number', `melder`='$Melder_Counter_Nummer', `text`='$Text', `art`='$mtyp[text]', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Ring', `typ`='$Type', `adresse`='$Address', `serial`='$serial', `mandant`='$userinfo[mandant]'");

					$Anzahl_Melder++;

					#Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
					$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$Corresponding_Group_Number."' AND `melder` = '".$Melder_Counter_Nummer."' AND `mandant` = '$userinfo[mandant]'");
					if(mysql_num_rows($qm)==0){

						$i1=0;
						$i2=0;
						$i3=0;
						$i4=0;

						$mod = ($Melder_Counter_Nummer%4);
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
						$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$Corresponding_Group_Number."',
						`melder` = '".$Melder_Counter_Nummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
						$qm=mysql_query($sql);
					}
					$Melder_Counter_Nummer++;
				}
			}


		}
	
	}
	
	msg($Anzahl_Melder." Melder Importiert");
	msg($Anzahl_Meldegruppen." Meldegrupen Importiert");

}
?>

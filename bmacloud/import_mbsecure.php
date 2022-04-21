<?php
mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function mbsecure_einlesen($fid, $aid)
{
	global $aid, $userinfo,$programFilesFolder;

	$Anzahl_Meldegruppen = 0;
	$Anzahl_Melder = 0;
	$gruppe_branch=array();
	$viewguard=array();
	$viewguard_gruppe=array();
	$pir_img = 101010;
	$digital_img= 202020;
	$analoge_img=303030;
	$tamper_img=404040;
	$sirene_img=505050;
	$Melder_Counter_Nummer = 1;
	
	#Alte Eintragungen aus der Datenbank loeschen
	$qd1=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$qd2=mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$qd3=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$qd4=mysql_query("DELETE FROM `technik_ansteuer` WHERE `anlage` = '$aid' AND `mandant` = '$userinfo[mandant]'");
	
	$q=mysql_query("SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='$programFilesFolder' AND `mandant`='$userinfo[mandant]'");

	while($file=mysql_fetch_array($q))
	{
		$fileid = $file['fid'];
		$fp = get_fid_path($fileid);
		$lines = file($fp);
		$numlines = sizeof($lines);
		
		for ($i = 0; $i < $numlines; $i++)
		{
			$conv_lines_utf8 = mb_convert_encoding($lines[$i], 'UTF-8', mb_detect_encoding($lines[$i], 'UTF-8, ISO-8859-1', true));
			$split_temp = explode(";", $conv_lines_utf8);
			$dp_root = trim($split_temp[0]);
			$dp_name = trim($split_temp[1]);
			$dp_partition = trim($split_temp[2]);
			$dp_discription = trim($split_temp[3]);
			$dp_branch_num = trim($split_temp[5]);
			$dp_num = trim($split_temp[6]);
			$dp_num2 = trim($split_temp[7]);
			$dp_type = trim($split_temp[8]);
			$dp_partition_num = trim($split_temp[9]);
			
			if ($dp_partition =="DetectorGroup")
				{
					$gruppenummer = $dp_num;
					$gruppe_name= $dp_name;
					$mg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$gruppenummer', `text`='$gruppe_name', `mandant`='".$userinfo['mandant']."'");
					$Anzahl_Meldegruppen++;
					$gruppe_branch[$dp_root]=$gruppenummer;
				}

			if ($dp_partition =="VirtualPIR")
				{
					$gruppenummer= $gruppe_branch[$dp_num];
					if ($gruppenummer!=0 ||$gruppenummer!='' )
					{
						$bus_line = $dp_branch_num;
						for ($j = 0; $j < $numlines; $j++)
							{
							$conv_lines_utf8 = mb_convert_encoding($lines[$j], 'UTF-8', mb_detect_encoding($lines[$j], 'UTF-8, ISO-8859-1', true));
							$split_temp = explode(";", $conv_lines_utf8);
							$dp_root = trim($split_temp[0]);
							$dp_name = trim($split_temp[1]);
							$dp_partition = trim($split_temp[2]);
							$dp_discription = trim($split_temp[3]);
							$dp_branch_num = trim($split_temp[5]);
							$dp_num = trim($split_temp[6]);
							$dp_num2 = trim($split_temp[7]);
							$dp_type = trim($split_temp[8]);
							$dp_partition_num = trim($split_temp[9]);
							if ($dp_root==$bus_line)
							{
							$viewguard[$dp_root]=$dp_name;
							$viewguard_gruppe[$dp_root]=$gruppenummer;
							}
							}
					}
				}
			if ($dp_partition =="VirtualPeripheral")
				{
					$gruppenummer = $dp_num;
					$viewguard[$dp_root]=$dp_name;
					$viewguard_gruppe[$dp_root]=$gruppenummer;
				}
		}
		for ($i = 0; $i < $numlines; $i++)
		{
			$conv_lines_utf8 = mb_convert_encoding($lines[$i], 'UTF-8', mb_detect_encoding($lines[$i], 'UTF-8, ISO-8859-1', true));
			$split_temp = explode(";", $conv_lines_utf8);
			$dp_root = trim($split_temp[0]);
			$dp_name = trim($split_temp[1]);
			$dp_partition = trim($split_temp[2]);
			$dp_discription = trim($split_temp[3]);
			$dp_branch_num = trim($split_temp[5]);
			$dp_num = trim($split_temp[6]);
			$dp_num2 = trim($split_temp[7]);
			$dp_type = trim($split_temp[8]);
			$dp_partition_num = trim($split_temp[9]);
				#die türen sind von 5608 - 6631
				# die melder sind VirtualInput
				if($dp_partition =="VirtualInput" || $dp_partition =="VirtualPIR")
				{
					$meldernummer= $dp_type;
					$gruppenummer= $gruppe_branch[$dp_num];
					$ring=0;
					if($viewguard[$dp_branch_num]!=''&& $gruppenummer==0)
					{
					$gruppenummer=	$viewguard_gruppe[$dp_branch_num];			
					}
					if ($dp_type>=60 && $dp_type<=70 )$type_img=$analoge_img;
					if ($dp_type>=10018 && $dp_type<=10070 )$type_img=$digital_img;
					if ($dp_type>=10071 && $dp_type<=10085 )$type_img=$sirene_img;
					if ($dp_type>=11121 && $dp_type<=11500 )$type_img=$tamper_img;
					if($dp_partition =="VirtualPIR")
					{
						$type_img=$pir_img;
						$meldernummer=$dp_root;
					}
					$meldername= $dp_name."/".$viewguard[$dp_branch_num];
					if ($dp_type>=0 || $dp_num!=0)
					{
						
						#Melderdetails aus Datenbank holen
						$melderabfrage=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$dp_partition_num' AND `hersteller`='MBSecure'");
						$mtyp=mysql_fetch_array($melderabfrage);
						#Melder in Datenbank eintragen
						$sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='".$gruppenummer."', `melder`='$meldernummer',`text`='$meldername', `art`='".$mtyp['text']."', `auto`='".$mtyp['auto']."', `manuell`='".$mtyp['manuell']."', `steuer`='".$mtyp['steuer']."', `ring`='$ring', `typ`='$type_img', `adresse`='$Address', `serial`='$serial', `mandant`='".$userinfo['mandant']."'");
						$Anzahl_Melder++;
						#Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
						$qm=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$gruppenummer."' AND `melder` = '".$meldernummer."' AND `mandant` = '".$userinfo['mandant']."'");
						if(mysql_num_rows($qm)==0)
						{
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
							$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$gruppenummer."',
							`melder` = '".$Melder_Counter_Nummer."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='".$userinfo['mandant']."'";
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
	
	
	/*
	
					elseif  ($dp_partition =="Macro")
				{
					$gruppenummer = $dp_type - $offset_macro;
					$gruppe_name= $dp_name;
					$mg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$gruppenummer', `text`='$gruppe_name', `mandant`='".$userinfo['mandant']."'");
					$Anzahl_Meldegruppen++;
					$gruppe_branch[$gruppenummer]=$dp_root;
				}
				elseif  ($dp_partition =="VirtualPeripheral")
				{
					$gruppenummer = $dp_type - $offset_peripheral;
					$gruppe_name= $dp_name;
					$mg=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$gruppenummer', `text`='$gruppe_name', `mandant`='".$userinfo['mandant']."'");
					$Anzahl_Meldegruppen++;
					$gruppe_branch[$gruppenummer]=$dp_root;
				}			
	
	*/
	
	
?>

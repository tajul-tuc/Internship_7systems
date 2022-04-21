<?

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");
function fc2060_einlesen($fid, $aid){
	global  $userinfo, $debug,$programFilesFolder;
	
	$q=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$q=mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$q=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
	$Anzahl_Meldergroups = 0;
	$Anzahl_Melder = 0;
	$Anzahl_Steuerungen = 0;
	$Anzahl_DetectorType = 0;
	$detector_array = array();
	$diferent_detectors = array();
	$fp = get_fid_path($fid);
	$counter_different = 0;
	// search  one file 
	$sql="SELECT * FROM `files` WHERE `fid`='$fid' AND `ordner`='$programFilesFolder' AND `mandant`='$userinfo[mandant]' LIMIT 1";
	$q=mysql_query($sql);
	
	//read thefile
	$qa=mysql_fetch_array($q);
	if(strpos($qa[name], '.xml')){
		$xml=simplexml_load_file($fp) or die("Error: Cannot create object, fp:$fp fid:$fid");
		
	}
	$tn ='technicalName';
	$vl ='value';
	  
	$return="";
	foreach ($xml->View as $view)
	{	
		//fill array of detectors
		if($view[$tn]=="Hardware")
		{
			foreach ($view->Element as $anlage)
			{
				foreach ($anlage->Element as $panel)
				{	
					foreach ($panel->Element as $module)
					{	
						foreach ($module->Element as $line)
						{
							foreach ($line->Element as $device)
							{	
								foreach ($device->Element as $chanel)
								{	
									foreach ($chanel->Reference as $ref)
									{
										//the string cast is because the $ref is an xml object and the key needs to be string
										$detector_array[(string)$ref[$vl]] = (string)$device["localizedName"];
										if(!isset($diferent_detectors [(string)$device["localizedName"]])){
											$diferent_detectors[(string)$device["localizedName"]] = $counter_different;
											$counter_different++;
										}
										$Anzahl_DetectorType++;
									}
								}
							} 
						}
					}
				}
			}
			if($debug){
				echo "detector_array: ".count($detector_array)."<br>";
				
			}
			// print_r($detector_array);
		}
		//Group / Melder loop
		if($view[$tn]=="Detection")
		{
			foreach ($view->Element as $anlage)
			{
				foreach ($anlage->Element as $panel)
				{
					foreach ($panel->Element as $area)
					{	
						if($area[$tn]=="AreaElem")
						{
							foreach ($area->Element as $section)
							{
								foreach ($section->Element as $group)
								{	
								    $gtext = "";
									foreach ($group->Property as $prop)
										{	
											if($prop[$tn]=="address"){
												$g=(string)$prop[$vl];
												$Anzahl_Meldergroups++;
											}
											if($prop[$tn]=="customerText"){
												$gtext=(string)$prop[$vl];
											}
										}
									//insert groups
									$sql_group_insert = "INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$g', `text`='$gtext', `mandant`='$userinfo[mandant]'";
									$q_g=mysql_query($sql_group_insert);
									if($debug){
										echo "<br>Group Insert: ".$sql_group_insert."<br>";
									}
									foreach ($group->Element as $melder)
									{
										$mtext = "";
										foreach ($melder->Property as $prop)
										{	
											if($prop[$tn]=="address"){
												 $m=(string)$prop[$vl];
												 $Anzahl_Melder++;
											}
											if($prop[$tn]=="customerText"){
												$mtext=(string)$prop[$vl];
											}
											if($prop[$tn]=="elementId"){
											    $detector_key=(string)$prop[$vl];
											}
										}
										
										if(isset($detector_array[$detector_key])){
											$q_mtype=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$detector_array[$detector_key]' AND `hersteller`='FC2060'");
											$mtyp=mysql_fetch_array($q_mtype);

											$gruppe = $t[gruppe];
											$melder = $t[melder];
											$art = $mtyp[kurztext];
											$ring = ""; //need details
											$adresse = "";
											$serial = "";
											
											$sql_melder_insert = "INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$g', `melder`='$m', `text`='$mtext', `art`='$art', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]',`submelder` = '$mtyp[sirene]', `steuer`='$mtyp[steuer]', `ring`='$ring', `typ`='$detector_array[$detector_key]', `adresse`='$adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'";
											$q_m=mysql_query($sql_melder_insert);
											if($debug){
												// echo"technicalName=".$melder[$tn]." G:".$g."-M:".$m." type :$detector_key => ".$detector_array[$detector_key]."<br>";
												echo "Melder Insert: ".$sql_melder_insert."<br>";
											}
										}
									}
								}
							}
						}
					}
				}
			}
		}
	}
	
	// print_r($diferent_detectors);
	// echo "Group number: ".$Anzahl_Meldergroups."<br>";
	// echo "Melder number: ".$Anzahl_Melder."<br>";
	// echo "Detector number: ".$Anzahl_DetectorType."<br>";
	msg((int)$Anzahl_Meldergroups." Meldergruppen Importiert");
	msg((int)$Anzahl_Melder." Melder Importiert");
}	

?>

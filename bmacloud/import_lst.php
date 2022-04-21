<?
//$debug=true;

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

//aid wird benoetigt, da mehere Files pro Anlage gebraucht werden
function lst_einlesen($aid)
{

global $userinfo, $debug,$programFilesFolder;
$melder_counter = 0;
$steuer_counter = 0;
$q=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
$q=mysql_query("DELETE FROM `technik_melder` WHERE `anlage`='$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
$q=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage`='$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
$q=mysql_query("DELETE FROM `technik_ansteuer` WHERE `anlage`='$aid' AND `mandant` = '$userinfo[mandant]'");
//$q=mysql_query("DELETE FROM `technik_esser_sgruppen` WHERE `anlage`='$aid' AND `mandant` = '$userinfo[mandant]'");

//Durchlaufe die Files und Suche nach den Dateinamen
$q=mysql_query("SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='$programFilesFolder' AND `mandant`='$userinfo[mandant]'");
if($debug)
{
echo("SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='$programFilesFolder' AND `mandant`='$userinfo[mandant]'");
}

while($file=mysql_fetch_array($q))
{

    if($debug)
{echo "File: $file[name]\n";}

		if(stripos($file[name],'_Bediengruppen')>2)
		{
		$melder_counter += gruppen(get_fid_path($file[fid]));
		if($debug)
{echo("Bediengruppen gefunden<br>");}
		$dat++;
		}
		if(stripos($file[name],'_Steuerungen')>2)
		{
		$steuer_counter +=sgruppen(get_fid_path($file[fid]));
		if($debug)
{echo("Steuerungen gefunden<br>");}
		$dat++;
		}	
}
	



if($dat==2)
{
/**
$q=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `mandant` = '$userinfo[mandant]'");
$q=mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd`<>1 AND `mandant` = '$userinfo[mandant]'");
$q=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `mandant` = '$userinfo[mandant]'");

//Gruppen
$q2=mysql_query("SELECT * FROM `technik_esser_gruppen` WHERE `anlage`='$aid' AND `mandant` = '$userinfo[mandant]'");
while($g=mysql_fetch_array($q2))
{
$q3=mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$g[gr]', `text`='$g[etage]', `mandant`='$userinfo[mandant]'");
}

//Teilnehmer
$q2=mysql_query("SELECT * FROM `technik_esser_teilnehmer` WHERE `anlage`='$aid' AND `mandant` = '$userinfo[mandant]'");
while($t=mysql_fetch_array($q2))
{
//Wenn der Teilnehmer keinen Text hat, den Gruppentext nehmen
$q4=mysql_query("SELECT * FROM `technik_esser_gruppen` WHERE `anlage` = '$aid' AND `gr`='$t[gruppe]' AND `mandant` = '$userinfo[mandant]'");
$g=mysql_fetch_array($q4);
if($t[text]=="") { $text=$g[etage]; } else {$text=$t[text];}

$typ = $t[art];

$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$typ' AND `hersteller`='ESSER'");
$mtyp=mysql_fetch_array($q4);

$gruppe = $t[gruppe];
$melder = $t[melder];
$ring = $g[pl];
$art = $mtyp[kurztext];
$adresse = $t[adresse];
$serial = $t[serial];

$sql = "INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$gruppe', `melder`='$melder', 
`text`='$text', `art`='$art', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$ring', `typ`='$typ', `adresse`='$adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'";

$c_melder++;

$q3=mysql_query($sql);

if($debug)
{echo($sql."<br>");}

//Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
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

//Wenn der Meldertyp einen vorgegebenen Pruefplan hat, dann diesen verwenden:
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

//Sonderfunktion, der O2T-Sounder auf 2 Pruefpunkte verteilen
if($mtyp[typ]=='O2T/So')
{
$art = 'Sirene';
$typ = 'System';
	$q3=mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$gruppe', `melder`='$melder', `submelder`='1', 
`text`='$text', `art`='$art', `auto`='0', `manuell`='0', `steuer`='1', `ring`='$ring', `typ`='$typ', `adresse`='$adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");

$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$gruppe."',
`melder` = '".$melder."', `submelder` = '1', `i1`='1', `i2`='0', `i3`='0', `i4`='0', `mandant`='$userinfo[mandant]'";
$qm=mysql_query($sql);
}




}

//Steuergruppen
$q2=mysql_query("SELECT * FROM `technik_esser_sgruppen` WHERE `anlage`='$aid' AND `ereignis`='' AND `mandant` = '$userinfo[mandant]' ORDER BY `art`");
while($s=mysql_fetch_array($q2))
{
$von = 0;
$count = 0;

$nr = $s[art];
$ttext = $s[ausl];
$tansteuerung = $s[text];
$tereignis = $s[ereignis];

$q3=mysql_query("SELECT * FROM `technik_esser_sgruppen` WHERE `anlage`='$aid' AND `ereignis`<>'' AND `art`='$nr' AND `mandant` = '$userinfo[mandant]' ORDER BY `g1`");
while($sub=mysql_fetch_array($q3))
{
if($count==0) { $von = $sub[g1]; }
$nr = $sub[art];
$text = $sub[ausl];
$bis = $sub[g2];
$ereignis = $sub[ereignis].' Gruppe '.$von.'-'.$bis;



if($debug)
{echo("Sub: $count - von:$von - bis:$bis<br>");}

if(($sub[g1]<>($vorher+1))&&($count>0))
{
$sql = "INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$nr', `g1`='$von', `text`='$text', `ansteuerung`='$tansteuerung', `ereignis`='$ereignis'";
if($debug)
{echo("Sub: ".$sql."<br>");}
$q4=mysql_query($sql);
$vorher="";
$count=0;
} else {
$count++;
$vorher = $sub[g2];
}
}

if($count>0)
{
$sql = "INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$nr', `g1`='$von', `text`='$text', `ansteuerung`='$tansteuerung', `ereignis`='$ereignis', `mandant`='$userinfo[mandant]'";
$c_stg++;
if($debug)
{echo("Sub: ".$sql."<br>");}
$q4=mysql_query($sql);

//Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
$qman=mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr` = '".$nr."' AND `mandant` = '$userinfo[mandant]'");
if(mysql_num_rows($qman)==0)
{

//Steuergruppen immer in jedem Quartal
$i1=1;
$i2=1;
$i3=1;
$i4=1;

/**
$i1=0;
$i2=0;
$i3=0;
$i4=0;
$mod = ($nr%4);

if($mod==1){$i1='1';}
if($mod==2){$i2='1';}
if($mod==3){$i3='1';}
if($mod==0){$i4='1';}


$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr` = '".$nr."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4'";
$qman=mysql_query($sql);
}
}
}
**/


msg("$melder_counter Melder Importiert");
msg("$steuer_counter Steuergruppen Importiert");

}
else {
err("Keine passenden Dateinamen gefunden");
msg("$melder_counter Melder Importiert");
msg("$steuer_counter Steuergruppen Importiert");
}


}

		
//2.Teil--------------------Gruppenimport--------------------------------------------

function gruppen($fname2)                                                          #Datei ist vorhanden
{	
	global $aid, $userinfo, $debug;
	$grp2=file_get_contents($fname2);
	$m_counter = 0;
	//Definition von Arraygroessen
	$doppel=0;

	$zeile = explode("\n", $grp2);
	$count = count($zeile);

	//first create the meldergruppe, checking if they have been inserted before
	for ($i=1; ($i<=($count)); $i++)
	{
	
		if($debug){echo("Gruppe: $gruppe Zeile: $zeile[$i]<br>");}

		// $feld = explode("\t", $zeile[$i]);
		$feld = str_getcsv($zeile[$i],"\t");
		$gruppe = trim($feld[5]);
		$gtext = utf8_encode(trim($feld[8]));
		//Pruefe ob es die Gruppe schon gibt
		// if($gruppe>0 && $typ!=0)
		if($gruppe>0 )
		{
			$q=mysql_query("SELECT * FROM `technik_gruppe` WHERE `mandant`='$userinfo[mandant]' AND `anlage`='$aid' AND `gruppe`='$gruppe'");
			if(mysql_num_rows($q)==0)
			{
				//if($debug)
				//{echo("INSERT INTO `technik_gruppe` SET `mandant`='$userinfo[mandant]', `anlage`='$aid', `gruppe`='$gruppe', `text`='$gtext'<br>");}
				$sql = "INSERT INTO `technik_gruppe` SET `mandant`='$userinfo[mandant]', `anlage`='$aid', `gruppe`='$gruppe', `text`='$gtext'";
				$q2=mysql_query($sql);
			}
		}
	}
	
	// fill the melder gruppe with melder,
	for ($i=1; ($i<=($count)); $i++)
	{
	
		if($debug){echo("Gruppe: $gruppe Zeile: $zeile[$i]<br>");}

		// $feld = explode("\t", $zeile[$i]);
		$feld = str_getcsv($zeile[$i],"\t");
		$gruppe = trim($feld[5]);
		$melder = trim($feld[6]);
		$text = utf8_encode(trim($feld[10]));
		$adresse = trim($feld[7]);
		$ring = trim($feld[2]);
		$typ = trim($feld[19]);

		
		//Pruefe ob es die Gruppe schon gibt
		// if($gruppe>0 && $typ!=0)
		if($gruppe>0 )
		{
			$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$typ' AND `hersteller`='LST'");
			if(mysql_num_rows($q4)==0 && $typ!="0")
			{
				if($debug){
					err("Der Meldertyp $typ von Melder $gruppe/$melder wurde nicht gefunden");
				}
				$typ ="0";
			}
				$mtyp=mysql_fetch_array($q4);
				
				$sql = "INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$gruppe', `melder`='$melder',`text`='$text', `art`='$art', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$ring', `typ`='$typ', `adresse`='$adresse', `mandant`='$userinfo[mandant]'";
				mysql_query($sql);
				$m_counter++;
				//Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
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

					//Wenn der Meldertyp einen vorgegebenen Pruefplan hat, dann diesen verwenden:
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
			
		}
	}
	return $m_counter;
}
	
	
//.Teil--------------------SteuerGruppenimport--------------------------------------------

function sgruppen($fname2)                                                        #Datei ist vorhanden
{
	global $aid, $userinfo, $debug;
	$s_counter=0;
	$grp2=file_get_contents($fname2);

//Definition von Arraygroessen
	$doppel=0;

	$zeile = explode("\n", $grp2);
	$count = count($zeile);

	for ($i=1; ($i<=($count)-1); $i++)
	{
		$nr = "";
		$text = "";
		$tansteuerung = "";
		$ereignis = "";
		$last_sid ="";
		if($debug)
		{echo("Steuerung: $zeile[$i]<br>");}
	
		$feld = str_getcsv($zeile[$i],"\t");
		// $feld = explode("\t", $zeile[$i]);
		$steuer_typ = utf8_encode(trim($feld[6]));
		$nr = utf8_encode(trim($feld[7]));
		$text = utf8_encode(trim($feld[13]));
		$tansteuerung = utf8_encode(trim($feld[9]));
		$ereignis = utf8_encode(trim($feld[11]));
		$fehler=0;
		if($debug)
		{
			echo("Steuerung: $steuer_typ $nr $text $tansteuerung $ereignis <br>");
		}
		if($steuer_typ =="Steuerung")
		{
			$sql = "INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$nr',`text`='$text', `ansteuerung`='$tansteuerung', `mandant`='$userinfo[mandant]'";
			$c_stg++;
			if($debug){echo("Sub: ".$sql."<br>");}
			$q4=mysql_query($sql);
			$last_sid = mysql_insert_id();
			
			$sql5 = "INSERT INTO `technik_ansteuer` SET  `art`='$nr', `anlage`='$aid', `sgid`='$last_sid', `ereignis`='$ereignis', `mandant`='$userinfo[mandant]'";
			$c_stg++;
			if($debug){echo("Sub5: ".$sql5."<br>");}
			$q5=mysql_query($sql5);
			$s_counter++;

			//Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
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
return $s_counter;
}


?>



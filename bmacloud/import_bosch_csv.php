<?

$debug=false;

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

//aid wird benötigt, da mehere Files pro Anlage gebraucht werden
function bosch_csv_einlesen($fid,$aid)
{


global $userinfo, $debug;

$q=mysql_query("DELETE FROM `technik_melder` WHERE `anlage`='$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
$q=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage`='$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");

    if (!$handle2 = fopen(get_fid_path($fid), "r+")) {
         print "Kann die Datei $fname nicht oeffnen";
         exit;
    }
	
$lines = file_get_contents(get_fid_path($fid));

		$zeile = explode("\n", $lines);
		$count = count($zeile);

		if($debug)
		{
echo("Import: $fid ".$zeile[0]);
}

		for ($i=1; ($i<=($count)); $i++)
		{
		
			$feld = explode("\t", utf8_encode($zeile[$i]));
			$gruppe = trim($feld[0]);
			$melder = trim($feld[1]); 
			$schaltadresse = trim($feld[2]);
			$ring = trim($feld[3]);
			$pos = trim($feld[4]);
			$typ = trim($feld[8]);
			
			$text = trim($feld[10]);
			$adresse = $ring.'/'.$pos;
			
			//Sonderregel RAS
			if($melderart=='Rauchansaugsystem IT'){
				$text .= ' - '.$typ;
				$typ='Rauchansaugsystem IT';
				}
		if(($gruppe>0)&&($melder>0))
		{
			
					//Prüfe ob es die Gruppe schon gibt
		if($gruppe>0)
		{
		$q=mysql_query("SELECT * FROM `technik_gruppe` WHERE `mandant`='$userinfo[mandant]' AND `anlage`='$aid' AND `gruppe`='$gruppe'");
			if(mysql_num_rows($q)==0)
			{
			//if($debug)
			//{echo("INSERT INTO `technik_gruppe` SET `mandant`='$userinfo[mandant]', `anlage`='$aid', `gruppe`='$gruppe', `text`='$gtext'<br>");}

			$q2=mysql_query("INSERT INTO `technik_gruppe` SET `mandant`='$userinfo[mandant]', `anlage`='$aid', `gruppe`='$gruppe', `text`='$text'");
			} else {
			$q2=mysql_query("UPDATE `technik_gruppe` SET `text`='$text' WHERE `mandant`='$userinfo[mandant]' AND `anlage`='$aid' AND `gruppe`='$gruppe'");
			}
			
			
			$q4=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$typ' AND `hersteller`='BOSCH'");
			if(mysql_num_rows($q4)==1)
			{
			$mtyp=mysql_fetch_array($q4);
			
			$count++;
			
			$sql = "INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$gruppe', `melder`='$melder', 
`text`='$text', `art`='$mtyp[kurztext]', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$ring', `typ`='$typ', `adresse`='$adresse', `mandant`='$userinfo[mandant]'";
			mysql_query($sql);
			
			//echo($sql."<br>");
			
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
		
		$qmt=mysql_query("INSERT INTO `technik_meldertypen` SET `typ`='$typ', `hersteller`='BOSCH', `text`='$typ_text', `img`='melder_autom.png'");
		
		}
		}
		}
			
		}
		
msg((int)$count." Zeilen importiert");
		
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



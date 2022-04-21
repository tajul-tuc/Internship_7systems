<?php

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function effeff_einlesen($fid)
{  

global $aid, $userinfo;

$anlage = $aid; //KWapp kompatibilität


$q=mysql_query("DELETE FROM `technik_effeff` WHERE `anlage` = '$anlage' AND `mandant` = '$userinfo[mandant]'");

    if (!$handle2 = fopen(get_fid_path($fid), "r+")) {
         print "Kann die Datei $fid nicht oeffnen";
         exit;
    }
	
$lines = file(get_fid_path($fid));

foreach ($lines as $line_num => $line) {

if($debug) { echo($line."<br>"); }

	$col = explode(';',$line);
if(count($col)>3)
{
	$id = trim($col[0]);
	$typ = '';
	$text = '';

$gruppe = 0;
$melder = 0;
	
$pattern = '@(.+)-Gruppe(.+)@is'; 
if($result = preg_match($pattern, $col[1], $subpattern))
{
	$gruppe = (int)trim($subpattern[2]);
	$text = trim(utf8_encode($col[3]));
}

$pattern = '@Melder (.+)/(.+),(.+)@is'; 
if($result = preg_match($pattern, $col[1], $subpattern))
{
	$gruppe = (int)trim($subpattern[1]);
	$melder = (int)trim($subpattern[2]);
	$text = trim($subpattern[3]) . $col[3];
	$typ = trim($subpattern[3]);
}


$pattern = '@Steuermodul (.+)/(.+),(.+)@is'; 
if($result = preg_match($pattern, $col[1], $subpattern))
{
	$gruppe = (int)trim($subpattern[1]);
	$melder = (int)trim($subpattern[2]);
	$typ = trim($subpattern[3]);
}

if($debug) { echo("<br><b>Gruppe: $gruppe Melder: $melder Text: $text $col[3]</b><br>"); }

$q=mysql_query("INSERT INTO `technik_effeff` SET `anlage` = '$anlage', `gruppe`='$gruppe', `melder`='$melder', `text`='$text', `typ`='$typ', `mandant` = '$userinfo[mandant]'");

}
}

//Fülle die Tabelle technik_melder mit den Daten von nsc_kopf und nsc_zeile

//Fülle die Gruppentabelle
$q=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$anlage'  AND `mandant` = '$userinfo[mandant]'");
$q=mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$anlage' AND `useradd`<>1  AND `mandant` = '$userinfo[mandant]'");
$q=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$anlage'  AND `mandant` = '$userinfo[mandant]'");

//$q2=mysql_query("INSERT INTO `technik_gruppe` SET `gruppe`='$gruppe', `text`='$text', `anlage` = '$anlage'");

//Lege die Gruppen an

$q=mysql_query("SELECT * FROM `technik_effeff` WHERE `gruppe`>'0' AND `melder`='0' AND `anlage` = '$anlage'  AND `mandant` = '$userinfo[mandant]'");
while($g=mysql_fetch_array($q))
{
$q2=mysql_query("INSERT INTO `technik_gruppe` SET `gruppe`='$g[gruppe]', `text`='$g[text]', `anlage` = '$anlage'");
}


//Lege die Melder an
$q=mysql_query("SELECT * FROM `technik_effeff` WHERE `gruppe`>'0' AND `melder`>'0' AND `anlage` = '$anlage'  AND `mandant` = '$userinfo[mandant]'");
while($m=mysql_fetch_array($q))
{
//Melder Typ aus Typentabelle
$typ = $m[typ];
$gruppe = $m[gruppe];
$melder = $m[melder];
$text = $m[text];
$q5=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$typ'");
if(mysql_num_rows($q5)==0){echo("<b>Meldertyp $typ wurde nicht gefunden</b><br>"); print_r($melder_arr);}
$dat_typ=mysql_fetch_array($q5);
$art = $dat_typ[kurztext];
$auto = $dat_typ[auto];
$manuell = $dat_typ[manuell];
$steuer = $dat_typ[steuer];

$betriebsart = 'Standard';

$sql="INSERT INTO `technik_melder` SET `gruppe`='$gruppe', `melder`='$melder', `adresse`='$adresse', `ring`='$ring', `art`='$art', `text`='$text', `typ`='$typ', `anlage` = '$anlage', `auto`='$auto', `manuell`='$manuell', `steuer`='$steuer', `betriebsart`='$betriebsart', `mandant` = '$userinfo[mandant]'";
$q2=mysql_query($sql);

$c_melder++;

//Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
$q2=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$anlage' AND `gruppe` = '".$gruppe."' AND `melder` = '".$melder."'  AND `mandant` = '$userinfo[mandant]'");
if(mysql_num_rows($q2)==0)
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
if(($dat_typ[i1]==1)||($dat_typ[i2]==1)||($dat_typ[i3]==1)||($dat_typ[i4]==1))
{
	$i1=$dat_typ[i1];
	$i2=$dat_typ[i2];
	$i3=$dat_typ[i3];
	$i4=$dat_typ[i4];
}


$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$anlage', `gruppe` = '".$gruppe."',
`melder` = '".$melder."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4',  `mandant`='$userinfo[mandant]'";
$q2=mysql_query($sql);

}
}

//Steuergruppen - Nicht implementiert

msg((int)$c_melder." Melder Importiert");
}


function no_null($str)
{
if(($str[0]=='0')&&($str<>'')){$str=(int)$str;} //Führende Nullen entfernen
return $str;
}

?>


<?php

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function algorex_einlesen($fid)
{  
global $aid, $userinfo;

$anlage = $aid; //KWapp kompatibilität

if (!$handle2 = fopen(get_fid_path($fid), "r+")) {
	 print "Kann die Datei $fname nicht oeffnen";
	 exit;
}
	
 $myXMLData = file_get_contents(get_fid_path($fid));
 //encoding the xml to utf8
$xml=simplexml_load_string(utf8_encode($myXMLData)) or die("Error: Keine XML-Struktur erkannt");

$q=mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$anlage' AND `mandant` = '$userinfo[mandant]'");
$q=mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$anlage' AND `useradd`<>1 AND `mandant` = '$userinfo[mandant]'");
$q=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$anlage' AND `mandant` = '$userinfo[mandant]'");


foreach($xml->span as $span) {

if($debug) {echo("$span[id]<br>");}

foreach($span->div as $div) {
if($debug) {echo("Div: $div[id]<br>");}

foreach($div as $div2) {
if($debug) {echo("&nbsp;Div2: $div2[id]<br>");}

foreach($div2 as $div3) {
if($debug) {echo("&nbsp;&nbsp;Div3: $div3[id]<br>");}

foreach($div3 as $div4) {
if($debug) {echo("&nbsp;&nbsp;&nbsp;Div4: $div4[id]<br>");}

foreach($div4 as $div5) {
if($debug) {echo("&nbsp;&nbsp;&nbsp;Div5: $div5[id]<br>");}

foreach($div5 as $div6) {
if($debug) {echo("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Div6: $div6[id] - $div6<br>");}
//Hier stehen die Meldertexte
if(strlen($text)<3) //Bei Zeilenumbruch kommen mehrfach die Texte vor, aber nur mi /
{
$pattern = '@.*MG (\d+\s*)/(\s*\d+)(.*)@is'; 
if($result = preg_match($pattern, $div6, $subpattern))
{
	$gruppe = (int)trim($subpattern[1]);
	$melder = (int)trim($subpattern[2]);
	//$text = trim(utf8_encode($subpattern[3]));
	$text = trim($subpattern[3]);
	if($debug) {echo(" G: ".$gruppe." - M: ".$melder." Text: ".$text);}
}
$pattern = '@.*Melder (\d+\s*)/(\s*\d+)(.*)@is'; 
if($result = preg_match($pattern, $div6, $subpattern))
{
	$gruppe = (int)trim($subpattern[1]);
	$melder = (int)trim($subpattern[2]);
	//$text = trim(utf8_encode($subpattern[3]));
	$text = trim($subpattern[3]);
	if($debug) {echo(" G: ".$gruppe." - M: ".$melder." Text: ".$text);}
}
$pattern = '@.*Melder  (\d+\s*)/(\s*\d+)(.*)@is'; 
if($result = preg_match($pattern, $div6, $subpattern))
{
	$gruppe = (int)trim($subpattern[1]);
	$melder = (int)trim($subpattern[2]);
	//$text = trim(utf8_encode($subpattern[3]));
	$text = trim($subpattern[3]);
	if($debug) {echo(" G: ".$gruppe." - M: ".$melder." Text: ".$text);}
}
}

foreach($div6 as $div7) {
if($debug) {echo("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Div7: $div7[id] - $div7<br>");}

foreach($div7 as $div8) {
if($debug) {echo("&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;Div8: $div8[id] - $div8<br>");}
//Hier die Typen

$pattern = '@D:(\d*) (.+)@is'; 
if($result = preg_match($pattern, $div8, $subpattern))
{
	$adresse = (int)trim($subpattern[1]);
	//$text = trim(utf8_encode($subpattern[3]));
	$typ = trim($subpattern[2]);
	
	if($debug) {echo(" Ringadresse: ".$adresse." - Typ: ".$typ);}
}

$pattern = '@I:(\d*) (.+)@is'; 
if($result = preg_match($pattern, $div8, $subpattern))
{
	$ring = (int)trim($subpattern[1]);
	//$text = trim(utf8_encode($subpattern[3]));
	$rintyp = trim($subpattern[2]);
	
	if($debug) {echo(" Ring: ".$ring);}
	
	//Alle Daten zum Melder vohanden, also DB-Eintrag:
	$c_melder += neuer_teilnehmer($gruppe,$melder,$ring,$adresse,$typ,$text);
	unset($text);
	unset($gruppe);
	unset($melder);
	unset($ring);
	unset($adresse);
	unset($typ);
	unset($text);
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

msg((int)$c_melder." Melder Importiert");

}


function neuer_teilnehmer($gruppe,$melder,$ring,$adresse,$typ,$text)
{
global $aid, $userinfo;

$anlage = $aid; //KWapp kompatibilität

//Lege die Gruppen an
if($gruppe>0)
{
$q=mysql_query("SELECT * FROM `technik_gruppe` WHERE `gruppe`='$gruppe' AND `anlage` = '$anlage' AND `mandant` = '$userinfo[mandant]'");
if(mysql_num_rows($q)==0)
{
$gtext = "Gruppe ".$gruppe;
$q2=mysql_query("INSERT INTO `technik_gruppe` SET `gruppe`='$gruppe', `text`='$gtext', `anlage` = '$anlage' , `mandant` = '$userinfo[mandant]'");
}

$q5=mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$typ' AND `hersteller`='Algorex'");
if(mysql_num_rows($q5)==0){err("Meldertyp <b>$typ</b> wurde nicht gefunden");}
$dat_typ=mysql_fetch_array($q5);
$art = $dat_typ[kurztext];
$auto = $dat_typ[auto];
$manuell = $dat_typ[manuell];
$steuer = $dat_typ[steuer];

$betriebsart = 'Standard';

if(strlen($text)<3)
{
	$text = "Melder ".$melder;
}

$sql="INSERT INTO `technik_melder` SET `gruppe`='$gruppe', `melder`='$melder', `adresse`='$adresse', `ring`='$ring', `art`='$art', `text`='$text', `typ`='$typ', `anlage` = '$anlage', `auto`='$auto', `manuell`='$manuell', `steuer`='$steuer', `betriebsart`='$betriebsart', `mandant` = '$userinfo[mandant]'";

$q2=mysql_query($sql);

//Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
$q2=mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$anlage' AND `gruppe` = '".$gruppe."' AND `melder` = '".$melder."' AND `mandant` = '$userinfo[mandant]'");

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
`melder` = '".$melder."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant` = '$userinfo[mandant]'";
$q2=mysql_query($sql);

}
return 1;
}
return 0;
}

?>


<?php


mysqli_query($mysqli, "SET NAMES 'utf8'");
mysqli_query($mysqli, "SET CHARACTER SET 'utf8'");

function nsc_einlesen($fid)
{  
global $aid, $userinfo, $debug, $mysqli;


$q = mysqli_query($mysqli, "DELETE FROM `technik_nsc_zeile`
WHERE kid IN (select kid from `technik_nsc_kopf` as k WHERE k.`anlage` = '$aid' AND k.`mandant` = '$userinfo[mandant]');");

if($debug){echo("DELETE FROM `technik_nsc_zeile` 
WHERE kid IN (select kid from `technik_nsc_kopf` as k WHERE k.`anlage` = '$aid' AND k.`mandant` = '$userinfo[mandant]');"); }

$q=mysqli_query($mysqli, "DELETE FROM `technik_nsc_kopf` WHERE `anlage` = '$aid' AND `mandant` = '$userinfo[mandant]'");
$q=mysqli_query($mysqli, "DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
$q=mysqli_query($mysqli, "DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
$q=mysqli_query($mysqli, "DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
$q=mysqli_query($mysqli, "DELETE FROM `technik_ansteuer` WHERE `anlage` = '$aid' AND `mandant` = '$userinfo[mandant]'");
$qfiles=mysqli_query($mysqli, "SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='0' AND UPPER(`ext`) = 'NSC' AND  `mandant`='$userinfo[mandant]'");

if(!$qfiles){
        printf("Error: %s\n", mysqli_error($mysqli));
}

if($debug){//see sql of files
		echo "SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='0' AND UPPER(`ext`) = 'NSC' AND  `mandant`='$userinfo[mandant]' <br>";
	}
while($file=mysqli_fetch_array($qfiles))
{
	$fid =$file[fid];
    if (!$handle2 = fopen(get_fid_path($fid), "r+")) {
         print "Kann die Datei $fid nicht oeffnen";
		 exit;
    }
$lines = file(get_fid_path($fid));
//find the GruppenOffset if exists, if not will be 0



$gruppenOffset = 0;
$matches  = preg_grep ('/^O=.*/is', $lines);
if(count($matches)==1){
	if($result = preg_match('/O=0*(\d*)/is', array_values($matches)[0], $subpattern)){
		if($subpattern[1]!=''){
			$gruppenOffset = (int) $subpattern[1];
		} 
	} 
}

foreach ($lines as $line_num => $line) {

	//Kopfzeilen einlesen
	if($line[0]=="[")
	{
		$line = str_replace('[','',$line);
		$line = str_replace(']','',$line);
		$tag = explode(' ',$line);
		$tag1 = trim($tag[0]);
		$tag2 = no_null(trim($tag[1]));
		$tag3 = no_null(trim($tag[2]));
		$tag4 = no_null(trim($tag[3]));
			//add gruppenoffset to the tag2 when is a group in tag1 and tag2 is greater than 0 , this
			// way makes possible multiple uploads without interfering the groupnumbers in the database in case there are different gruppenofsets for each one
			if(($tag1 =="G") && (int)$tag2>0){
				$tag2 = $gruppenOffset+(int)$tag2;
			}
		$sql = "INSERT INTO `technik_nsc_kopf` SET `tag1`='".$tag1."', `tag2`='".$tag2."', `tag3`='".$tag3."', `tag4`='".$tag4."', `anlage`='".$aid."', `mandant` = '$userinfo[mandant]'";
		$q=mysqli_query($mysqli, $sql);
		$kid = mysqli_insert_id($mysqli);
	} else {
		$temp = explode('=',$line);
		$name = trim($temp[0]);
		$wert = no_null(trim($temp[1]));
		if(($wert[0]=='0')&&($wert<>'')){$wert=(int)$wert;} //Führende Nullen entfernen
		//add the gruppenoffset to the group of the melder data
		if(($name =="G") && (int)$wert>0){
				$wert = $gruppenOffset+(int)$wert;
			}
		$sql = "INSERT INTO `technik_nsc_zeile` SET `name`='".$name."', `wert`='".$wert."', `kid`='".$kid."'";
		if($debug){echo($line. ' ergibt '.$sql."<br>");}
		$q=mysqli_query($mysqli, $sql);
	}
	
}
}

//Fülle die Tabelle technik_melder mit den Daten von nsc_kopf und nsc_zeile

//Fülle die Gruppentabelle
$sql = "SELECT `tag2`, `kid` FROM `technik_nsc_kopf` WHERE `tag1`='G' AND CONVERT(SUBSTRING_INDEX(`tag2`,'-',-1),UNSIGNED INTEGER)>'0' AND `anlage` = '$aid'AND `mandant` = '$userinfo[mandant]'";

$q=mysqli_query($mysqli, $sql);
// $q=mysqli_query("SELECT * FROM `technik_nsc_kopf` WHERE `tag1`='G' AND `anlage` = '$aid' AND `mandant` = '$userinfo[mandant]'");

if($debug){
	echo($sql."<br>");
}

while($g=mysqli_fetch_array($q))
{
	//add gruppenOffset to the groups as an integer

$gruppe = (int) $g['tag2'];
$gruppeOff = $gruppe;
$gruppe_arr = lade($g['kid']);
$text = $gruppe_arr['T'];

if($debug){echo("lade kid: ".$g['kid']."<br>");}

$sql = "INSERT INTO `technik_gruppe` SET `gruppe`='$gruppeOff', `text`='$text', `anlage` = '$aid', `mandant` = '$userinfo[mandant]'";

$q2=mysqli_query($mysqli, $sql);

if($debug){echo($sql."<br>");}

//Fülle die Meldertabelle
$sql="SELECT * FROM `technik_nsc_kopf` as k, `technik_nsc_zeile` as z WHERE k.kid=z.kid AND k.anlage = '$aid' AND `name`='G' AND `wert`='$gruppe' AND k.`mandant` = '$userinfo[mandant]'";

if($debug){echo($sql."<br>");}

$q4=mysqli_query($mysqli, $sql);
while($dat=mysqli_fetch_array($q4))
{
$adresse = $dat[tag4];
$ring = $dat[tag2];

//Detailinfos des Melders
$melder_arr = lade($dat[kid]);
$melder = $melder_arr['M'];
$text = $melder_arr['T'];


//Sockelsirenen etc. Adr > 127
if($adresse>127)
{
$sockelmelder_adr = $adresse - 127;
$sockelmelder_arr=lade(get_kid('R',$ring,'A',$sockelmelder_adr));
if($text=='')
{
$text = $sockelmelder_arr['T'];
}
}

if($text=='')
{
$text = $gruppe_arr['T'];
}


//Melder Typ aus Typentabelle
$typ = $melder_arr['D'];
$q5=mysqli_query($mysqli, "SELECT * FROM `technik_meldertypen` WHERE `typ`='$typ'");
if(mysqli_num_rows($q5)==0){err("<b>Meldertyp $typ wurde nicht gefunden</b><br>"); print_r($melder_arr);}
$dat_typ=mysqli_fetch_array($q5);
$art = $dat_typ[kurztext];
$auto = $dat_typ[auto];
$manuell = $dat_typ[manuell];
$steuer = $dat_typ[steuer];
//in case is a sirene
$submelder ='';
if(strpos($dat_typ[kurztext],'Sirene') !== false ){
	$submelder = '1';
 } 
$betriebsart = 'Standard';


$sql="INSERT INTO `technik_melder` SET `gruppe`='$gruppeOff', `melder`='$melder', `adresse`='$adresse', `ring`='$ring', `art`='$art', `text`='$text', `typ`='$typ', `anlage` = '$aid', `auto`='$auto', `manuell`='$manuell', `steuer`='$steuer', `betriebsart`='$betriebsart',`submelder`='$submelder', `mandant` = '$userinfo[mandant]'";
if($debug){
	echo "A:".$sql."<br>";
}
$q2=mysqli_query($mysqli, $sql);

$c_melder++;




//Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
$q2=mysqli_query($mysqli, "SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '".$gruppeOff."' AND `melder` = '".$melder."' AND `mandant` = '$userinfo[mandant]'");
if(mysqli_num_rows($q2)==0)
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

$sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '".$gruppeOff."',
`melder` = '".$melder."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4',`submelder`='$submelder', `mandant` = '$userinfo[mandant]'";
if($debug){
	echo"B: ".$sql."<br>";
}
$q22=mysqli_query($mysqli, $sql);
}elseif(mysqli_num_rows($q2)==1){
	$mel_man=mysqli_fetch_array($q2);
	if($submelder != $mel_man[submelder] && $submelder != ''){
		$sql2mm= "UPDATE `technik_melder_manuell` SET `submelder`='$submelder' WHERE `anlage`='$aid' AND `gruppe` = '".$gruppeOff."' AND `melder` = '".$melder."'AND `mandant` = '$userinfo[mandant]'";
		if($debug){
			echo"C: ".$sql2mm."$mel_man[submelder]"."<br>";
		}
		$qmm=mysqli_query($mysqli, $sql2mm);
	}
}


}//Melder


}//Gruppe


//Steuergruppen
$sql="SELECT * FROM `technik_nsc_kopf` as k WHERE `tag1`='Al' AND `anlage` = '$aid' AND `mandant` = '$userinfo[mandant]' ORDER BY ABS(`tag2`)";
$q4=mysqli_query($mysqli, $sql);
while($dat=mysqli_fetch_array($q4))
{
$text = "";
$ansteuerung = "";
$ereignis = "";
$ausloesung = "";
$anzahl=0;
$a_grp_text="";

$al = lade($dat[kid]);

//Verknüpfungen prüfen
if(($v_d ==  $al[D])&&($v_r == $al[R])&&($v_a == $al[A])&&($v_n == $al[N]))
{
//Hier liegt eine Verknüpfung vor
$vk_sid = $sid;
if($debug)
{
echo("<br>Verknüpfung bei SG: $dat[tag2] mit $vk_sid<br>");
}

} else {
$vk_sid = 0;
if($debug)
{
echo("<br>Keine VK: $dat[tag2] ($v_d ==  $al[D])&&($v_r == $al[R])&&($v_a == $al[A])&&($v_n == $al[N])<br>");
}
}

$v_d = $al[D];
$v_r = $al[R];
$v_a = $al[A];
$v_n = $al[N];
$vk_art = $al[V];

//Ereignisse
if($al[K]==0)
{
	$ereignis = 'Feuer von '.gruppe($al[G1],$al[M1],$al[G2],$al[M2]);
}
if($al[K]==1)
{
	$ereignis = 'Voralarm von '.gruppe($al[G1],$al[M1],$al[G2],$al[M2]);
}
if($al[K]==2)
{
	$ereignis = 'Hauptalarm von '.gruppe($al[G1],$al[M1],$al[G2],$al[M2]);
}
if($al[K]==3)
{
	$ereignis = 'Störung von '.gruppe($al[G1],$al[M1],$al[G2],$al[M2]);
}
if($al[K]==4)
{
	$ereignis = 'Abschaltung von '.gruppe($al[G1],$al[M1],$al[G2],$al[M2]);
}
if($al[K]==5)
{
	$ereignis = 'BMZ Rückstellen durch Zentrale';
}
if($al[K]==6)
{
	$ereignis = 'Störung Stromversorgung in der Zentrale';
}
if($al[K]==7)
{
	$ereignis = 'Netzausfall der Zentrale';
}
if($al[K]==8)
{
	$ereignis = 'Feuer Automatikmelder';
}
if($al[K]==9)
{
	$ereignis = 'Feuer Handmelder';
}
if($al[K]==10)
{
	$ereignis = 'FSD Sabotage';
}
if($al[K]==11)
{
	$ereignis = 'FSD entriegelt';
}
if($al[K]==12)
{
	$ereignis = 'FSD Ansteuerung';
}
//if($al[K]==13)
//{
//	$ereignis = 'Verzögerung';
//}
if($al[K]==13)
{
	$ereignis = 'ÜE ausgelöst';
}
if($al[K]==14)
{
	$ereignis = 'ÜE abgeschaltet';
}
if($al[K]==15)
{
	$ereignis = '2. Alarm von '.gruppe($al[G1],$al[M1],$al[G2],$al[M2]);
}
if($al[K]==16)
{
	$ereignis = 'Technischer Alarm von '.gruppe($al[G1],$al[M1],$al[G2],$al[M2]);
}
if($al[K]==17)
{
	$ereignis = 'Testalarm von '.gruppe($al[G1],$al[M1],$al[G2],$al[M2]);
}
if($al[K]==18)
{
	$ereignis = 'Auslösung';
}
if($al[K]==19)
{
	$ereignis = 'Zeitprogramm';
}
if($al[K]==20)
{
	$ereignis = 'Information';
}
if($al[K]==128)
{
	if($al[F]==0) { $funktion = 'Gruppe/Melder'; }
	if($al[F]==1) { $funktion = 'Ausgang'; }
	if($al[F]==2) { $funktion = 'Relais'; }
	if($al[F]==3) { $funktion = 'Steuerlinie'; }
	if($al[F]==4) { $funktion = 'Ausgangsmodul'; }
	if($al[F]==5) { $funktion = 'ÜE'; }
	if($al[F]==6) { $funktion = 'Signalgeber'; }
	if($al[F]==7) { $funktion = 'Brandfallsteuerung'; }
	if($al[F]==8) { $funktion = 'Verzögerung'; }
	
	$ereignis = "Ein-/Ausschalten von $funktion ".$al[G1].'-'.$al[G2];
}
if($al[K]==130)
{
	if($al[F]==0) { $funktion = 'Gruppe/Melder'; $adressen = gruppe($al[G1],$al[M1],$al[G2],$al[M2]);}
	if($al[F]==1) { $funktion = 'Ausgang'; $adressen = $al[G1];}
	if($al[F]==2) { $funktion = 'Relais'; $adressen = $al[G1];}
	if($al[F]==3) { $funktion = 'Steuerlinie'; $adressen = $al[G1];}
	if($al[F]==4) { $funktion = 'Ausgangsmodul'; 
	
	//Prüfe den Typ des Ausgangsmodules
	$ausgangsmodul = lade(get_kid('R',$al[G1],'A',$al[M1]));
	$adressen = ' von Gr. '.$ausgangsmodul[G].'/'.$ausgangsmodul[M];
	}
	
	$ereignis = "Auslösen von $funktion ".$al[A1]." $adressen";
}
if($al[K]==133)
{
	$ereignis = 'Revision von '.gruppe($al[G1],$al[M1],$al[G2],$al[M2]);
}



//Ausgang ist ein Relais
if($al[D]==2)
{
  $rel = lade(get_kid('Rel',$al[N]));
  $text=$rel[T];
  $ansteuerung = 'Relais '.$al[N];
}

//Ausgang ist eine Steuerlinie
if($al[D]==3)
{
  $stl = lade(get_kid('Stl',$al[N]));
  $text=$stl[T];
  $ansteuerung = 'Steuerlinie '.$al[N];
}

//Ausgang ist ein Ausgangsmodul
if($al[D]==4)
{
	//Prüfe den Typ des Ausgangsmodules
	$kid = get_kid('R',$al[R],'A',$al[A]);
	$ausgangsmodul = lade($kid);

	//Ausgangsmdul-Typ aus Typentabelle
	$typ = $ausgangsmodul[D];
	$dat_typ=get_typ($typ);
	
	$k = 8 + $al[N];
	$sql = "SELECT kopf.*,s.*,a.*,k.*,(SELECT `wert` FROM `technik_nsc_zeile` as t WHERE t.kid=a.kid AND name='T') as T FROM `technik_nsc_zeile` as s, 
	`technik_nsc_zeile` as a, 
	`technik_nsc_zeile` as k,
	`technik_nsc_kopf` as kopf 
	WHERE 
	a.kid=s.kid AND 
	a.kid=k.kid AND 
	kopf.kid = a.kid AND
	kopf.anlage = '$aid' AND
	kopf.`mandant` = '$userinfo[mandant]' AND
	((s.`name`='S' AND s.`wert`='$al[R]') AND (a.`name`='A' AND a.`wert`='$al[A]') AND (k.`name`='K' AND k.`wert`='$k'))";


	$q5=mysqli_query($mysqli, $sql);
	$t=mysqli_fetch_array($q5);
	$text = $t[T];
	$ansteuerung = "Ausgang ".$al[N].' von Gr. '.$ausgangsmodul[G].'/'.$ausgangsmodul[M];
	
	//Ausgsngsmodultyp
	if($dat_typ[sirene]==1)
	{
		$text = 'Akkustik';
		$agrp = $ausgangsmodul[P2];
		//Anzahl der Steuergruppen
		$q6=mysqli_query($mysqli, "DELETE FROM `technik_nsc_agrp` WHERE `anlage` = '$aid' AND `agrp`='$agrp' AND `mandant` = '$userinfo[mandant]'");
		
		//Alle Teilnehmer mit der Ansteuergruppe:
	$sql = "select * FROM `technik_nsc_zeile` as s, `technik_nsc_kopf` as kopf  WHERE
	kopf.kid = s.kid AND
	kopf.anlage = '$aid' AND
	kopf.`mandant` = '$userinfo[mandant]' AND
	kopf.tag1 = 'R' AND
	s.name = 'P2' AND
	s.wert = '".$agrp."'";
	$q6 = mysqli_query($mysqli, $sql);
	while($a_grp=mysqli_fetch_array($q6))
	{
		
		$a_tln=lade($a_grp[kid]);
		
			if($debug)
{echo("<br>TLN:");
		print_r($a_tln);}
		
		$a_tln_typ = get_typ($a_tln[D]);
		//Prüfe den Typ
		if(($a_tln_typ[sirene]==1)||($a_tln_typ[optisch]==1))
		{
			if($debug)
{echo("Steuerbar<br>");}
			//Gruppe / Melder in Temp-Tabelle
			$q7=mysqli_query($mysqli, "INSERT INTO `technik_nsc_agrp` SET `anlage`='$aid', 
			`gruppe`='$a_tln[G]', `melder`='$a_tln[M]', `agrp`='$agrp', `mandant` = '$userinfo[mandant]'");
				
			$anzahl ++;
		}
		
		
	} //Ende Teilnehmer Ansteuergruppen
	
	$q7=mysqli_query($mysqli, "SELECT `gruppe`, count(*) as anzahl FROM `technik_nsc_agrp` WHERE 
	`anlage`='$aid' AND `agrp`='$agrp' AND `mandant` = '$userinfo[mandant]' GROUP BY `gruppe`");
	while($a_grp = mysqli_fetch_array($q7))
	{
		if($a_grp_text<>''){$a_grp_text .= ' und ';}
		$a_grp_text .= $a_grp[anzahl].' Sirenen Gr.'.$a_grp[gruppe];
	}
	
	$ansteuerung = $a_grp_text.' Ton '.$al[N];
	} 
	
	if($dat_typ[optisch]==1)
	{
		$text = 'Optik';
		$ansteuerung = 'Blitzleuchten Gr.'.$ausgangsmodul[G];
	} 
}

//Eingangsmodul
if($al[D]==5)
{
	//Ereignis und Ansteuerung verdrehen
	$ansteuerung = $ereignis;

	$eingangsmodul = lade(get_kid('R',$al[R],'A',$al[A]));
	
	//Eingangsmodul
	$sql = "SELECT kopf.*,s.*,a.*,k.*,(SELECT `wert` FROM `technik_nsc_zeile` as t WHERE t.kid=a.kid AND name='T') as T FROM `technik_nsc_zeile` as s, 
	`technik_nsc_zeile` as a, 
	`technik_nsc_zeile` as k,
	`technik_nsc_kopf` as kopf 
	WHERE 
	a.kid=s.kid AND 
	a.kid=k.kid AND 
	kopf.kid = a.kid AND
	kopf.anlage = '$aid' AND
	kopf.`mandant` = '$userinfo[mandant]' AND
	((s.`name`='S' AND s.`wert`='$al[R]') AND (a.`name`='A' AND a.`wert`='$al[A]') AND (k.`name`='K' AND k.`wert`='$al[N]'))";
	
	$q5=mysqli_query($mysqli, $sql);
	$t=mysqli_fetch_array($q5);
	$text = $t[T];
	$ereignis = 'Eingang '.$al[N].' von Gr. '.$eingangsmodul[G].'/'.$eingangsmodul[M];
}

//Eingang in der Zentrale
if($al[D]==6)
{
	//Ereignis und Ansteuerung verdrehen
	$ansteuerung = $ereignis;
	
	//Text vom Eingang holen
	$eingang_arr = lade(get_kid('Ein',$al[N]));
	$text = $eingang_arr['T'];
	$ereignis = 'Eingang '.$al[N].' von Zentrale';
}

//Sondertaste
if($al[D]==8)
{
	//Ereignis und Ansteuerung verdrehen
	$ansteuerung = $ereignis;
	
	//Text vom Eingang holen
	$text = 'Tastendruck an Zentrale';
	$ereignis = 'Sondertaste '.$al[N];
}

$sql="INSERT INTO `technik_steuergruppen` SET `nr`='$dat[tag2]'
, `text`='$text'
, `ansteuerung`='$ansteuerung'
, `ereignis`='$ereignis'
, `ausloesung`='$ausloesung'
, `vk_sid`='$vk_sid'
, `vk_art`='$vk_art'
, `anlage` = '$aid'
, `mandant` = '$userinfo[mandant]'
";

$c_stg++;

$q2=mysqli_query($mysqli, $sql);
$last_id = mysqli_insert_id($mysqli);

if($vk_sid==0) //Bei Verknüpfung, SID auf die erste Steuergruppe legen
{
$sid = $last_id;
}

//Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
$q2=mysqli_query($mysqli, "SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `mandant` = '$userinfo[mandant]' AND `nr` = '".$dat[tag2]."'");
if(mysqli_num_rows($q2)==0)
{
//Steuergruppen immer in jedem Quartal
$i1=1;
$i2=1;
$i3=1;
$i4=1;

$sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `mandant` = '$userinfo[mandant]', `nr` = '".$dat[tag2]."', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4'";
$q2=mysqli_query($mysqli, $sql);
}



}


//Bereinige die Temp-Tabellen damit die DB geschont wird

$q = mysqli_query($mysqli, "DELETE FROM `technik_nsc_zeile`
WHERE kid IN (select kid from `technik_nsc_kopf` as k WHERE k.`anlage` = '$aid' AND k.`mandant` = '$userinfo[mandant]');");

if($debug){echo("DELETE FROM `technik_nsc_zeile` 
WHERE kid IN (select kid from `technik_nsc_kopf` as k WHERE k.`anlage` = '$aid' AND k.`mandant` = '$userinfo[mandant]');"); }

$q=mysqli_query($mysqli, "DELETE FROM `technik_nsc_kopf` WHERE `anlage` = '$aid' AND `mandant` = '$userinfo[mandant]'");


msg("$c_melder Melder Importiert");
msg("$c_stg Steuergruppen Importiert");
}

//Funktionen
//Lade alle Zeilen in ein Array
function lade($kid)
{
	global $mysqli;
$q=mysqli_query($mysqli, "SELECT * FROM `technik_nsc_zeile` WHERE `kid`='$kid'");
while($dat=mysqli_fetch_array($q))
{
	$name = $dat[name];
	$wert = $dat[wert];
	$arr[$name] = $wert;
}
return $arr;
}

function gruppe($g1,$m1,$g2,$m2)
{
if(($g1==0)&&($g2==0))
{
return 'Zentrale';
}
if($g1<>$g2)
{
if($m1>0)
{
$text = 'Gruppe '.$g1.'/'.$m1.' bis '.$g2.'/'.$m2;
} else {
$text = 'Gruppe '.$g1.'-'.$g2;
}
} else {

if($m1>0)
{
$text = 'Melder '.$g1.'/'.$m1;
} else {
$text = 'Gruppe '.$g1;
}
}
return $text;
}

//Suche die KID von einem Kopf
function get_kid($tag1,$tag2='',$tag3='',$tag4='')
{
global $mysqli;
$tag1=trim($tag1);
$tag2=trim($tag2);
$tag3=trim($tag3);
$tag4=trim($tag4);
global $aid, $userinfo;
$q=mysqli_query($mysqli, "SELECT `kid` FROM `technik_nsc_kopf` WHERE `anlage` = '$aid' AND `tag1`='$tag1' AND `tag2`='$tag2' AND `tag3`='$tag3' AND `tag4`='$tag4' AND `mandant` = '$userinfo[mandant]'");
$dat=mysqli_fetch_array($q);
return $dat[kid];
}

function get_typ($typ)
{
global $ausgangsmodul,$mysqli;
$q5=mysqli_query($mysqli, "SELECT * FROM `technik_meldertypen` WHERE `typ`='$typ'");

if(mysqli_num_rows($q5)==0){
echo("<b>Ausgangsmodul-Typ $typ wurde nicht gefunden</b><br>"); print_r($ausgangsmodul);
return null;
} else {
$dat_typ=mysqli_fetch_array($q5);
return $dat_typ;
}
}


function no_null($str)
{
if(($str[0]=='0')&&($str<>'')){$str=(int)$str;} //Führende Nullen entfernen
return $str;
}

?>


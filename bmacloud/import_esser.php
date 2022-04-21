<?

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function file_get_contents_utf8($fn)
{
    $content = file_get_contents($fn);
    return mb_convert_encoding(
        $content,
        'UTF-8',
        mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true)
    );
}

//aid wird benoetigt, da mehrere Files pro Anlage gebraucht werden
function esser_einlesen($aid)
{

    global $userinfo, $debug, $programFilesFolder;

    $q = mysql_query("DELETE FROM `technik_esser_teilnehmer` WHERE `anlage`='$aid' AND `mandant` = '$userinfo[mandant]'");
    $q = mysql_query("DELETE FROM `technik_esser_gruppen` WHERE `anlage`='$aid' AND `mandant` = '$userinfo[mandant]'");
    $q = mysql_query("DELETE FROM `technik_esser_sgruppen` WHERE `anlage`='$aid' AND `mandant` = '$userinfo[mandant]'");
    $q = mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
    $q = mysql_query("DELETE FROM `technik_ansteuer` WHERE `anlage` = '$aid' AND `mandant` = '$userinfo[mandant]'");

    $FOUND_BEZEICHNER = 0;
    $Found_TXT = 0;

    //Durchlaufe die Files und Suche nach den Dateinamen
    $sql = "SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='$programFilesFolder' AND `mandant`='$userinfo[mandant]'";
    $q = mysql_query($sql);
    if ($debug) {
        echo $sql;
    }

    while ($file = mysql_fetch_array($q)) {

        if ($debug) {
            echo "File: $file[name]<br>";
        }

        if (stripos($file[name], 'Teilnehmer') > 5) {

            //Datei ist ein Teilnehmer
            if (strpos($file[name], '.txt') !== false) {
                $Found_TXT = 1;
            }
            teilnehmer(get_fid_path($file[fid]), $Found_TXT);
            $Found_TXT = 0;
            if ($debug) {
                echo ("Teilnehmer gefunden $file[name] END<br><br>");
            }
            $dat++;
        }
        if (stripos($file[name], '_Meldergruppen') > 1) {
            if (strpos($file[name], 'BEZEICHNER') !== false) {
                $FOUND_BEZEICHNER = 1;
            }
            //Datei ist Meldergruppe
            gruppen(get_fid_path($file[fid]), $FOUND_BEZEICHNER);
            $FOUND_BEZEICHNER = 0;
            if ($debug) {
                echo ("Meldergruppen gefunden $file[name]<br><br>");
            }
            $dat++;
        }
        if (stripos($file[name], '_Steuergruppen') > 1) {
            //Datei ist Steuergruppe
            sgruppen(get_fid_path($file[fid]));
            if ($debug) {
                echo ("Steuergruppendatei gefunden<br><br>");
            }
            $dat++;
        }
        if (stripos($file[name], '_Ansteuerungen') > 1) {
            //Datei ist Ansteuerung
            ansteuerung(get_fid_path($file[fid]));
            if ($debug) {
                echo ("Ansteuerungsdatei gefunden<br><br>");
            }
            $dat++;
        }
    }





    if ($dat > 1) {
        $q = mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
        $q = mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");

        // $q=mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");

        //Gruppen
        $q2 = mysql_query("SELECT * FROM `technik_esser_gruppen` WHERE `anlage`='$aid' AND `mandant` = '$userinfo[mandant]'");
        while ($g = mysql_fetch_array($q2)) {
            if ($g[gr] != 0) {
                $q3 = mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$g[gr]', `text`='$g[etage]', `mandant`='$userinfo[mandant]'");
                if ($debug) {
                    echo ("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$g[gr]', `text`='$g[etage]', `mandant`='$userinfo[mandant]' <br>");
                }
                $c_meldergruppen++;
            }
        }

        //Teilnehmer
        $q2 = mysql_query("SELECT * FROM `technik_esser_teilnehmer` WHERE `anlage`='$aid' AND `mandant` = '$userinfo[mandant]'");
        while ($t = mysql_fetch_array($q2)) {
            //Wenn der Teilnehmer keinen Text hat, den Gruppentext nehmen
            $q4 = mysql_query("SELECT * FROM `technik_esser_gruppen` WHERE `anlage` = '$aid' AND `gr`='$t[gruppe]' AND `mandant` = '$userinfo[mandant]'");
            $g = mysql_fetch_array($q4);
            if ($t[text] == "") {
                $text = $g[etage];
            } else {
                $text = $t[text];
            }
            $typ = trim($t[art]);

            if ($typ == "undefzusatztext") {
                if (strpos($text, "RM") !== false) {
                    $typ = "OT";
                }
                if (strpos($text, "AM") !== false) {
                    $typ = "OT";
                }
                if (strpos($text, "HM") !== false) {
                    $typ = "DKM";
                }
                if (strpos($text, "DKM") !== false) {
                    $typ = "DKM";
                }
                if (strpos($text, "Koppler") !== false) {
                    $typ = "Alarm";
                }
                if (strpos($text, "Sirene") !== false) {
                    $typ = "Voice";
                }
            }

            $q4 = mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$typ' AND `hersteller`='ESSER'");
            $mtyp = mysql_fetch_array($q4);

            $gruppe = $t[gruppe];
            $melder = $t[melder];
            $ring = $g[pl];
            $art = $mtyp[kurztext];
            $adresse = $t[adresse];
            $serial = $t[serial];
            $submelder = $mtyp[sirene];
            if ($mtyp[typ] == 'O2T/So' || $mtyp[typ] == 'O2T/Sp'  || $mtyp[typ] == 'O/So' || $mtyp[typ] == 'O2T/FSp' || $mtyp[typ] == 'O2T/F') {
                $submelder = '0';
            }

            $sql = "INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$gruppe', `melder`='$melder', 
`text`='$text', `art`='$art', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]',`submelder` = '$submelder', `steuer`='$mtyp[steuer]', `ring`='$ring', `typ`='$typ', `adresse`='$adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'";
            if ($debug) {
                echo ("Melder: $sql<br>");
            }
            $c_melder++;

            $q3 = mysql_query($sql);

            if ($debug) {
                echo ("SELECT * FROM `technik_meldertypen` WHERE `typ`='$typ' AND `hersteller`='ESSER'<br>");
            }

            //Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
            $qm = mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '" . $gruppe . "' AND `melder` = '" . $melder . "' AND `mandant` = '$userinfo[mandant]'");
            if (mysql_num_rows($qm) == 0) {
                $i1 = 0;
                $i2 = 0;
                $i3 = 0;
                $i4 = 0;
                $mod = ($melder % 4);

                if ($mod == 1) {
                    $i1 = '1';
                }
                if ($mod == 2) {
                    $i2 = '1';
                }
                if ($mod == 3) {
                    $i3 = '1';
                }
                if ($mod == 0) {
                    $i4 = '1';
                }

                //Wenn der Meldertyp einen vorgegebenen Pruefplan hat, dann diesen verwenden:
                if (($mtyp[i1] == 1) || ($mtyp[i2] == 1) || ($mtyp[i3] == 1) || ($mtyp[i4] == 1)) {
                    $i1 = $mtyp[i1];
                    $i2 = $mtyp[i2];
                    $i3 = $mtyp[i3];
                    $i4 = $mtyp[i4];
                }

                $sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '" . $gruppe . "',
`melder` = '" . $melder . "', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `submelder` = '$submelder',`mandant`='$userinfo[mandant]'";
                $qm = mysql_query($sql);
            }

            //Sonderfunktion, der O2T-Sounder auf 2 Pruefpunkte verteilen
            //this creates a parallel melder/melder_manuell for the O2T devices with the same values but with submelder = 1, so it can be checked as a Alarmierung
            if ($mtyp[typ] == 'O2T/So' || $mtyp[typ] == 'O2T/Sp'  || $mtyp[typ] == 'O/So' || $mtyp[typ] == 'O2T/FSp' || $mtyp[typ] == 'O2T/F') {
                // $art = 'Alarmierung';
                $typ = 'System';
                $q3 = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$gruppe', `melder`='$melder', `submelder`='1', 
`text`='$text', `art`='$art', `auto`='0', `manuell`='0', `steuer`='1', `ring`='$ring', `typ`='$typ', `adresse`='$adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");

                $sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '" . $gruppe . "',
`melder` = '" . $melder . "', `submelder` = '1', `i1`='1', `i2`='0', `i3`='0', `i4`='0', `mandant`='$userinfo[mandant]'";
                $qm = mysql_query($sql);
            }
        }

        //Steuergruppen
        $q2 = mysql_query("SELECT * FROM `technik_steuergruppen` WHERE `anlage`='$aid' AND `ereignis`='' AND `mandant` = '$userinfo[mandant]' ORDER BY `art`");

        while ($s = mysql_fetch_array($q2)) {

            $von = 0;
            $count = 0;

            $nr = $s[nr];
            $ttext = $s[ausl];
            $tansteuerung = $s[text];
            $tereignis = $s[ereignis];




            #Hier sind die Steuergruppen noch alle da, werden ausgelesen aus technik_esser_sgruppen
            #echo ("$nr - $ttext - $tansteuerung<br>");

            $q3 = mysql_query("SELECT * FROM `technik_steuergruppen` WHERE `anlage`='$aid' AND `ereignis`<>'' AND `nr`='$nr' AND `mandant` = '$userinfo[mandant]' ORDER BY `g1`");
            while ($sub = mysql_fetch_array($q3)) {
                if ($count == 0) {
                    $von = $sub[g1];
                }
                $nr = $sub[nr];
                $text = $sub[ausl];
                $bis = $sub[g2];
                $ereignis = $sub[ereignis] . ' Gruppe ' . $von . '-' . $bis;


                #Hier fehlen die Steuergruppen auf einmal
                #Es fehlen alle Steuergruppen die nicht mit einem Ereignis versehen sind, weil sie damit durch die Pruefung `ereignis`<>'' im obigen SQL SELECT rausfallen
                #Function ansteuerung definiert in diesem Fall keine Ereignise, da es ja keine Ansteuerung gibt
                #echo("Nr: $nr - Sub: $count - von:$von - bis:$bis<br>");





                if (($sub[g1] <> ($vorher + 1)) && ($count > 0)) {
                    $c_stg++;
                    // $sql = "INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$nr', `g1`='$von', `text`='$text', `ansteuerung`='$tansteuerung', `ereignis`='$ereignis', `mandant`='$userinfo[mandant]'";

                    if ($debug) {
                        echo ("Sub: " . $sql . "<br>");
                    }
                    $q4 = mysql_query($sql);
                    $vorher = "";
                    $count = 0;




                    //Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
                    $qman = mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr` = '" . $nr . "' AND `mandant` = '$userinfo[mandant]'");
                    if (mysql_num_rows($qman) == 0) {

                        //Steuergruppen immer in jedem Quartal
                        $i1 = 1;
                        $i2 = 1;
                        $i3 = 1;
                        $i4 = 1;

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
                         **/

                        $sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr` = '" . $nr . "', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4'";
                        $qman = mysql_query($sql);
                    }
                } else {
                    $count++;
                    $vorher = $sub[g2];
                }
            }

            if ($count > 0) {
                // $sql = "INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$nr', `g1`='$von', `text`='$text', `ansteuerung`='$tansteuerung', `ereignis`='$ereignis', `mandant`='$userinfo[mandant]'";
                $c_stg++;

                // $q4=mysql_query($sql);

                //Pruefplan berechnen fuer den Melder, nur wenn es noch keine manuellen Zeilen dafuer gibt
                $qman = mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr` = '" . $nr . "' AND `mandant` = '$userinfo[mandant]'");
                if (mysql_num_rows($qman) == 0) {

                    //Steuergruppen immer in jedem Quartal
                    $i1 = 1;
                    $i2 = 1;
                    $i3 = 1;
                    $i4 = 1;

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
                     **/

                    $sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr` = '" . $nr . "', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant` = '$userinfo[mandant]'";
                    $qman = mysql_query($sql);
                }
            }
        }



        msg((int)$c_melder . " Melder Importiert");
        msg((int)$c_meldergruppen . " Meldergruppen Importiert");
        $qst = mysql_query("SELECT * FROM `technik_steuergruppen` WHERE  `anlage`='$aid' AND `mandant` = '$userinfo[mandant]'");
        $counter_steuergruppen = mysql_num_rows($qst);
        if (!$counter_steuergruppen) $counter_steuergruppen = 0;
        msg((int)$counter_steuergruppen . " Steuergruppen Importiert");
        $qanst = mysql_query("SELECT * FROM `technik_ansteuer` WHERE  `anlage`='$aid' AND `mandant` = '$userinfo[mandant]'");
        $counter_ansteuer = mysql_num_rows($qanst);
        if (!$counter_ansteuer) $counter_ansteuer = 0;
        msg((int)$counter_ansteuer . " Ansteuerungen Importiert");
        // msg((int)$c_stg." Steuergruppen Importiert");

    } else {
        err("Keine passenden Dateinamen gefunden<br>
	Ben&ouml;tigt werden die Ansteuerungen, die Meldergruppen, die Steuergruppen und die Teilnehmer Dateien
");
    }
}




//1.Teil--------------------Teilnehmerimport--------------------------------------------
function teilnehmer($fname2, $Found_TXT)
{
    global $aid, $userinfo, $debug;
    $tln2 = file_get_contents_utf8($fname2);

    $pattern_melder = '@(.*?)Melder(.*?);(.*?);(.*)@is';
    $pattern_gruppe = '@(.*?)Meldergruppe(.*?);(.*?);(.*)@is';

    //Definition von Arraygroessen
    $doppel = 0;
    $first = 0;
    $zusatz = 0;

    if ($Found_TXT == 0) {
        $zeile = explode("\n", $tln2);
        $count = count($zeile);

        if ((strpos($zeile, "Zusatztexte") !== false) && ($first == 0)) {
            $zusatz = 1;
        }
        $first = 1;

        for ($i = 3; ($i <= ($count - 1)); $i++) {
            #$feld = explode(";", $zeile[$i]);
            #echo "$zeile[$i]<br>";
            // $feld =  preg_split( "/(;|,)/", $zeile[$i] );
            $feld =  preg_split("/(;)/", $zeile[$i]);
            $art;
            $g;
            $gruppe;
            $melder;
            $text;
            $fehler;
            $adresse;
            $serial;
            $found_zusatztext_melder = 0;

            #Urspruengliches Format gefunden
            if (strpos($feld[0], '""') !== false) {
                $column_count = count($feld);

                $art = substr($feld[3], 0, strlen($feld[3]));
                if ($column_count == 11) {
                    $g = substr($feld[9], 0, strlen($feld[9]));
                    $text = trim($feld[10]);
                    $serial = substr($feld[6], 0, strlen($feld[6]));
                } else if ($column_count == 10) {
                    $g = substr($feld[8], 0, strlen($feld[8]));
                    $text = trim($feld[9]);
                    $serial = substr($feld[5], 0, strlen($feld[5]));
                }
                $g = explode("/", $g);
                $gruppe = $g[0];
                $melder = $g[1];
                // $text = trim($feld[10]);
                $text = preg_replace("/[^A-Za-z0-9 + - _ .\/]/", "", $text);
                //Nur wenn " vorne, dann Kuerzen
                if (substr($text, 0, 1) == '"') {
                    $text = substr($text, 1);
                    $text = substr($text, 0, -1);
                }
                $fehler = 0;
                $adresse = substr($feld[1], 0, strlen($feld[1]));
                // $serial = substr($feld[6],0,strlen($feld[6]));
                $ward = "1";
            }
            #Format mit Zusatztexten gefunden
            else if ($zusatz == 1) {
                $art = "undefzusatztext";
                $line_cleaned = preg_replace("/[^0-9a-zA-Z;.\/]/", "", $zeile[$i]);
                if ($result = preg_match($pattern_melder, $line_cleaned, $subpattern)) {
                    $gm = $subpattern[2];
                    $temp = explode("/", $gm);
                    $gruppe = $temp[0];
                    $melder = $temp[1];
                    $text = $subpattern[3];
                    $found_zusatztext_melder = 1;
                }
                if ($result = preg_match($pattern_gruppe, $line_cleaned, $subpattern)) {
                    $gruppe = $subpattern[2];
                    $text = $subpattern[3];
                    $pl = " ";
                    $ue = " ";
                    $bart = " ";

                    $Complete_Text = $subpattern[4] . " " . $subpattern[5] . " " . $subpattern[6];
                    $Text_for_group = $Complete_Text;
                    $Name = $subpattern[4];
                    $Leitung = $subpattern[1];
                    $query = mysql_query("INSERT INTO `technik_esser_gruppen` SET `anlage`='$aid', `gr`='$gruppe', `etage`='$text', `pl`='$pl', `bart`='$bart', `ue`='$ue', `mandant`='$userinfo[mandant]' ");
                }
                $ward = "2";
            }
            #Neues Format gefunden
            else {
                $art = substr($feld[2], 0, strlen($feld[2]));
                $g = substr($feld[8], 0, strlen($feld[8]));
                $g = explode("/", $g);
                $gruppe = $g[0];
                $melder = $g[1];
                $text = trim($feld[9]);
                //Nur wenn " vorne, dann Kuerzen
                if (substr($text, 0, 1) == '"') {
                    $text = substr($text, 1);
                    $text = substr($text, 0, -1);
                }
                $fehler = 0;
                $adresse = substr($feld[0], 0, strlen($feld[0]));
                $serial = substr($feld[5], 0, strlen($feld[5]));
                $ward = "3";
            }

            //Import
            if ($art == "") {
                $q1++;
                if ($debug) {
                    echo ("art:$art<p>");
                }
            } else if ($art == "undefzusatztext") {
                if (($found_zusatztext_melder == 1) && ($melder != 0)) {

                    #Undefined Meldertype in file, trying to figure out type from text
                    if (strpos($text, "RM") !== false) {
                        $art = "OT";
                    }
                    if (strpos($text, "AM") !== false) {
                        $typ = "OT";
                    }
                    if (strpos($text, "HM") !== false) {
                        $art = "DKM";
                    }
                    if (strpos($text, "DKM") !== false) {
                        $art = "DKM";
                    }
                    if (strpos($text, "Koppler") !== false) {
                        $art = "Alarm";
                    }
                    if (strpos($text, "Sirene") !== false) {
                        $art = "Voice";
                    }

                    $query = mysql_query("INSERT INTO `technik_esser_teilnehmer` SET `art`='$art', `gruppe`='$gruppe', `melder`='$melder', `text`='$text', `anlage`='$aid', `adresse`='$adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");
                    if ($debug) {
                        echo "Teilnemer 1: INSERT INTO `technik_esser_teilnehmer` SET `art`='$art', `gruppe`='$gruppe', `melder`='$melder', `text`='$text', `anlage`='$aid', `adresse`='$adresse', `serial`='$serial', `mandant`='$userinfo[mandant]' <br>";
                    }
                    $found_zusatztext_melder = 0;
                    $w1++;
                }
            } else {
                $query = mysql_query("INSERT INTO `technik_esser_teilnehmer` SET `art`='$art', `gruppe`='$gruppe', `melder`='$melder', `text`='$text', `anlage`='$aid', `adresse`='$adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");
                if ($debug) {
                    echo "Teilnemer 2: INSERT INTO `technik_esser_teilnehmer` SET `art`='$art', `gruppe`='$gruppe', `melder`='$melder', `text`='$text', `anlage`='$aid', `adresse`='$adresse', `serial`='$serial', `mandant`='$userinfo[mandant]' ward= $ward <br>";
                }
                $w1++;
            }
        }
    } else {
        $zeile = explode("\n", $tln2);
        $count = count($zeile);

        $Pattern_Melder_Gruppe = '@(.*);(.*);(\d*);Gruppe.(\d*).Melder.(\d*);(.*)@is';

        for ($i = 0; ($i <= ($count - 1)); $i++) {

            if ($result = preg_match($Pattern_Melder_Gruppe, $zeile[$i], $subpattern)) {

                $art = "-1";
                $g = " ";
                $gruppe = " ";
                $melder = " ";
                $text = " ";
                $fehler = " ";
                $adresse = "0";
                $serial = "0";

                $gruppe = $subpattern[4];
                $melder = $subpattern[5];
                $meldertyp = $subpattern[3];
                $text = $subpattern[2];

                if (strpos($meldertyp, "300800019") !== false) {
                    $art = "DKM";
                }
                if (strpos($meldertyp, "300800013") !== false) {
                    $art = "OT";
                }
                if (strpos($meldertyp, "300800016") !== false) {
                    $art = "O";
                }


                $query = mysql_query("INSERT INTO `technik_esser_teilnehmer` SET `art`='$art', `gruppe`='$gruppe', `melder`='$melder', `text`='$text', `anlage`='$aid', `adresse`='$adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");
                if ($debug) {
                    echo "Teilnemer 3: INSERT INTO `technik_esser_teilnehmer` SET `art`='$art', `gruppe`='$gruppe', `melder`='$melder', `text`='$text', `anlage`='$aid', `adresse`='$adresse', `serial`='$serial', `mandant`='$userinfo[mandant]' <br>";
                }
            }
        }
    }
}

//2.Teil--------------------Gruppenimport--------------------------------------------

function gruppen($fname2, $FOUND_BEZEICHNER)                                            #Datei ist vorhanden
{
    global $aid, $userinfo;
    $grp2 = file_get_contents_utf8($fname2);

    //Definition von Arraygroessen
    $doppel = 0;

    $zeile = explode("\n", $grp2);
    $count = count($zeile);


    for ($i = 3; ($i <= ($count - 1)); $i++) {

        $feld = explode(";", $zeile[$i]);
        if(count($feld) == 1){
            // tab separated csv file
            $feld = explode("\t", $zeile[$i]);
        }

        if ($FOUND_BEZEICHNER == 0) {
            $gr = substr($feld[0], 0, strlen($feld[0]));
            $etage = substr($feld[1], 0, strlen($feld[1]));
            $pl = substr($feld[2], 0, strlen($feld[2]));
            $bart = substr($feld[4], 0, strlen($feld[4]));
            $feld6 = substr($feld[6], 0, strlen($feld[6]));
            if ($feld[6] == "---") {
                $gr = substr($feld[0], 0, strlen($feld[0]));
                $query = mysql_query("INSERT INTO `technik_esser_teilnehmer` SET `anlage`='$aid', `art`='RZ', `gruppe`='$gr', `melder`='1', `mandant`='$userinfo[mandant]'");
                if ($debug) {
                    echo "Teilnemer 4: INSERT INTO `technik_esser_teilnehmer` SET `anlage`='$aid', `art`='RZ', `gruppe`='$gr', `melder`='1', `mandant`='$userinfo[mandant]' <br>";
                }
                #echo ("INSERT INTO `technik_esser_teilnehmer` SET `anlage`='$aid', `art`='RZ', `gruppe`='$gr', `melder`='1', `mandant`='$userinfo[mandant]' <br>");
            }
            $ue = substr($feld[7], 0, strlen($feld[7]));
        } else {
            $gr = substr($feld[1], 0, strlen($feld[1]));
            $etage = substr($feld[2], 0, strlen($feld[2]));
            $pl = substr($feld[3], 0, strlen($feld[3]));
            $bart = substr($feld[5], 0, strlen($feld[5]));
            $feld6 = substr($feld[7], 0, strlen($feld[7]));
            $bezeichner_gruppe = substr($feld[0], 0, strlen($feld[0]));
            #$etage = $bezeichner_gruppe." - ".$etage;
            if ($feld[7] == "---") {
                $gr = substr($feld[1], 0, strlen($feld[1]));
                $query = mysql_query("INSERT INTO `technik_esser_teilnehmer` SET `anlage`='$aid', `art`='RZ', `gruppe`='$gr', `melder`='1', `mandant`='$userinfo[mandant]'");
                if ($debug) {
                    echo "Teilnemer 5: INSERT INTO `technik_esser_teilnehmer` SET `anlage`='$aid', `art`='RZ', `gruppe`='$gr', `melder`='1', `mandant`='$userinfo[mandant]' <br>";
                }
            }
            $ue = substr($feld[8], 0, strlen($feld[8]));
        }
        $fehler = 0;


        //Import
        if ($gr == "") {
            $q1++;
        } else {
            $etage = preg_replace("/[^A-Za-z0-9 + - _ .\/]/", "", $etage);
            $query = mysql_query("INSERT INTO `technik_esser_gruppen` SET `anlage`='$aid', `gr`='$gr', `etage`='$etage', `pl`='$pl', `bart`='$bart', `ue`='$ue', `mandant`='$userinfo[mandant]' ");
            if ($debug) {
                echo ("$gr INSERT INTO `technik_esser_gruppen` SET `anlage`='$aid', `gr`='$gr', `etage`='$etage', `pl`='$pl', `bart`='$bart', `ue`='$ue', `mandant`='$userinfo[mandant]' <br>");
            }
            $w1++;
        }
    }
}


//3.Teil--------------------SteuerGruppenimport--------------------------------------------

function sgruppen($fname2)                                                        #Datei ist vorhanden
{
    global $aid, $userinfo, $debug;
    $grp2 = file_get_contents_utf8($fname2);


    #	/** if($debug)
    #	{
    #	echo("<b>Steuergruppendatei</b>: $grp2");	
    #	} **/

    //Definition von Arraygroessen
    $doppel = 0;

    $zeile = explode("\n", $grp2);
    $count = count($zeile);

    #for ($r=0; $r < $count; $r++){
    #	echo "$zeile[$r]<br>";
    #}

    for ($i = 3; ($i <= ($count - 1)); $i++) {
        // $eingang_line = preg_replace("/[^A-Za-z0-9 -öäüÖÄÜß \t\/\,\.\-]/","",$zeile[$i]);

        // $feld = explode(";", $eingang_line);
        $feld = str_getcsv($zeile[$i], ";");

        $nr = $feld[0];
        $zusatztext = $feld[1];
        $art_des_Ausgangs = $feld[2];
        $installationsort = $feld[3];
        $fehler = 0;

        if ($debug) {
            // print_r($feld);
            echo ("Text: $zusatztext   --- Zeile: $zeile[$i]<br>");
        }
        //Import
        if ($nr == "") {
            $q1++;
        } else {
            // $query=mysql_query("INSERT INTO `technik_esser_sgruppen` SET `anlage`='$aid', `art`='$art', `text`='$text', `pl`='$pl', `mandant`='$userinfo[mandant]'");


            $sql = "INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$nr', `text`='" . $art_des_Ausgangs . "', `ansteuerung`='" . $zusatztext . "', `pl`='$installationsort', `mandant`='$userinfo[mandant]'";
            $query = mysql_query($sql);
            if ($debug) {
                echo ("A: " . $sql . "<br>");
            }

            $w1++;
        }
    }
}


//4.Teil--------------------Ansteuergruppenimport--------------------------------------------

function ansteuerung($fname2)                                                         #Datei ist vorhanden
{
    global $aid, $userinfo, $debug;
    $grp2 = file_get_contents_utf8($fname2);

    //Definition von Arraygroessen
    $doppel = 0;

    $zeile = explode("\n", $grp2);
    $count = count($zeile);

    for ($i = 3; ($i <= ($count - 1)); $i++) {
        $feld = str_getcsv($zeile[$i], ";");
        #feld[0] <--- Ereignis
        #feld[1] <--- Gruppe von bis
        #feld[2] <--- Steuerung
        #feld[4] <--- Text
        $ereignis = $feld[0];
        $gruppe_von = $feld[1];
        $ansteuerung = $feld[2];

        $feld1_arr = explode(" ", $gruppe_von);
        $g1 = (int)$feld1_arr[1];
        $g2 = (int)$feld1_arr[4];
        if ($g2 == 0) {
            $g2 = $g1;
        }
        //match the number
        preg_match('/\d+/', $ansteuerung, $max);
        $art = $max[0];
        //Import
        if ($art == "") {
            $q1++;
        } else {
            $sql = "INSERT INTO `technik_ansteuer` SET `anlage`='$aid',`art`='$art', `ausl`='$ansteuerung', `ereignis`='$ereignis $gruppe_von', `g1`='$g1', `g2`='$g2', `mandant`='$userinfo[mandant]'";
            $query = mysql_query($sql);
            if ($debug) {
                echo ("<br><b>B: $sql</b><br>");
            }
            $w1++;
        }
    }
}

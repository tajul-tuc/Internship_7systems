<?

$debug = false;

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

$Anzahl_Melder = 0;
$Anzahl_Meldergruppen = 0;
$ignoredLines = 0;
function file_get_contents_utf8($fn)
{
    $content = file_get_contents($fn);
    return mb_convert_encoding($content, 'UTF-8',
        mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
}

//aid wird benötigt, da mehere Files pro Anlage gebraucht werden
function fp1200_einlesen($fid, $aid)
{

    global $aid, $userinfo, $programFilesFolder, $Anzahl_Melder, $Anzahl_Meldergruppen,$ignoredLines;
    $Anzahl_Melder = 0;
    $Anzahl_Meldergruppen = 0;
    $ignoredLines=0;

#Delete old entries
    $qd1 = mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
    $qd2 = mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
    $qd3 = mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");

#File suchen
    $q = mysql_query("SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='$programFilesFolder' AND `mandant`='$userinfo[mandant]'");
    if ($debug) {
        echo ("SELECT * FROM `files` WHERE `aid`='$aid' AND `ordner`='$programFilesFolder' AND `mandant`='$userinfo[mandant]'");
    }

    $FileIDs = array();
    $FileNames = array();
    $FileCounter = 0;

    while ($file = mysql_fetch_array($q)) {
        $FileIDs[$FileCounter] = $file[fid];
        $FileNames[$FileCounter] = $file[name];
        $FileCounter++;
        $c++;
    }

    #File suchen für Melder, Meldergruppen und Steuergruppen
    for ($ic = 0; $ic < $FileCounter; $ic++) {
        $fp = get_fid_path($FileIDs[$ic]);
        //Datei enthält Melder
        melderdatei($fp,$FileNames[$ic]);
    }

    msg($Anzahl_Melder . " Melder Importiert");
    msg($Anzahl_Meldergruppen . " Meldergruppen Importiert");
}

function remove_spaces($str)
{
    $str = trim(str_replace('  ', ' ', $str));
    return $str;
}

function melderdatei($fp, $mFilename)
{
    global $aid, $userinfo, $Anzahl_Melder, $Anzahl_Meldergruppen,$ignoredLines;
    $ignoredLines=0;
    //data structure variables start
    $ring_offset = 0;
    $ring_lenght = 10;
    $group_offset = 90;
    $group_lenght = 11;
    $address_offset = 11;
    $address_lenght = 11;
    $status_offset = 22;
    $status_lenght = 11;
    $taglevel_offset = 73;
    $taglevel_lenght = 11;
    $type_offset = 84;
    $type_lenght = 6;
    $loop_offset = 90;
    $loop_lenght = 11;
    $text1_offset = 101;
    $text1_lenght = 41;
    $text2_offset = 142;
    $text2_lenght = 41;
    //data structure variables end

    $line = file_get_contents_utf8($fp);
    $single_lines = preg_split('/\n|\r\n?/', $line);
    $numb_lines = sizeof($single_lines);
    for ($i = 1; $i < $numb_lines - 1; $i++) {
        $split_temp = $single_lines[$i];
        if (strlen($split_temp) < 180) {
            continue;
        }

        $Ring = remove_spaces(substr($split_temp, $ring_offset, $ring_lenght));
        $Gruppe = (int) remove_spaces(substr($split_temp, $group_offset, $group_lenght)); //overrider below
        $Melder = 0;
        $Adresse = remove_spaces(substr($split_temp, $address_offset, $address_lenght));
        $Text = remove_spaces(substr($split_temp, $text2_offset, $text2_lenght));
        $Art = remove_spaces(substr($split_temp, $type_offset, $type_lenght));
        $Bezeichnung = "";
        $Kommentar = remove_spaces(substr($split_temp, $text1_offset, $text1_lenght));
        $Text=trim($Kommentar.' '.$Text);
        $group_melder_primary = '/(\d+)\D*?\/\D*?(\d+)/i';
        $group_melder_secondary = '/\D*(\d+)\D+(\d+)/i';
        $Auto='1';
        $Manuell='0';

        $result = array();
        if (strlen($Kommentar) < 3) {
            $ignoredLines++;
            continue;
        }

        preg_match($group_melder_primary, $Kommentar, $result);
        if (count($result) < 3) {
            preg_match($group_melder_secondary, $Kommentar, $result);{
                if (count($result) < 3) {
                    $ignoredLines++;
                    continue;
                }
            }
        }else{
            $tempGr=(int) $result[1];
            $tempMe=(int) $result[2];
            if($tempGr==0||$tempMe==0){
                preg_match($group_melder_secondary, $Kommentar, $result);{
                    if (count($result) < 3) {
                        $ignoredLines++;
                        continue;
                    }
                }                
            }
        }

        $showImportWarning='';
        if ($Gruppe > 0 && $Gruppe != ((int) $result[1])) {
            $showImportWarning= '<small>Warnung: verschiedene Gruppen in '.$mFilename.' Linien ' . ($i+1) . '. MG:'.$Gruppe.' und Text:'.$result[1].'/'.$result[2].'. Hinzugefügt als <i>'.$result[1].'/'.$result[2].'</i><br></small>';
        }
        $Gruppe = (int) $result[1];
        $Melder = (int) $result[2];

        if ($Gruppe == 0) {
            $ignoredLines++;
            continue;
        }

        if ($Melder == 0) {
            $ignoredLines++;
            continue;
        }


        #Meldertypen klassifizieren
        if (stripos($Art, 'Kein') !== false) {
            $ignoredLines++;
            continue;
        }
        $Typ = "2";
        if (stripos($Art, 'MUL') !== false) { #Multisensor
        $Typ = "1";
        }
        if (stripos($Art, 'SONSTIGES') !== false) { #Sonstiges
        $Typ = "2";
        }
        if ((stripos($Art, 'E/A') !== false)) { #Steuermodul
        $Typ = "3";
        }
        if ((stripos($Art, 'SGM') !== false) || (stripos($Art, 'LCC') !== false)) { #Sirene
            $ignoredLines++;
            continue;
        $Typ = "4";
        }
        if (stripos($Art, 'OPT') !== false) { #Optisch-Thermischer Melder
        $Typ = "5";
        }
        if (stripos($Art, 'DKM') !== false) { #Druckknopfmelder
            $Auto='0';
            $Manuell='1';
        $Typ = "6";
        }
        if (stripos($Art, 'TEMP') !== false) { #Thermischer Melder
        $Typ = "7";
        }
        if (stripos($Art, 'EMG') !== false) { #Eingang/Steuermodul
        $Typ = "3";
        }

        if(strlen($showImportWarning)>5){
            echo $showImportWarning;
        }

        #Melderdetails aus Datenbank holen
        $q4 = mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='2'");
        $mtyp = mysql_fetch_array($q4);

        //$art = $mtyp[kurztext];
        //$adresse = $t[adresse];
        $serial = $t[serial];

        #GROUP TECHNIK
        $qcheck = mysql_query("SELECT count(*) FROM `technik_gruppe` WHERE `anlage`='$aid' AND `gruppe`='$Gruppe' AND  `mandant`='$userinfo[mandant]'");
        $mcheck = mysql_fetch_array($qcheck);
        if ($mcheck[0] < 1) {
            $mg = mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppe', `text`='', `mandant`='$userinfo[mandant]'");
            $Anzahl_Meldergruppen++;
        }
        #Melder in Datenbank eintragen
        $sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Gruppe', `melder`='$Melder', `text`='$Text', `art`='$Art', `auto`='$Auto', `manuell`='$Manuell', `steuer`='$mtyp[steuer]', `ring`='$Ring', `typ`='$Typ', `adresse`='$Adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");
        $Anzahl_Melder++;

        #Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
        $qm = mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '" . $Gruppe . "' AND `melder` = '" . $Melder . "' AND `mandant` = '$userinfo[mandant]'");
        if (mysql_num_rows($qm) == 0) {
            $i1 = 0;
            $i2 = 0;
            $i3 = 0;
            $i4 = 0;

            $mod = ($Melder % 4);
            if ($mod == 1) {$i1 = '1';}
            if ($mod == 2) {$i2 = '1';}
            if ($mod == 3) {$i3 = '1';}
            if ($mod == 0) {$i4 = '1';}

            #Wenn der Meldertyp einen vorgegebenen Prüfplan hat, dann diesen verwenden:
            if (($mtyp[i1] == 1) || ($mtyp[i2] == 1) || ($mtyp[i3] == 1) || ($mtyp[i4] == 1)) {
                $i1 = $mtyp[i1];
                $i2 = $mtyp[i2];
                $i3 = $mtyp[i3];
                $i4 = $mtyp[i4];
            }
            #Prüfplan eintragen in Datenbank

            $sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '$Gruppe',
            `melder` = '$Melder', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
            //echo "<BR>3)++".$sql."++";
            $qm = mysql_query($sql);
        }

    }
    if($ignoredLines>0){
        echo '<small><i>'. $mFilename.'</i> - '.$ignoredLines.' Textzeilen ignoriert<br></small>';
    }

}

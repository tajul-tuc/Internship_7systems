<?

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function file_get_contents_utf8($fn)
{
    $content = file_get_contents($fn);
    return mb_convert_encoding($content, 'UTF-8',
        mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
}

$pattern_melderfile = '@(.*)melder(.*)@isx';
$pattern_meldergruppenfile = '@(.*)gruppe(.*)@isx';
$pattern_steuergruppenfile = '@(.*)steuerungen(.*)@isx';

$pattern_meldergruppenfile_id = '@(.*)-Gruppe-(.*)-Typ-(.*)-Kundentext-(.*)-Kommentar-(.*)@isx';
$pattern_melderfile_id = '@(.*)-Seriennummer-(.*)@isx';
$pattern_steuergruppenfile_id = '@(.*)-Index-(.*)@isx';

$pattern_xps_file = '@(.*).xps@isx';

function dc3500_einlesen($fid, $aid)
{
    global $aid, $userinfo, $programFilesFolder,
    $pattern_melderfile, $pattern_meldergruppenfile, $pattern_steuergruppenfile,
    $pattern_meldergruppenfile_id, $pattern_melderfile_id, $pattern_steuergruppenfile_id,
        $pattern_xps_file, $debug;

    $Anzahl_Melder = 0;
    $Anzahl_Meldergruppen = 0;
    $Anzahl_Steuerungen = 0;

    $q = mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
    $q = mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
    $q = mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
    $q = mysql_query("DELETE FROM `technik_ansteuer` WHERE `anlage`='$aid' AND `mandant` = '$userinfo[mandant]'");
    $c = 0;
    $fileid = 0;

    $delete_first_group_line = 0;

    //$debug = false;

    $pattern_melderfile = '@(.*)melder(.*)@isx';
    $pattern_meldergruppenfile = '@(.*)gruppe(.*)@isx';
    $pattern_steuergruppenfile = '@(.*)steuerungen(.*)@isx';

    $pattern_meldergruppenfile_id = '@(.*)-Gruppe-(.*)-Typ-(.*)-Kundentext-(.*)-Kommentar-(.*)@isx';
    $pattern_melderfile_id = '@(.*)-Seriennummer-(.*)@isx';
    $pattern_steuergruppenfile_id = '@(.*)-Index-(.*)@isx';

    $pattern_xps_file = '@(.*).xps@isx';

#File suchen
    $q = mysql_query("SELECT * FROM `files` WHERE `aid`='$aid'  AND `ordner` = '$programFilesFolder'  AND `mandant`='$userinfo[mandant]'");
    if ($debug) {
        echo ("SELECT * FROM `files` WHERE `aid`='$aid'  AND `ordner` = '$programFilesFolder'  AND `mandant`='$userinfo[mandant]'");
    }

    $FileIDs = array();
    $FileNames = array();
    $FileCounter = 0;

    while ($file = mysql_fetch_array($q)) {
        if ($debug) {echo "<br>$c: File: $file[name]<br>\n";
            echo "<br>aid: $file[aid]<br>\n";
            echo "<br>fid: $file[fid]<br> $filepath<br>\n";}
        $FileIDs[$FileCounter] = $file[fid];
        $FileNames[$FileCounter] = $file[name];
        $FileCounter++;
        $c++;
    }

    #File suchen für Melder, Meldergruppen und Steuergruppen - Determine file type by content
    for ($ic = 0; $ic < $FileCounter; $ic++) {
        $fp = get_fid_path($FileIDs[$ic]);
        $line_t = file_get_contents_utf8($fp);
        $line_test = str_replace("<", "---", $line_t);
        $line_test = str_replace(">", "---", $line_test);
        $line_array = explode("Worksheet", $line_test);
        $first_line_temp = $line_array[1];
        $first_split_line = explode("Row", $line_test);
        $first_line = $first_split_line[1];
        $Found_correct_filename = 0;
        if (preg_match($pattern_xps_file, $FileNames[$ic])) {
            $Anzahl_Gruppen_Melder = xpsdatei($fp, $FileIDs[$ic], $FileNames[$ic]);
            $Temp_Return = explode("-", $Anzahl_Gruppen_Melder);
            $Anzahl_Melder += (int) $Temp_Return[1];
            $Anzahl_Meldergruppen += (int) $Temp_Return[0];
            $Anzahl_Steuerungen += (int) $Temp_Return[2];
        } else {

            if (preg_match($pattern_melderfile, $FileNames[$ic])) {
                if (preg_match($pattern_meldergruppenfile, $FileNames[$ic])) {
                    #Meldergruppendatei gefunden, wird weiter unten bearbeitet
                } else {
                    #Melderdatei gefunden
                    $Anzahl_Melder += melderdatei($fp);
                }
                $Found_correct_filename = 1;
            }
            if (preg_match($pattern_meldergruppenfile, $FileNames[$ic])) {
                //Datei enthält Meldergruppen
                $Anzahl_Meldergruppen += meldergruppendatei($fp);
                $Found_correct_filename = 1;
            }
            if (preg_match($pattern_steuergruppenfile, $FileNames[$ic])) {
                //Datei enthält Steuergruppen
                $Anzahl_Steuerungen += steuerungsdatei($fp);
                $Found_correct_filename = 1;
            }
            if ($Found_correct_filename == 0) {
                #Try to determine filetype by first line
                if (preg_match($pattern_meldergruppenfile_id, $first_line)) {
                    $Anzahl_Meldergruppen += meldergruppendatei($fp);
                }
                if (preg_match($pattern_melderfile_id, $first_line)) {
                    $Anzahl_Melder += melderdatei($fp);
                }
                if (preg_match($pattern_steuergruppenfile_id, $first_line)) {
                    $Anzahl_Steuerungen += steuerungsdatei($fp);
                }
            }
        }

    }

    msg($Anzahl_Melder . " Melder Importiert");
    msg($Anzahl_Meldergruppen . " Meldergruppen Importiert");
    msg($Anzahl_Steuerungen . " Steuerungen Importiert");

}

function xpsdatei($fp, $fid, $fullfilename)
{
    global $aid, $userinfo, $filepath,
    $pattern_melderfile, $pattern_meldergruppenfile, $pattern_steuergruppenfile,
    $pattern_meldergruppenfile_id, $pattern_melderfile_id, $pattern_steuergruppenfile_id,
        $pattern_xps_file;

    $Anzahl_Gruppen = 0;
    $Anzahl_Melder = 0;
    $Anzahl_Steurung = 0;

    $Meldergroups = array();

    $Gruppen_Array = array();

    $FPAGE_Array = array();
    $UNICODE_Array = array();

    $pattern_group_overview = 'Gruppe(.*)BMZ.3500(.*)Version@isx';

    $pattern_handfeuermelder = '@(.*)PL.3300.PBD(.*)@isx';
    $pattern_optischermelder = '@(.*)PL.3300.O(.*)@isx';
    $pattern_multimelder = '@(.*)PL.3300.OTi(.*)@isx';
    $pattern_thermomelder = '@(.*)PL.3300.T(.*)@isx';
    $pattern_sounder = '@(.*)AOM.3301.LS(.*)@isx';

    $zip = new ZipArchive;
    $res = $zip->open($fp);
    if ($res === true) {
        $Temp_Filepath = $filepath . "XPS/" . $fid . "/";
        $zip->extractTo($Temp_Filepath);
        $zip->close();
    }

    $FPAGE_Filepath = $Temp_Filepath . "Documents/1/Pages/";

    if ($handle = opendir($FPAGE_Filepath)) {
        while (false !== ($entry = readdir($handle))) {
            if (stripos($entry, 'fpage') !== false) {
                $fpagefile = $FPAGE_Filepath . $entry;
                array_push($FPAGE_Array, $fpagefile);
            }
        }
        closedir($handle);
    }

    foreach ($FPAGE_Array as $key => $value) {
        $line = file_get_contents_utf8($value);
        $line_mod = str_replace("<", "---", $line);
        $line_mod = str_replace(">", "---", $line_mod);

        $line_split_1 = explode("UnicodeString=\"", $line_mod);
        $Unicode_Parts_1 = sizeof($line_split_1);
        for ($k = 1; $k < $Unicode_Parts_1; $k++) {
            $line_split_2 = explode("\"", $line_split_1[$k]);
            $Unicode_Strings = $line_split_2[0];
            array_push($UNICODE_Array, $Unicode_Strings);
        }

    }

    $Unicode_String = implode(";", $UNICODE_Array);

    if (preg_match($pattern_steuergruppenfile, $fullfilename)) {
        if (strpos($Unicode_String, ";Kommentar;") !== false && //check that we are working with correct format
            strpos($Unicode_String, ";Aktiv;Index;") !== false &&
            strpos($Unicode_String, ";Quelle Typ;") !== false) {
            $Unicode_String = explode(";Kommentar;", $Unicode_String);
            $tempStr = "";
            for ($i = 1; $i < count($Unicode_String); $i++) {
                if (strpos($Unicode_String, ";Aktiv;Index;") !== false) {
                    $Unicode_String[$i] = (";" . explode(";Aktiv;Index;", $Unicode_String[$i], 2)[0]);
                }
                $regexSeit = "/;Seite\s{1,3}\d{1,3};?/";
                if (preg_match($regexSeit, $Unicode_String[$i]) > 0) {
                    $Unicode_String[$i] = preg_split($regexSeit, $Unicode_String[$i], 2)[0];
                }
                $tempStr .= ";" . $Unicode_String[$i];
            }
            $Unicode_String = ";" . str_replace(";;", ";", trim($tempStr, ";"));
            $tempStr = "";
            $matches = array();
            $Unicode_String = preg_split("/;\s?(\d{4})\s?-?\s?;/", $Unicode_String, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY); //split to table lines

            //$Unicode_String = implode("<hr>\n", $Unicode_String);
            //echo $Unicode_String;
        }
        for ($i = 0; $i < count($Unicode_String); $i = $i + 2) {
            $tempStr = explode(";", $Unicode_String[$i + 1]);
            if (count($tempStr) < 10) {
                continue;
            }
            for ($k = 0; $k < count($tempStr); $k++) {
                $tempStr[$k] = trim($tempStr[$k]);
                $tempStr[$k] = str_replace("  ", " ", $tempStr[$k]);
                $tempStr[$k] = trim($tempStr[$k], "[");
                $tempStr[$k] = trim($tempStr[$k], "]");
                $tempStr[$k] = trim($tempStr[$k], "-");
                $tempStr[$k] = trim($tempStr[$k], ";");
                $tempStr[$k] = trim($tempStr[$k]);
                $tempStr[$k] = trim($tempStr[$k], "[");
                $tempStr[$k] = trim($tempStr[$k], "]");
                $tempStr[$k] = trim($tempStr[$k], "-");
                $tempStr[$k] = trim($tempStr[$k]);
            }

            $startIndex = 0;
            if (is_numeric($tempStr[0])) {
                $startIndex = 1;
            }
            $Index_int = (int) ltrim(trim(trim(trim($Unicode_String[$i]), "-")), "0");
            $Quelle_Typ = trim($tempStr[$startIndex]);
            $Quelle_Meldung = "";
            $Ansteuerung = "";
            for ($k = 0; $k < count($tempStr); $k++) {
                if (strlen($tempStr[$k]) > 0) {
                    if (is_numeric($tempStr[$k]) && $k == 0) {
                        $Quelle_Meldung .= "" . $tempStr[$k] . "#";
                    } else {
                        $Quelle_Meldung .= "-" . $tempStr[$k];
                    }
                }
            }
            $Quelle_Meldung = trim($Quelle_Meldung, "-");

            for ($k = 5; $k < count($tempStr) - 5; $k++) {
                if (strlen($tempStr[$k]) > 0) {
                    $Ansteuerung .= "-" . $tempStr[$k];
                }
            }
            $Ansteuerung = trim($Ansteuerung, "-");

            $Ereignis = trim($tempStr[count($tempStr) - 3] . " " . $tempStr[count($tempStr) - 2]);

            if ($Index_int < 1) {
                continue;
            }
            //echo $Unicode_String[$i+1] . "<hr>";
            #Steuerungen in Datenbank eintragen
            $sql = "INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$Index_int', `g1`='$Quelle_Typ', `text`='$Quelle_Meldung', `ansteuerung`='$Ansteuerung', `ereignis`='$Ereignis', `mandant`='$userinfo[mandant]'";
            $st = mysql_query($sql);
            //Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
            $qman = mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr`='$Index_int' AND `mandant` = '$userinfo[mandant]'");
            if (mysql_num_rows($qman) == 0) {
                //Steuergruppen immer in jedem Quartal
                $i1 = 1;
                $i2 = 1;
                $i3 = 1;
                $i4 = 1;
                //todo --- `technik_ansteuer` insert data to this table (OR use G1 field may be...)
                $sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr`='$Index_int', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
                $qman = mysql_query($sql);
            }

            $Anzahl_Steurung++;

        }

    } else {

        #Meldegruppen extrahieren und einlesen
        if (preg_match("@BMZ(.*?)3500(.*?)Version(.*?)Adresse(.*?)Kommentar@isx", $Unicode_String, $subpattern)) {
            $Melder_Gruppen_Temp_2 = preg_split("/Brandmeldergruppe/", $subpattern[3]);
            $Gruppenanzahl = sizeof($Melder_Gruppen_Temp_2);
            $Current_Group = 0;
            for ($d = 0; $d < $Gruppenanzahl; $d++) {
                if ($d == 0) {
                    $Temp_Number_Split = explode(";", $Melder_Gruppen_Temp_2[$d]);
                    array_pop($Temp_Number_Split);
                    $Previous_Group = array_values(array_slice($Temp_Number_Split, -1))[0];
                } else {
                    $Temp_Line_Split = explode(";", $Melder_Gruppen_Temp_2[$d]);
                    array_pop($Temp_Line_Split);
                    $New_Group = array_values(array_slice($Temp_Line_Split, -1))[0];

                    $Name = "";
                    $numb_lines = sizeof($Temp_Line_Split);
                    for ($i = 2; $i < $numb_lines - 1; $i++) {
                        $Name .= $Temp_Line_Split[$i];
                    }

                    if ($Previous_Group != 0) {
                        $mg = mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Previous_Group', `text`='$Name', `mandant`='	$userinfo[mandant]'");
                        $Previous_Group = $New_Group;
                        array_push($Gruppen_Array, $Previous_Group);
                        $Anzahl_Gruppen++;
                    }
                }
            }
        }

        #Melder extrahieren und einlesen
		$Meldergroups_Temp = preg_split("/1.BMZ(.*?)3500(.*?)Version(.*?)Adresse(.*?)Kommentar/", $Unicode_String);

		foreach ($Meldergroups_Temp as $key => $value){
			$Temp_Counter = 0;
			$Meldergroups_Temp_2 = preg_split("/;(\d*?);(\d*?);(\d*?);(\d*?);(\d*?);(\D)/", $value, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
			$Temp_String = ";";
			foreach ($Meldergroups_Temp_2 as $key2 => $value2){
				if (ctype_digit($value2)){
					$Temp_String .= "$value2;";
				}
				else{
					if ($Temp_Counter == 0){
						$Temp_String .= "$value2;";
						$Temp_Counter = 1;
					}else{
						$Temp_String .= "$value2;";
						array_push($Meldergroups, $Temp_String);
						$Temp_String = ";";
						$Temp_Counter = 0;
					}
				}
			}
		}

        foreach ($Meldergroups as $key => $value) {
            $split_line = explode(";", $value);

            $Ring = $split_line[2];
            $Adresse = $split_line[3];
            $Gruppennummer = $split_line[4];
            $Meldernummer = $split_line[5];

            $Meldertyp;
            $Text = "";

            $Number_Elem = sizeof($split_line);
            $Mixed_String = "";
            for ($EC = 6; $EC < $Number_Elem; $EC++) {
                $Mixed_String .= "$split_line[$EC];";
            }

            $pattern_split_extract = '@(.*?)\;(\S*?)(\.)(\S*?)\;(.*)\;(.*)@isx';
            if (preg_match($pattern_split_extract, $Mixed_String, $subpattern)) {
                $Meldertyp = $subpattern[1];
                $Meldertyp = str_replace(";", " ", $Meldertyp);
                $Text = $subpattern[5];
                $Text_Temp = explode("Gruppe", $Text);
                $Text = $Text_Temp[0];
                $Text = str_replace(";", " ", $Text);
            }

            $TTemp1 = preg_split("/Seite/", $Text);
            $TTemp2 = preg_split("/BMZ/", $TTemp1[0]);
            $TTemp3 = preg_split("/Tabelle/", $TTemp2[0]);
            $Text = $TTemp3[0];

            $Seriennummer = $split_line[7];

            if (($Gruppennummer != 0) && ($Meldernummer != 0)) {

                // #Meldertypen klassifizieren
                $Typ = "6000";
                if (strpos($Meldertyp, ' O optischer') !== false) { #Optischer Melder
                $Typ = "6001";
                }
                if (strpos($Meldertyp, ' PBD Handfeuermelder') !== false) { #Handfeuermelder
                $Typ = "6002";
                }
                if (strpos($Meldertyp, ' optisch/thermisch ') !== false) { #Multisensor
                $Typ = "6003";
                }
                if (strpos($Meldertyp, ' optisch/thermischer ') !== false) { #Multisensor
                $Typ = "6003";
                }
                if (strpos($Meldertyp, 'Multimelder') !== false) { #Multisensor
                $Typ = "6003";
                }
                if (strpos($Meldertyp, ' T thermischer') !== false) { #THERMO
                $Typ = "6006";
                }
                if (strpos($Meldertyp, ' Ein-/Ausgangsmodul') !== false) { #Steuer
                $Typ = "6005";
                }
                if (strpos($Meldertyp, 'Sounder') !== false) { #Sirene
                $Typ = "6007";
                }

                if (preg_match($pattern_handfeuermelder, $Meldertyp)) {
                    $Typ = "6002";
                }
                if (preg_match($pattern_optischermelder, $Meldertyp)) {
                    $Typ = "6001";
                }
                if (preg_match($pattern_multimelder, $Meldertyp)) {
                    $Typ = "6003";
                }
                if (preg_match($pattern_thermomelder, $Meldertyp)) {
                    $Typ = "6006";
                }
                if (preg_match($pattern_sounder, $Meldertyp)) {
                    $Typ = "6007";
                }

                #Melderdetails aus Datenbank holen
                $q4 = mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='Detectomat'");
                $mtyp = mysql_fetch_array($q4);

                $art = $mtyp[kurztext];
                $adresse = $t[adresse];
                $serial = $t[serial];

                #Melder in Datenbank eintragen
                $sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Gruppennummer', `melder`='$Meldernummer',
		`text`='$Text', `art`='$Meldertyp', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Ring', `typ`='$Typ', `adresse`='$Adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");
                $Anzahl_Melder++;

                #Backup: Gruppe ohne Text eintragen, falls nicht schon oben geschehen
                if (in_array($Gruppennummer, $Gruppen_Array)) {
                    #Do nothing
                } else {
                    $GroupText = " ";
                    $mg = mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppennummer', `text`='$GroupText', `mandant`='$userinfo[mandant]'");
                    array_push($Gruppen_Array, $Gruppennummer);
                    $Anzahl_Gruppen++;
                }

                #Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
                $qm = mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '" . $Gruppennummer . "' AND `melder` = '" . $Meldernummer . "' AND `mandant` = '$userinfo[mandant]'");
                if (mysql_num_rows($qm) == 0) {
                    $i1 = 0;
                    $i2 = 0;
                    $i3 = 0;
                    $i4 = 0;

                    $mod = ($Meldernummer % 4);
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
                    $sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '" . $Gruppennummer . "',
			`melder` = '" . $Meldernummer . "', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
                    $qm = mysql_query($sql);
                }
            }
        }
    }
    $Anzahl_Combined = $Anzahl_Gruppen . "-" . $Anzahl_Melder . "-" . $Anzahl_Steurung;
    return $Anzahl_Combined;

}

function melderdatei($fp)
{
    global $aid, $userinfo, $filepath;
    $Number_Melders = 0;

    $pattern_handfeuermelder = '@(.*)PL.3300.PBD(.*)@isx';
    $pattern_optischermelder = '@(.*)PL.3300.O(.*)@isx';
    $pattern_multimelder = '@(.*)PL.3300.OTi(.*)@isx';
    $pattern_thermomelder = '@(.*)PL.3300.T(.*)@isx';
    $pattern_sounder = '@(.*)AOM.3301.LS(.*)@isx';

    $line = file_get_contents_utf8($fp);
    $line_mod = str_replace("<", "---", $line);
    $line_mod = str_replace(">", "---", $line_mod);

    $line_split = explode("Worksheet", $line_mod);

    $split_detect_format = explode("---", $line_mod);

    if (strpos($split_detect_format[64], 'Aktiv') !== false) {
        $new_format = "1";
    } else {
        $new_format = "0";
    }

    $single_lines = explode("/Row", $line_split[1]);
    $numb_lines = sizeof($single_lines);

    for ($i = 1; $i < $numb_lines - 1; $i++) {

        $Melder;
        $Gruppe;
        $Adresse;
        $Loop;
        $Meldertyp;
        $Seriennummer;
        $Kundentext;
        $Kommentar;
        $Text;

        $split_temp = explode("---", $single_lines[$i]);
        if ($new_format == "1") {
            $Melder = $split_temp[47];
            $Gruppe = $split_temp[39];
            $Adresse = $split_temp[31];
            $Loop = $split_temp[23];
            $Meldertyp = $split_temp[55];
            $Kundentext = $split_temp[63];
            $Kommentar = $split_temp[71];
            $Text = $Kundentext . " " . $Kommentar;
        } else {
            $Melder = $split_temp[39];
            $Gruppe = $split_temp[31];
            $Adresse = $split_temp[23];
            $Loop = $split_temp[15];
            $Meldertyp = $split_temp[47];
            $Seriennummer = $split_temp[55];
            $Kundentext = $split_temp[63];
            $Kommentar = $split_temp[71];

            $Text = $Kundentext . " - " . $Seriennummer;
        }

        #Meldertypen klassifizieren
        $Typ = "6000";
        if (strpos($Meldertyp, ' O optischer') !== false) { #Optischer Melder
        $Typ = "6001";
        }
        if (strpos($Meldertyp, ' PBD Handfeuermelder') !== false) { #Handfeuermelder
        $Typ = "6002";
        }
        if (strpos($Meldertyp, ' optisch/thermisch ') !== false) { #Multisensor
        $Typ = "6003";
        }
        if (strpos($Meldertyp, ' optisch/thermischer ') !== false) { #Multisensor
        $Typ = "6003";
        }
        if (strpos($Meldertyp, 'Multimelder') !== false) { #Multisensor
        $Typ = "6003";
        }
        if (strpos($Meldertyp, ' T thermischer') !== false) { # THERMO
        $Typ = "6006";
        }

        if (preg_match($pattern_handfeuermelder, $Meldertyp)) {
            $Typ = "6002";
        }
        if (preg_match($pattern_optischermelder, $Meldertyp)) {
            $Typ = "6001";
        }
        if (preg_match($pattern_multimelder, $Meldertyp)) {
            $Typ = "6003";
        }
        if (preg_match($pattern_thermomelder, $Meldertyp)) {
            $Typ = "6006";
        }
        if (preg_match($pattern_sounder, $Meldertyp)) {
            $Typ = "6007";
        }

        #Melderdetails aus Datenbank holen
        $q4 = mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='Detectomat'");
        $mtyp = mysql_fetch_array($q4);

        $art = $mtyp[kurztext];
        $adresse = $t[adresse];
        $serial = $t[serial];

        #Melder in Datenbank eintragen
        $sql = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Gruppe', `melder`='$Melder',
		`text`='$Text', `art`='$Meldertyp', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Loop', `typ`='$Typ', `adresse`='$Adresse', `serial`='$serial', `mandant`='$userinfo[mandant]'");
        $Number_Melders++;

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
            $sql = "INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '" . $Gruppe . "',
			`melder` = '" . $Melder . "', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
            $qm = mysql_query($sql);
        }

    }

    return $Number_Melders;
}

function meldergruppendatei($fp)
{
    global $aid, $userinfo, $filepath;
    $Number_Groups = 0;

    $line = file_get_contents_utf8($fp);
    $line_mod = str_replace("<", "---", $line);
    $line_mod = str_replace(">", "---", $line_mod);

    $line_split = explode("Worksheet", $line_mod);

    $single_lines = explode("/Row", $line_split[1]);
    $numb_lines = sizeof($single_lines);
    for ($i = 1; $i < $numb_lines - 1; $i++) {
        $split_temp = explode("---", $single_lines[$i]);
        $Gruppe = $split_temp[7];
        $Gruppentyp = $split_temp[15];
        $Text = $split_temp[23];
        $Kommentar = $split_temp[31];

        $mg = mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Gruppe', `text`='$Text', `mandant`='$userinfo[mandant]'");
        $Number_Groups++;
    }

    return $Number_Groups;
}

function steuerungsdatei($fp)
{
    global $aid, $userinfo, $filepath;
    $Number_Steuerungen = 0;

    $line = file_get_contents_utf8($fp);
    $line_mod = str_replace("<", "---", $line);
    $line_mod = str_replace(">", "---", $line_mod);

    $line_split = explode("Worksheet", $line_mod);

    $single_lines = explode("/Row", $line_split[1]);
    $numb_lines = sizeof($single_lines);
    for ($i = 1; $i < $numb_lines - 1; $i++) {
        $split_temp = explode("---", $single_lines[$i]);

        $Active = $split_temp[7];
        $Index = $split_temp[15];
        $Quelle_Typ = $split_temp[23];
        $Quelle_Nummer = $split_temp[31];
        $Quelle_Meldung = $split_temp[39];
        $Negiert = $split_temp[47];
        $Ziel_Typ = $split_temp[55];
        $Ziel_Nummer_1 = $split_temp[63];
        $Ziel_Nummer_2 = $split_temp[71];
        $Ziel_Nummer_3 = $split_temp[79];
        $Ziel_Ereignis = $split_temp[87];
        $Verzoegerung = $split_temp[95];
        $Ziel_automatisch_ruecksetzen = $split_temp[103];
        $Kundentext = $split_temp[111];
        $Kommentar = $split_temp[119];
        $Index_int = intval($Index);

        $Ansteuerung = $Ziel_Nummer_1 . " " . $Ziel_Nummer_2 . " " . $Ziel_Nummer_3;
        $Ereignis = $Ziel_Ereignis . " " . $Quelle_Nummer;

        #Steuerungen in Datenbank eintragen
        $sql = "INSERT INTO `technik_steuergruppen` SET `anlage`='$aid', `nr`='$Index_int', `g1`='$Quelle_Typ', `text`='$Quelle_Meldung', `ansteuerung`='$Ansteuerung', `ereignis`='$Ereignis', `mandant`='$userinfo[mandant]'";
        $st = mysql_query($sql);
        //Prüfplan berechnen für den Melder, nur wenn es noch keine manuellen Zeilen dafür gibt
        $qman = mysql_query("SELECT * FROM `technik_steuergruppen_manuell` WHERE `anlage`='$aid' AND `nr`='$Index_int' AND `mandant` = '$userinfo[mandant]'");
        if (mysql_num_rows($qman) == 0) {
            //Steuergruppen immer in jedem Quartal
            $i1 = 1;
            $i2 = 1;
            $i3 = 1;
            $i4 = 1;

            $sql = "INSERT INTO `technik_steuergruppen_manuell` SET `anlage`='$aid', `nr`='$Index_int', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'";
            $qman = mysql_query($sql);
        }

        $Number_Steuerungen++;

    }

    return $Number_Steuerungen;
}

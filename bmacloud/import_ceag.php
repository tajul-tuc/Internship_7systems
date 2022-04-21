<?

$debug = false;

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function file_get_contents_utf8($fn)
{
    $content = file_get_contents($fn);
    return mb_convert_encoding($content, 'UTF-8', mb_detect_encoding($content, 'UTF-8, ISO-8859-1', true));
}

$Number_Melders = 0;
$Anzahl_Gruppen = 0;

//aid wird bentigt, da mehere Files pro Anlage gebraucht werden
function ceag_einlesen($fid, $aid)
{
    global $aid, $userinfo, $programFilesFolder, $Number_Melders, $Anzahl_Gruppen;

    $Anzahl_Melder = 0;
    $Anzahl_Meldergruppen = 0;
    $Anzahl_Steuerungen = 0;

    // Alte Eintragungen aus der Datenbank lschen
    $qd1 = mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
    $qd2 = mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");
    $qd3 = mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = '$aid' AND `useradd` <> '1' AND `mandant` = '$userinfo[mandant]'");

    // File suchen
    $q = mysql_query("SELECT * FROM `files` WHERE `aid`='$aid'  AND `ordner` = '$programFilesFolder' AND `mandant`='$userinfo[mandant]'");
    if ($debug) {
        echo ("SELECT * FROM `files` WHERE `aid`='$aid'  AND `ordner` = '$programFilesFolder' AND `mandant`='$userinfo[mandant]'");
    }

    $FileIDs = array();
    $FileNames = array();
    $FileCounter = 0;

    $Array_Melder = array();

    // Query ausfhren (die Datensatzgruppe $file enthlt das Ergebnis)
    // Schleifendurchlauf durch $file
    // Jede Zeile wird zu einem Array ($file), mit mysql_fetch_array

    #File einlesen
    while ($file = mysql_fetch_array($q)) {
        $FileIDs[$FileCounter] = $file[fid];
        $FileNames[$FileCounter] = $file[name];
        $FileCounter++;
        $c++;
    }

    // Schreibe den Wert der Melder (der jetzt im Array $file ist)
    // File suchen fr Melder, Meldergruppen und Steuergruppen

    for ($ic = 0; $ic < $FileCounter; $ic++) {
        $fp = get_fid_path($FileIDs[$ic]);
        melder($fp);
    }
    //Datei ffnen
    //Datenverbindung besteht

    msg($Number_Melders . " Melder Importiert");
    msg($Anzahl_Gruppen . " Meldergruppen Importiert");
}

//-------------------------Melder Import-------------------------

function melder($fp)
{
    global $aid, $userinfo, $Number_Melders, $Anzahl_Gruppen;
    $line = file_get_contents_utf8($fp);
    $line = preg_replace("/[^0-9a-zA-Z \/>]/", "", $line); //replace all simbols to Nothing except 0-9 and a-Z
    $line = str_ireplace("/td>", "", $line);
    $line = str_ireplace("/th>", "", $line);
    $line = str_ireplace("/tr>", "", $line);
    $line = str_ireplace("tr>", "tr>", $line);
    $single_lines = explode("tr>", $line); //split by table rows
    $number_lines = sizeof($single_lines);
    $GroupIDs = array();
    for ($i = 1; $i < $number_lines; $i++) {
        if (stripos($single_lines[$i], "th>")) {
            continue;
        }
        $single_lines[$i] = str_ireplace("td>", "td>", $single_lines[$i]);
        $Zeile_lines = explode("td>", $single_lines[$i]); //split to columns
        $Anzahl_lines = sizeof($Zeile_lines);

        /**<tbody><tr>-0
        <th>Panel Addr</th>1
        <th>Panel Name</th>2
        <th>Loop Number</th>3
        <th>Device Addr</th>4
        <th>Device Type</th>5
        <th>Device Text</th>6
        <th>Zone Address</th>7
        <th>Zone Number</th>8
        <th>Zone Name</th>9
        </tr>
        <tr>
        <td>1</td>1
        <td>Familienzentrum</td>2
        <td>1</td>3
        <td>1</td>4
        <td>Ein-/Ausgangsmodul</td>5
        <td>Lüftung1</td>6
        <td>1</td>7
        <td>21</td>8
        <td>Koppler EG Lüftungsabsch.</td>9
        </tr>*/

        $Loop_Number = trim($Zeile_lines[3]); //num
        $Device_addr = trim($Zeile_lines[4]); //num
        $Device_Type = trim($Zeile_lines[5]); //str
        $Device_Text = trim($Zeile_lines[6]); //str

        $Zone_Address = (int)trim($Zeile_lines[7]); //num melder
		$Zone_Number = (int)trim($Zeile_lines[8]); //num gruppe
		if($Zone_Address==0&&Zone_Number==0){
			echo "Error in group/detector number (".$Zeile_lines[8]."/".$Zeile_lines[7]."). ";
			continue;
		}
        $Zone_Name = trim($Zeile_lines[9]); //str
        if ($Zone_Address != 0) {
            $Typ = "CEAG0";
            #Meldertypen klassifizieren
            if (stripos($Device_Type, 'Photo') !== false) { #Optischer Melder
            $Typ = "CEAG1";
            }
            if (stripos($Device_Type, 'OptoHeat') !== false) { #Wrmemelder
            $Typ = "CEAG2";
            }
            if (stripos($Device_Type, 'Rauchmelder') !== false) { #Wrmemelder
            $Typ = "CEAG2";
            }
            if (stripos($Device_Type, 'Multisensor') !== false) { #Wrmemelder
            $Typ = "CEAG2";
            }
            if (stripos($Device_Type, 'Sounder') !== false) { #Sirene
            $Typ = "CEAG3";
            }
            if (stripos($Device_Type, 'Signalgeber') !== false) { #Sirene
            $Typ = "CEAG3";
            }
            if (stripos($Device_Type, 'Call Point') !== false) { #Handfeuermelder
            $Typ = "CEAG4";
            }
            if (stripos($Device_Type, 'Druckknopfmelder') !== false) { #Handfeuermelder
            $Typ = "CEAG4";
            }
            if (stripos($Device_Type, 'IOUnit') !== false) { #Steuermodul
            $Typ = "CEAG5";
            }
            if (stripos($Device_Type, 'MCIM') !== false) { #Steuermodul
            $Typ = "CEAG5";
            }
            if (stripos($Device_Type, 'EinAusgangsmodul') !== false) { #Steuermodul
            $Typ = "CEAG5";
            }

            $hersteller = "CEAG";
            #Melderdetails aus Datenbank holen
            $q4 = mysql_query("SELECT * FROM `technik_meldertypen` WHERE `typ`='$Typ' AND `hersteller`='$hersteller'");
            $mtyp = mysql_fetch_array($q4);

            $art = $mtyp[kurztext];
            $adresse = $mtyp[adresse];
            $serial = $mtyp[serial];

            #Melder in Datenbank eintragen
            $ga = mysql_query("INSERT INTO `technik_melder` SET `anlage`='$aid', `gruppe`='$Zone_Number', `melder`='$Zone_Address',
			`text`='$Device_Text', `art`='$Device_Type', `auto`='$mtyp[auto]', `manuell`='$mtyp[manuell]', `steuer`='$mtyp[steuer]', `ring`='$Loop_Number', `typ`='$Typ', `adresse`='$Device_addr', `serial`='$serial' , `mandant`='$userinfo[mandant]'");

            $Number_Melders++;

            #Prfplan berechnen fr den Melder, nur wenn es noch keine manuellen Zeilen dafr gibt
            $qm = mysql_query("SELECT * FROM `technik_melder_manuell` WHERE `anlage`='$aid' AND `gruppe` = '" . $Zone_Number . "' AND
			`melder` = '" . $Zone_Address . "' AND `mandant` = '$userinfo[mandant]'");

            if (mysql_num_rows($qm) == 0) {
                $i1 = 0;
                $i2 = 0;
                $i3 = 0;
                $i4 = 0;

                $mod = ($Zone_Address % 4);
                if ($mod == 1) {$i1 = '1';}
                if ($mod == 2) {$i2 = '1';}
                if ($mod == 3) {$i3 = '1';}
                if ($mod == 0) {$i4 = '1';}

                #Wenn der Meldertyp einen vorgegebenen Prfplan hat, dann diesen verwenden:
                if (($mtyp[i1] == 1) || ($mtyp[i2] == 1) || ($mtyp[i3] == 1) || ($mtyp[i4] == 1)) {
                    $i1 = $mtyp[i1];
                    $i2 = $mtyp[i2];
                    $i3 = $mtyp[i3];
                    $i4 = $mtyp[i4];
                }

                #Prfplan eintragen in Datenbank
                $ga = mysql_query("INSERT INTO `technik_melder_manuell` SET `anlage`='$aid', `gruppe` = '" . $Zone_Number . "',`text`='$Device_Text',`melder` = '" . $Zone_Address . "', `i1`='$i1', `i2`='$i2', `i3`='$i3', `i4`='$i4', `mandant`='$userinfo[mandant]'");
                $qm = mysql_query($ga);
            }

            if ($Zone_Number != 0) {
                if (in_array($Zone_Number, $GroupIDs)) {
                    #Do nothing, group already in pp
                } else {
                    $ga = mysql_query("INSERT INTO `technik_gruppe` SET `anlage`='$aid', `gruppe`='$Zone_Number', `text`='$Zone_Name', `mandant`='$userinfo[mandant]'");
                    $Anzahl_Gruppen++;
                    array_push($GroupIDs, $Zone_Number);
                }
            }
        }
    }
    return $Anzahl_Melder;
}

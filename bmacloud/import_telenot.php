<?
require_once('models/technik_gruppe.inc.php');
require_once('import_utils.inc.php');

$debug=false;

mysql_query("SET NAMES 'utf8'");
mysql_query("SET CHARACTER SET 'utf8'");

function read_telenot_sicherungsbereiche(&$lines, &$atLine, $aid){
	// We jump to chapter 2.2.1 and read the Meldebereiche raw data into a temp table
	$atLine = search_line("/^ 2\\.2\\.1 .*/", $lines, $atLine);
	$atLine = search_line("/^-+/", $lines, $atLine) - 1;
	$t = TextTable::parse_fixed_width_table($lines, $atLine);
	$t->createToDb("tmp_telenot_sicherungsbereich_$aid");
	$t->insertToDb("tmp_telenot_sicherungsbereich_$aid");
}

function read_telenot_meldebereiche(&$lines, &$atLine, $aid){
	// We jump to chapter 2.2.2 and read the Meldebereiche raw data into a temp table
	$atLine = search_line("/^ 2\\.2\\.2 .*/", $lines, $atLine);
	$atLine = search_line("/^-+/", $lines, $atLine) - 1;
	$t = TextTable::parse_fixed_width_table($lines, $atLine);
	$t->createToDb("tmp_telenot_meldebereich_$aid");
	$t->insertToDb("tmp_telenot_meldebereich_$aid");
}

function read_telenot_analog_melders(&$lines, &$atLine, $aid){
	// We will go through chapters 2.3.3, 2.3.3.1, 2.5.3, 2.5.3.1, 2.6.3, 2.6.3.1, 2.7.3, 2.7.3.1, 2.8.3, 2.8.3.1 and read the Melder raw data into a temp table
	// We will also skip chapter 2.4.3
	$all1 = null;
	$all2 = null;
	while(($found = search_line("/^ 2\\.[3,5-9]\\.3  Parametrierung .*? Eingänge/", $lines, $atLine, $matches)) != -1) {
		$atLine = search_line("/^-+/", $lines, $found) - 1;
		$t = TextTable::parse_fixed_width_table($lines, $atLine, ["Eing_" => "Inputs", "Eingang" => "Inputs"]);

		if($all1 === null){
			$all1 = $t;
		}else{
			$all1->extend($t);
		}

		// now we jump to section 2.x.3.1, which always follows a 2.x.3 Eingang chapter
		$atLine = search_line("/^-+/", $lines, $atLine) - 1;
		$t = TextTable::parse_fixed_width_table($lines, $atLine, ["Eing_" => "Inputs", "Eingang" => "Inputs"]);

		if($all2 === null){
			$all2 = $t;
		}else{
			$all2->extend($t);
		}
	}
	$all1->createToDb("tmp_telenot_analog_melder_$aid");
	$all1->insertToDb("tmp_telenot_analog_melder_$aid");
	$all2->createToDb("tmp_telenot_analog_mg_$aid");
	$all2->insertToDb("tmp_telenot_analog_mg_$aid");
}

function read_telenot_steuergruppen(&$lines, &$atLine, $aid){
	// We jump to the Steuergruppen from 2.3.4 and read them into a temp table
	$atLine = search_line("/^ 2\\.3\\.4 .*/", $lines, $atLine);
	$atLine = search_line("/^-+/", $lines, $atLine) - 1;
	$t = TextTable::parse_fixed_width_table($lines, $atLine, [], ["Ausgang" => ""]);
	$t->createToDb("tmp_telenot_steuergruppen_$aid");
	$t->insertToDb("tmp_telenot_steuergruppen_$aid");
}

function read_telenot_digital_melders(&$lines, &$atLine, $aid){
	// For each Melderbus, there are chapters 2.3.x.1 with digital melders and 2.3.x.2 with sabo melders, we want to read into a temp table
	$all = null;
	while(($found = search_line("/^ 2\\.3\\.\\d+\\.\\d+ .*? Melderbus (\\d+) .*/", $lines, $atLine, $matches)) != -1){
		$atLine = search_line("/^-+/", $lines, $found) - 1;
		$t = TextTable::parse_fixed_width_table($lines, $atLine, ["im_Meldebereich" => "Meldebereich"]);
		$t->addColumn("Melderbus", 4);
		$t->update(function() use ($matches){
			return ["Melderbus" => $matches[1]];
		});

		if($all === null){
			$all = $t;
		}else{
			$all->extend($t);
		}
	}

	$all->createToDb("tmp_telenot_digital_melder_$aid");
	$all->insertToDb("tmp_telenot_digital_melder_$aid");
}

function read_telenot_meldebereiche_sabotage(&$lines, &$atLine, $aid){
	// We jump to chapter 2.9.1.4 and read the Extra Sabotage Meldebereiche raw data into a temp table
	//We also need to look at section 2.7.1.4 also from others file
	$atLine = search_line("/^ 2\\.[9]\\.1\\.4 .*/", $lines, $atLine);
	$atLine = search_line("/^-+/", $lines, $atLine) - 1;
	$t = TextTable::parse_fixed_width_table($lines, $atLine,["Text__Standort_" => "Text_Montageort", "Typ" =>"Inputs"]);
	$t->createToDb("tmp_telenot_meldebereich_sabotage_$aid");
	$t->insertToDb("tmp_telenot_meldebereich_sabotage_$aid");
}

function import_telenot_meldergruppen($aid, $mandant){
	// insert general group
	mysql_query("INSERT INTO technik_gruppe " .
	            "SET `anlage` = '" . mysql_real_escape_string($aid) . "', " . 
				"`mandant` = '" . mysql_real_escape_string($mandant) . "', " .
				"`gruppe` = '" . mysql_real_escape_string(TELENOT_GENERAL_GROUP_NUM) . "', " .
				"`text` = 'kein MB', " .
				"`useradd` = 0");

	// insert groups from Meldebereiche
	mysql_query("INSERT INTO `technik_gruppe` (`anlage`, `mandant`, `gruppe`, `text`, `useradd`) " .
	            "SELECT '" . mysql_real_escape_string($aid) . "', " .
				"       '" . mysql_real_escape_string($mandant) . "', " .
				"       `bereich`, " .
				"       `MB_Text__Name_`, " .
				"       0 " .
				"FROM `tmp_telenot_meldebereich" . toIdentifier($aid) . "` " .
			    "WHERE `Vorh_` = 'Ja'");
	$cnt = mysql_affected_rows() + 1;

	// add multiples of 1000 of the Sicherungsbereich number to each Gruppe number
	$sbs = mysql_query("SELECT SUBSTR(`bereich`, 9) AS `bnum` FROM `tmp_telenot_sicherungsbereich" . toIdentifier($aid) . "` WHERE `Vorh_` = 'Ja'");
	while($row = mysql_fetch_assoc($sbs)){
		$bnum = $row["bnum"];

		// analog meldebereiche
		$update = "UPDATE `technik_gruppe` g " .
		          "INNER JOIN `tmp_telenot_analog_melder" . toIdentifier($aid) . "` m ON m.`Meldebereich` LIKE CONCAT(g.`gruppe`, ' %') " .
				  "INNER JOIN `tmp_telenot_analog_mg" . toIdentifier($aid) . "` mg ON mg.`Inputs` = m.`Inputs` " .
		          "SET g.`gruppe` = 1000 * '" . mysql_real_escape_string($bnum) . "' + g.`gruppe` " .
				  "WHERE mg.`_" . mysql_real_escape_string($bnum) . "` = '$bnum' " .
				  "AND `anlage` = " . mysql_real_escape_string($aid) ." AND `useradd` <> '1' AND `mandant` = " . mysql_real_escape_string($mandant);
		mysql_query($update);

		// digital meldebereiche
		$update = "UPDATE `technik_gruppe` g " .
		          "INNER JOIN `tmp_telenot_digital_melder" . toIdentifier($aid) . "` m ON m.`Meldebereich` LIKE CONCAT(g.`gruppe`, ' %') " .
				  "SET g.`gruppe` = 1000 * '" . mysql_real_escape_string($bnum) . "' + g.`gruppe` " . 
				  "WHERE m.`_" . mysql_real_escape_string($bnum) . "` = '$bnum' " .
				  "AND `anlage` = " . mysql_real_escape_string($aid) . " AND `useradd` <> '1' AND `mandant` = " . mysql_real_escape_string($mandant);
		mysql_query($update);
	}

	return $cnt;
}

function import_telenot_melders($aid, $mandant){
	// insert analog melders
	$q = "INSERT INTO `technik_melder` " .
	     "(`anlage`, `mandant`, `gruppe`, `meldebereich`, `text`, `ring`, `art`, `adresse`, `useradd`)" .
		 "SELECT DISTINCT '" . mysql_real_escape_string($aid) . "', " .
		 "       '" . mysql_real_escape_string($mandant) . "', " .
		 "       IF(m.`Meldebereich` IN ('', 'kein MB'), " . TELENOT_GENERAL_GROUP_NUM . ", SUBSTR(m.`Meldebereich`, 1, LOCATE(' ', m.`Meldebereich`) - 1)), " .
		 "       IF(m.`Meldebereich` IN ('', 'kein MB'), " . TELENOT_GENERAL_GROUP_NUM . ", SUBSTR(m.`Meldebereich`, 1, LOCATE(' ', m.`Meldebereich`) - 1)), " .
		 "       m.`Text_Montageort`, " .
		 "       0, " .
		 "       'analoger Melder', " .
		 "       IF(m.`Text_Montageort` IN ('Akku-Stoerung', 'UE-Stoerung'), m.`Text_Montageort`, m.`Inputs`), " .
         "       0 " .
		 "FROM `tmp_telenot_analog_melder" . toIdentifier($aid) . "` m " .
		 "WHERE m.`Aktiv` = 'Ja' OR m.`Text_Montageort` = 'UE-Stoerung'";
    mysql_query($q);
	$cnt_analog = mysql_affected_rows();

    // insert digital melders
    $q = "INSERT INTO `technik_melder` ".
         "(`anlage`, `mandant`, `gruppe`, `meldebereich`, `text`, `ring`, `art`, `adresse`, `useradd`) " .
         "SELECT DISTINCT '" . mysql_real_escape_string($aid) . "', " .
		 "       '" . mysql_real_escape_string($mandant) . "', " .
         "       IF(`Meldebereich` IN ('', 'kein MB'), " . TELENOT_GENERAL_GROUP_NUM . ", SUBSTR(`Meldebereich`, 1, LOCATE(' ', `Meldebereich`) - 1)), " .
         "       IF(`Meldebereich` IN ('', 'kein MB'), " . TELENOT_GENERAL_GROUP_NUM . ", SUBSTR(`Meldebereich`, 1, LOCATE(' ', `Meldebereich`) - 1)), " .
         "       `Text___Montageort`, " .
         "       `Melderbus`, " .
         "       `Modultyp`, " .
         "       `teiln_`, " .
         "       0 " .
         "FROM `tmp_telenot_digital_melder" . toIdentifier($aid) . "` " .
         "WHERE `Aktiv` = 'Ja' OR `Alarmierungstyp` <> ''" ;
    mysql_query($q);
    $cnt_digital = mysql_affected_rows();

	// add multiples of 1000 of the Sicherungsbereich number to each Gruppe number
	$sbs = mysql_query("SELECT SUBSTR(`bereich`, 9) AS `bnum` FROM `tmp_telenot_sicherungsbereich" . toIdentifier($aid) . "` WHERE `Vorh_` = 'Ja'");
	while($row = mysql_fetch_assoc($sbs)){
		$bnum = $row["bnum"];

		// analog melders
		$q = "UPDATE `technik_melder` m " .
		     "INNER JOIN `tmp_telenot_analog_melder" . toIdentifier($aid) . "` AS m2 ON m2.`meldebereich` LIKE CONCAT(m.`gruppe`, ' %') " .
		     "INNER JOIN `tmp_telenot_analog_mg" . toIdentifier($aid) . "` AS mg ON mg.`Inputs` = m2.`Inputs` " .
			 "SET m.`gruppe` = 1000 * '" . mysql_real_escape_string($bnum) . "' + m.`gruppe` " .
			 "WHERE mg.`_" . mysql_real_escape_string($bnum) . "` = '$bnum' " .
			 "AND `anlage` = '" . mysql_real_escape_string($aid) . "' AND `useradd` <> '1' AND `mandant` = " . mysql_real_escape_string($mandant);
		mysql_query($q);

        // digital melders
        $q = "UPDATE `technik_melder` m " .
             "INNER JOIN `tmp_telenot_digital_melder" . toIdentifier($aid) . "` m2 ON m2.`Meldebereich` LIKE CONCAT(m.gruppe, ' %') " .
             "SET m.`gruppe` = 1000 * '" . mysql_real_escape_string($bnum) . "' + m.`gruppe` " .
             "WHERE m2.`_" . mysql_real_escape_string($bnum) . "` = '$bnum' " .
             "AND m.`anlage` = '" . mysql_real_escape_string($aid) . "' AND `useradd` <> '1' AND `mandant` = " . mysql_real_escape_string($mandant);
        mysql_query($q);
	}

	return $cnt_analog + $cnt_digital;
}

function import_telenot_steuergruppen($aid, $mandant){
    $q = "INSERT INTO `technik_steuergruppen` " .
         "(`anlage`, `mandant`, `text`, `ansteuerung`, `ereignis`, `useradd`) " .
         "SELECT " .
         "    '" . mysql_real_escape_string($aid) . "', " .
         "    '" . mysql_real_escape_string($mandant) . "', " .
         "    `Name`, " .
         "    CONCAT(`Funktion`, IF(`Nr` = '-', '', CONCAT(' ', `Nr`))), " .
         "    `_auf_`, " .
         "    '0' " .
         "FROM `tmp_telenot_steuergruppen" . toIdentifier($aid) . "` " .
         "WHERE `Funktion` <> '------'";
    mysql_query($q);
    $cnt = mysql_affected_rows();

    return $cnt;
}

function clear_telenot_temp_tables($aid){
    mysql_query("DROP TABLE IF EXISTS `tmp_telenot_analog_melder" . toIdentifier($aid) . "`");
    mysql_query("DROP TABLE IF EXISTS `tmp_telenot_analog_mg" . toIdentifier($aid) . "`");
	mysql_query("DROP TABLE IF EXISTS `tmp_telenot_analog_mgs" . toIdentifier($aid) . "`");
	mysql_query("DROP TABLE IF EXISTS `tmp_telenot_meldebereich_sabotage" . toIdentifier($aid) . "`");
    mysql_query("DROP TABLE IF EXISTS `tmp_telenot_digital_melder" . toIdentifier($aid) . "`");
    mysql_query("DROP TABLE IF EXISTS `tmp_telenot_meldebereich" . toIdentifier($aid) . "`");
    mysql_query("DROP TABLE IF EXISTS `tmp_telenot_sicherungsbereich" . toIdentifier($aid) . "`");
    mysql_query("DROP TABLE IF EXISTS `tmp_telenot_steuergruppen" . toIdentifier($aid) . "`");
}

function telenot_complex_einlesen($fid, $aid){
	global $userinfo;

	$lines = read_utf8_lines(get_fid_path($fid));
	$atLine = 0;

	delete_programmed_entries($aid, $userinfo["mandant"]);

	// read relevant sections into temp SQL tables
	read_telenot_sicherungsbereiche($lines, $atLine, $aid);
	read_telenot_meldebereiche($lines, $atLine, $aid);
	read_telenot_analog_melders($lines, $atLine, $aid);
	$atLine = 0; // We are at the ende, so we jump back to the beginning
	read_telenot_steuergruppen($lines, $atLine, $aid);
	read_telenot_digital_melders($lines, $atLine, $aid);
	read_telenot_meldebereiche_sabotage($lines, $atLine, $aid);
	// insert from temp tables
	$groups_imported = import_telenot_meldergruppen($aid, $userinfo["mandant"]);
	$melders_imported = import_telenot_melders($aid, $userinfo["mandant"]);
    enumerate_melders($aid, $userinfo["mandant"]);
    calc_melder_pruefplan($aid, $userinfo["mandant"]);
    $steuergruppen_imported = import_telenot_steuergruppen($aid, $userinfo["mandant"]);
    enumerate_steuergruppen($aid, $userinfo["mandant"]);
    add_empty_ansteuer($aid, $userinfo["mandant"]);
    
	msg($groups_imported . " Meldergruppen Importiert");
	msg($melders_imported . " Melder Importiert");
    msg($steuergruppen_imported . " Steuergruppen Importiert");

    clear_telenot_temp_tables($aid);
}

?>

<?php

function read_utf8_lines($fp) {
    $cont = file_get_contents($fp);
    $cont_utf8 = mb_convert_encoding(
        $cont, 'UTF-8', mb_detect_encoding($cont, 'UTF-8, ISO-8859-1', true));
    return preg_split('/\n|\r\n?/', $cont_utf8);
}

function search_line($pattern, &$lines, $line_offset = 0, &$matches = null){
    set_time_limit(0); //Because we want to continue our execution of code after 30 sec
    for($i = $line_offset; $i < count($lines); $i++){
        if(preg_match($pattern, $lines[$i], $matches)){
            return $i;
        }
    }
    return -1;
}

function delete_programmed_entries($aid, $mandant) {
    // use mysql_real_escape_string to avoid SQL injections
    mysql_query("DELETE FROM `technik_gruppe` WHERE `anlage` = ". mysql_real_escape_string($aid) ." AND `useradd` <> '1' AND `mandant` = ". mysql_real_escape_string($mandant));
    mysql_query("DELETE FROM `technik_melder` WHERE `anlage` = ". mysql_real_escape_string($aid) ." AND `useradd` <> '1' AND `mandant` = ". mysql_real_escape_string($mandant));
    mysql_query("DELETE FROM `technik_steuergruppen` WHERE `anlage` = ". mysql_real_escape_string($aid) ." AND `useradd` <> '1' AND `mandant` = ". mysql_real_escape_string($mandant));
    mysql_query("DELETE FROM `technik_ansteuer` WHERE `anlage` = ". mysql_real_escape_string($aid) ." AND `mandant` = ". mysql_real_escape_string($mandant));
}

function toIdentifier($s){
    $s = preg_replace("/\\W/", "_", $s); // replace special characters with underscore
    $s = preg_replace("/^(\\d)/", "_$1", $s); // prepend underscore, if identifier begins with digit
    return $s;
}

class TextTable{

    private $rows;
    private $columns;

    static function parse_fixed_width_table(&$lines, &$atLine, $renameCols = [], $omitRepeatCols = []){
        // First, we need to detect the column names and the column lengths
        $headline = $lines[$atLine];
        $offset = 0;
        $colIdentifiers = [];
        $colLens =  [];

        while($offset < mb_strlen($headline, "UTF-8")){
            if(preg_match("/(.*?)  +/", $headline, $matches, 0, $offset)){
                $col = $matches[1];
                $len = mb_strlen($matches[0], "UTF-8");
            }else{
                // last row without trailing space
                $col = mb_substr($matches[1], $offset, null, "UTF-8");
                $len = mb_strlen($col, "UTF-8");
                $col = rtrim($col); // trim single trailing space
            }

            $col = toIdentifier($col);
            if(array_key_exists($col, $renameCols)){
                $col = $renameCols[$col];
            }

            $colIdentifiers[] = $col;
            $colLens[] = $len;
            $offset += $len;
        }

        // now we parse each row
        $table = new TextTable($colIdentifiers, $colLens);
        for($atLine += 2 /* skip line of ---------- */; !empty($lines[$atLine]); $atLine++){
            $row = [];
            $offset = 0;
            for($i = 0; $i < count($colIdentifiers); $i++){
                $val = rtrim(mb_substr($lines[$atLine], $offset, $colLens[$i], "UTF-8"));
                if(array_key_exists($colIdentifiers[$i], $omitRepeatCols)){
                    if(empty($val)){
                        $val = $omitRepeatCols[$colIdentifiers[$i]];
                    }else{
                        $omitRepeatCols[$colIdentifiers[$i]] = $val;
                    }
                }
                $row[$colIdentifiers[$i]] = $val;
                $offset += $colLens[$i];
            }
            $table->appendRow($row);
        }

        return $table;
    }

    public function __construct($columns, $lens){
        $this->columns = [];
        for($i = 0; $i < count($columns); $i++){
            $c = $columns[$i];
            $l = $lens[$i];
            $this->columns[$c] = $l;
        }
    }

    public function appendRow($row){
        $this->rows[] = $row;
    }

    public function getRow($i){
        return $rows[i];
    }

    public function numRows(){
        return count($this->rows);
    }

    public function addColumn($colname, $maxLength){
        $this->columns[$colname] = $maxLength;
    }

    public function update($fn){
        for($i = 0; $i < $this->numRows(); $i++){
            $res = $fn($this, $i);
            foreach($res as $col => $val){
                $this->rows[$i][$col] = $val;
            }
        }
    }

    public function extend($table){
        // We want to add new columns and enlarge existing columns, if necessary.
        foreach($table->columns as $c => $l){
            if(array_key_exists($c, $this->columns)){
                $this->columns[$c] = max($l, $table->columns[$c]);
            }else{
                $this->columns[$c] = $table->columns[$c];
            }
        }

        // We append all rows of the other table.
        array_push($this->rows, ...$table->rows);
    }

    public function createToDb($tableName, $engine = "MEMORY"){
        // drop table
        mysql_query("DROP TABLE IF EXISTS `" . mysql_real_escape_string($tableName) . "`");
        
        // create table
        $createCmd = "CREATE TABLE `" . mysql_real_escape_string($tableName) . "`(";
        foreach($this->columns as $col => $len){
            $createCmd .= "`" . mysql_real_escape_string($col) . "` VARCHAR(" . mysql_real_escape_string($len) . "),";
        }
        $createCmd = substr($createCmd, 0, -1) . ")";
        if(!empty($engine)){
            $createCmd .= " ENGINE = " . $engine;
        }
        mysql_query($createCmd);
    }

    public function insertToDb($tableName){
        // insert rows
        foreach($this->rows as $row){
            $insertCmd = "INSERT INTO `" . mysql_real_escape_string($tableName) . "` SET ";
            foreach($this->columns as $col => $_){
                if(array_key_exists($col, $row)){
                    $insertCmd .= "`" . mysql_real_escape_string($col) . "` = '" . mysql_real_escape_string($row[$col]) . "',";
                }else{
                    $insertCmd .= "`" . mysql_real_escape_string($col) . "` = '',";
                }
            }
            $insertCmd = substr($insertCmd, 0, -1);
            mysql_query($insertCmd);
        }
    }

}

function enumerate_melders($aid, $mandant){
    // iterate over the gruppen of the imported melders
    $q = "SELECT DISTINCT `gruppe` FROM `technik_melder` " . 
         "WHERE `anlage` = '" . mysql_real_escape_string($aid) . "' AND `useradd` <> '1' AND `mandant` = '" . mysql_real_escape_string($mandant) . "'";
    $gruppen = mysql_query($q);
    while($g = mysql_fetch_assoc($gruppen)){
        $g = $g["gruppe"];

        // we want to iterate over the imported melders of the actual gruppe to assign a number
        $q = "SELECT `id` FROM `technik_melder` " .
             "WHERE `gruppe` = '" . mysql_real_escape_string($g) . "' " .
             "AND `anlage` = '" . mysql_real_escape_string($aid) . "' AND `useradd` <> '1' AND `mandant` = '" . mysql_real_escape_string($mandant) . "'";
        $melders = mysql_query($q);
        $m = mysql_fetch_assoc($melders);
        $act_num = 1;

        // we want to skip the numbers of the manual melders in the actual gruppe
        $q = "SELECT `melder` FROM `technik_melder` " .
             "WHERE `gruppe` = '" . $g  . "' " .
             "AND `anlage` = '" . mysql_real_escape_string($aid) . "' AND `useradd` = '1' AND `mandant` = '" . mysql_real_escape_string($mandant) . "' " .
             "ORDER BY `melder`";
        $manual_nums = mysql_query($q);
        
        // read next number of manual melder
        if($mnum = mysql_fetch_assoc($manual_nums)){
            $skip_num = $mnum['melder'];
        }else{
            $skip_num = 0;
        }

        while($m){
            // check, if the actual number must be skipped
            if($act_num == $skip_num){
                // read next number of manual melder
                if($mnum = mysql_fetch_assoc($manual_nums)){
                    $skip_num = $mnum['melder'];
                }else{
                    $skip_num = 0;
                }
            }else{
                // assign number to imported melder and go to next melder
                $m = $m["id"];
                mysql_query("UPDATE `technik_melder` SET `melder` = '$act_num' WHERE `id` = $m");
                $m = mysql_fetch_assoc($melders);
            }

            $act_num++;
        }
    }
}

function enumerate_steuergruppen($aid, $mandant){
    // select ids of imported steuergruppen -> we want to enumerate these steuergruppen
    $q = "SELECT `sid` FROM `technik_steuergruppen` " .
         "WHERE `anlage` = '" . mysql_real_escape_string($aid) . "' AND `useradd` <> '1' AND `mandant` = '" . mysql_real_escape_string($mandant) . "'";
    $steuer_ids = mysql_query($q);

    // select steuergruppen numbers of manually added steuergruppen -> we want to skip these numbers in our enumeration
    $q = "SELECT `nr` FROM `technik_steuergruppen` " .
         "WHERE `anlage` = '" . mysql_real_escape_string($aid) . "' AND `useradd` = '1' AND `mandant` = '" . mysql_real_escape_string($mandant) . "' " .
         "ORDER BY `nr`";
    $manual_nums = mysql_query($q);
    
    // read next number of manual steuergruppe
    if($mnum = mysql_fetch_assoc($manual_nums)){
        $skip_num = $mnum['nr'];
    }else{
        $skip_num = 0;
    }
    
    $act_num = 1;
    $sid = mysql_fetch_assoc($steuer_ids);
    while($sid){
        if($act_num == $skip_num){
            // read next number of manual steuergruppe
            if($mnum = mysql_fetch_assoc($manual_nums)){
                $skip_num = $mnum['nr'];
            }else{
                $skip_num = 0;
            }
        }else{
            $sid = $sid["sid"];
            mysql_query("UPDATE `technik_steuergruppen` SET `nr` = $act_num WHERE `sid` = $sid");
            $sid = mysql_fetch_assoc($steuer_ids);
        }
        $act_num++;
    }
}

function add_empty_ansteuer($aid, $mandant){
    // we add empty ansteuerungen to each steuergruppe
    $q = "INSERT INTO `technik_ansteuer` " .
         "(`anlage`, `mandant`, `sgid`, `art`) " .
         "SELECT " .
         "    '" . mysql_real_escape_string($aid) . "', " .
         "    '" . mysql_real_escape_string($mandant) . "', " .
         "    `sid`, " .
         "    `nr` " .
         "FROM `technik_steuergruppen` " .
         "WHERE `anlage` = '" . mysql_real_escape_string($aid) . "' AND `mandant` = " . mysql_real_escape_string($mandant);
    mysql_query($q);
}

function calc_melder_pruefplan($aid, $mandant){
    // TODO: for melder types with predefined plan
    
    // for melders with no manual plans
    $q = "INSERT INTO `technik_melder_manuell` " .
         "(`anlage`, `mandant`, `gruppe`, `melder`, `i1`, `i2`, `i3`, `i4`) " .
         "SELECT " .
         "    '" . mysql_real_escape_string($aid) . "', " .
         "    '" . mysql_real_escape_string($mandant) . "', " .
         "    m.`gruppe`, " .
         "    m.`melder`, " .
         "    IF(m.`melder` % 4 = 1, '1', '0'), " .
         "    IF(m.`melder` % 4 = 2, '1', '0'), " .
         "    IF(m.`melder` % 4 = 3, '1', '0'), " .
         "    IF(m.`melder` % 4 = 0, '1', '0') " .
         "FROM `technik_melder` m " .
         "WHERE m.`anlage` = '" . mysql_real_escape_string($aid) . "' AND m.`useradd` <> '1' AND m.`mandant` = '" . mysql_real_escape_string($mandant) . "' " .
         "    AND NOT EXISTS (SELECT 'x' FROM `technik_melder_manuell` i " .
         "                    WHERE i.`anlage` = m.`anlage` AND i.`mandant` = m.`mandant` AND i.`gruppe` = m.`gruppe` AND i.`melder` = m.`melder`)";
    mysql_query($q);
}

?>
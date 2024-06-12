<?php
/*
 * The name "processCSVs" is a little misleading because it implies that something is actually
 * done to them. But they are only read, not ever changed. Hence "parseCSVs" would have been a
 * better name, but what the heck!
 *
 * This script, run by a cron job reads the CSV files uploaded by the gateway via ftp line by line.
 * The m-bus protocol lets each meter start with a line of attribute names followed
 * by the actual values in the next line. 
 *
 * Values are written to a db table with a combined unique index of timestamp and value. So this
 * script can be run repeatedly without harm.
 *
 * After parsing, the csv files are moved to a seperate directory named after the current year.
 *
 * Sensus meters don't send a timestamp, so file creation time is used instead. <= no more Sensus
 * meters so that code could go, not now though
 *
 * Work is done in the following order:
 * 1 read
 * 2 parse
 * 3 move
 *
 * todo:
 * deal with error codes
*/

# 1. look for uploaded csv and log files
$files = scandir( dirname(__DIR__) );

function isCSV( $filename ){
    if( substr( $filename, -4 ) === '.csv' ){
        return true;
    }
}
function isLOG( $filename ){
    if( substr( $filename, -4 ) ==='.log' ){
        return true;
    }
}
$CSVs = array_filter( $files, 'isCSV' );
$LOGs = array_filter( $files, 'isLOG' );

function chunkToDatetime($chunk){ # convert non-standard datetime
    $datetime = substr( $chunk, 0, 16) . ":00";
    $datetime = DateTimeImmutable::createFromFormat('d.m.Y H:i:s', $datetime);
    return $datetime->format('Y-m-d H:i:s');
}

# 2. read csv line by line, put values into array and store some in db
$dbc = new mysqli("localhost", 'heizkostenabrechnung', "KA-)1*hf[u7Qw[A.", "heizkostenabrechnung");
if( $dbc->connect_errno ){
   trigger_error( $dbc->connect_error );
   exit();
}

foreach( $CSVs as $c ) {
    $lines = file( dirname(__DIR__, 1) . '/' . $c );
    $i = 0;
    foreach( $lines as $line_num => $line) {
        if( $i%2 ){ # M-BUS/OMS format: every other line has values
            $chunks = explode(";", $line);
            $sql  = 'INSERT IGNORE INTO Messwerte SET Zaehler_ID = ';
            $sql .= $chunks[2];
            $sql .= ', Zeitpunkt = ';
            $sql .= '"' . chunkToDatetime( $chunks[9] ) . '"';
            $sql .= ', Wert = ';
            $sql .= str_replace( ',' , '.' , $chunks[10] );
	    $sql .= ';';
            $dbc->query( $sql ) or trigger_error ( $dbc->error );
        }
        $i++;
    }
}

# 3. move processed files into a folder named after the year
foreach( $CSVs as $c ) {
    $year = '20' . substr( $c, 9, 2 ); # get year from file name
    if( !is_dir( dirname(__DIR__, 1) . '/' . $year ) ) {
        mkdir( dirname(__DIR__, 1) . '/' . $year ); # create folders if not exits
    }
    rename( dirname(__DIR__,1) . '/' . $c, dirname(__DIR__, 1) . '/' . $year . '/' . $c );
}
foreach( $LOGs as $l ){
    $year = '20' . substr( $l, 9, 2 ); # get year from file name
    rename( dirname(__DIR__,1) . '/' . $l, dirname(__DIR__, 1) . '/' . $year . '/' . $l );
}


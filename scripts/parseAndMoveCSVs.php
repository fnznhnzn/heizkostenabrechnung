<?php
/*
 * Run by a cron job, this scipt parses CSV files found one directory above (put there by the gateway via ftp).
 * It reads the last 15 months' values stored in the meters, writes them to the database and then moves the 
 * files to a directory named after the year.
 * 
 * Values are written to a db table with a combined unique index (meter id and date). 
 * Hence this script can be run repeatedly without harm.
 * 
 * Todo:
 * fix date conversion
 * tame rouque loop starting in line 127
 * Fix HKV-Liste
 * 
 * Relevant columns in CSV file
 *  [2]: Meter ID
 *  [4]: Device Type
 *  [9]: Date and Time of last reading
 * [10]: Value of last reading (summed)
 * [11]: Date of year's end reading (Dec 31st)
 * [12]: Year's total
 * [13]: Date of reading 15 month before last reading
 * [14]: It's Value (summed)
 * [15]-[44]: 29 half-monthly values ("2"-"30", not summed)
 * [45]: Note or error code
 * [46]: Date of that
 * 
 * Procedure:
 * 1. provide some utils
 * 2. look for uploaded files
 * 3. read csv line by line
 * 4. store water meter readings
 * 5. store sums from last reading
 * 6. store year's totals
 * 7. store 30 readings
 * 8. write error codes
 * 9. move processed files into a folder named after the year
 *
*/

/* -- 1. utils ----------------------------------------------------------------------------------------------------------------------- 1. utils -- */
# database connection
$dbc = new mysqli("localhost", 'heizkostenabrechnung', "KA-)1*hf[u7Qw[A.", "heizkostenabrechnung");
if( $dbc->connect_errno ){
    trigger_error( $dbc->connect_error );
    exit();
}

# convert non-standard datetime
function chunkToDatetime($chunk){
    $datetime = substr( $chunk, 0, 16) . ":00";
    $datetime = DateTimeImmutable::createFromFormat('d.m.Y H:i:s', $datetime);
    return $datetime->format('Y-m-d H:i:s');
}

function storeWaterMeterReadings($dbc, $chunks){
    # most recent reading
    $sql  = 'INSERT IGNORE INTO Wasser SET Zaehler_ID = ';
    $sql .= $chunks[2];
    $sql .= ', Datum = ';
    $sql .= '"' . chunkToDatetime( $chunks[9] ) . '"';
    $sql .= ', Messwert = ';
    $sql .= str_replace( ',' , '.' , $chunks[10] );
    $sql .= ';';
    # last year's total
    $dbc->query( $sql ) or trigger_error ( $dbc->error );
    $sql  = 'INSERT IGNORE INTO Wasser SET Zaehler_ID = ';
    $sql .= $chunks[2];
    $sql .= ', Datum = ';
    $sql .= '"' . date( 'Y-m-d', strtotime($chunks[12]) ) . '"';
    $sql .= ', Messwert = ';
    $sql .= str_replace( ',' , '.' , $chunks[13] );
    $sql .= ';';
    $dbc->query( $sql ) or trigger_error ( $dbc->error );
}

/* -- 2. look for uploaded files ------------------------------------------------------------------------------------- 2. look for uploaded files -- */
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
$LOGs = array_filter( $files, 'isLOG' ); # future use

/* -- 3. read CSV line by line ----------------------------------------------------------------------------------------- 3. read csv line by line -- */
foreach( $CSVs as $c ) {
    $lines = file( dirname(__DIR__, 1) . '/' . $c );
    foreach( $lines as $line_num => $line) {
        if( $line_num%2 ){ # M-BUS/OMS format: every other line holds values
            $chunks = explode(";", $line);

            /* -- 4. store water meter readings ------------------------------------------------------------------- 4. store water meter readings -- */
            if( $chunks[4] == 'Water' ){
                storeWaterMeterReadings($dbc, $chunks);
                continue;
            } 
            
            /* -- 5. store most recent reading --------------------------------------------------------------------- 5. store most recend reading -- */
            $sql  = 'INSERT IGNORE INTO Messwerte SET Zaehler_ID = ';
            $sql .= $chunks[2];
            $sql .= ', Zeitpunkt = ';
            $sql .= '"' . chunkToDatetime( $chunks[9] ) . '"';
            $sql .= ', Wert = ';
            $sql .= str_replace( ',' , '.' , $chunks[10] );
            $sql .= ';';
            $dbc->query( $sql ) or trigger_error ( $dbc->error );

            /* -- 6. store year's totals ----------------------------------------------------------------------------------- 7. store years totals -- */
            $sql  = 'INSERT IGNORE INTO Messwerte SET Zaehler_ID = ';
            $sql .= $chunks[2];
            $sql .= ', Zeitpunkt = ';
            $sql .= '"' . $chunks[11] . '"';
            $sql .= ', Wert = ';
            $sql .= str_replace( ',' , '.' , $chunks[12] );
            $sql .= ';';
            $dbc->query( $sql ) or trigger_error ( $dbc->error );

            /* -- 7. store 30 readings --------------------------------------------------------------------------------------- 7. store 30 readings -- */
            $readingDate = strtotime( $chunks[13] ); # first of the 30 stored readings
/* >>> tame this loop! way too many records written to db! <<<
            for($e=14; $e<45; $e++){
                # readings happen on every 15th and on every last day of the month
                if($e%2){
                    $zp = date('Y-m-15 00:00:00', $readingDate);
                } else {
                    $zp = date('Y-m-t 00:00:00', $readingDate);
                    $readingDate = strtotime("last day of +1 month", $readingDate);
                }

                # year's first reading is negative because meter was reset, don't use
                if( substr($zp, 5, 5) === '01-15' ) continue; 
                
                $sql  = 'INSERT IGNORE INTO Messwerte SET Zaehler_ID = ';
                $sql .= $chunks[2];
                $sql .= ', Zeitpunkt = ';
                $sql .= '"' . $zp . '"';
                # inspite of all documentation, the first half month reading ([14]) is summed
                if( $e == 14 ){
                    $sql .= ', Wert = ';
                } else {
                    $sql .= ', Nettowert = ';
                }
                $sql .= str_replace( ',' , '.' , $chunks[$e] );
                $sql .= ';';
                $dbc->query( $sql ) or trigger_error ( $dbc->error );
            }
*/
            /* -- 8. write error codes --------------------------------------------------------------------------------------- 8. write error codes -- */
            if( $chunks[4] == 'HCA' && $chunks[44] != '0' ){
                $sql  = 'INSERT IGNORE INTO Fehler SET Zaehler_ID = ';
                $sql .= $chunks[2];
                $sql .= ', Hinweisdatum = ';
                $sql .= '"' . $chunks[45] . '"';
                $sql .= ', Hinweisflag = ';
                $sql .= '"' . $chunks[44] .'"';
                $sql .= ';';
                $dbc->query( $sql ) or trigger_error ( $dbc->error );
            }
        }
    }
}

/*-- 9. move processed files into a folder named after the year ------------------------------------------------------------- 9. move processed files -- */
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


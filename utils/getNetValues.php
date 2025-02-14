<?php
/**
 * Hilfs-Script, subtrahiert jeweils den Wert des Vormonats und schreibt 
 * das Ergebnis in die Spalte "Nettowert" der Tabelle "Messwerte".
 * 
 * Unklar ist noch, wann und wo es ausgeführt wird. Möglichkeiten wären:
 * - jährlich per cron
 * - manuell
 * - per Trigger
 * 
 */
$year = '2025';
$conn = new mysqli("localhost", 'heizkostenabrechnung', "KA-)1*hf[u7Qw[A.", "heizkostenabrechnung");
$conn->query("SET lc_time_names = 'de_DE'");

# iterate over all meters and store their ids in an array
$sql = <<<SQL
    SELECT 
        ID
    FROM 
        Zaehler
    ORDER BY
        ID
SQL;
$conn->query($sql);
$res = $conn->query($sql);
while($row = $res->fetch_assoc()){
    $zaehlerIDs[] = $row['ID'];
}

# loop trough all meters and and set each value minus the previous one
$resArray = [];
for($i = 0; $i < count($zaehlerIDs); $i++){
    $sql = <<<SQL
        SELECT 
            Zaehler_ID, Zeitpunkt, Wert, 
            COALESCE(Wert - LAG(Wert) OVER (ORDER BY Zeitpunkt),0) Nettowert
        FROM 
            Messwerte
        WHERE 
            YEAR(Zeitpunkt) = '$year'
        AND 
            Zaehler_ID =  '$zaehlerIDs[$i]'
    SQL;
    $res = $conn->query($sql);
    while($row = $res->fetch_assoc()){
        $resArray[] = $row['Nettowert'];
        $sql = <<<SQL
            UPDATE 
                Messwerte
            SET
                Nettowert = '$row[Nettowert]'
            WHERE 
                Zaehler_ID = '$zaehlerIDs[$i]'
            AND
                Zeitpunkt = '$row[Zeitpunkt]'
            AND
                Wert = '$row[Wert]'
        SQL;
        $conn->query($sql);
    }
}

# loop through all meters and set the first Nettowert to the first Wert
for($i = 0; $i < count($zaehlerIDs); $i++){
    $sql = <<<SQL
        SELECT 
            Zeitpunkt, Wert
        FROM 
            Messwerte
        WHERE 
            Zaehler_ID = '$zaehlerIDs[$i]'
        AND 
            YEAR(Zeitpunkt) = '$year'
        ORDER BY 
            Zeitpunkt ASC
        LIMIT 1
    SQL;
    $res = $conn->query($sql);
    $row = $res->fetch_assoc();
    $sql = <<<SQL
        UPDATE 
            Messwerte
        SET
            Nettowert = '$row[Wert]'
        WHERE 
            Zaehler_ID = '$zaehlerIDs[$i]'
        AND
            Zeitpunkt = '$row[Zeitpunkt]'
        AND
            Wert = '$row[Wert]'
    SQL;
    $conn->query($sql);
}


<?php
/* Einzelwerte pro Monat, wie theoretisch nach Heizkostenverordnung vorgeschrieben.
 * Skript ist erst am Anfang aber lovely LAG()-Funktion in Mariadb funktioniert!
 * Es fehlen noch Monatsangaben in der Spalten und die Auswahl des Jahres <= Hierzu kompletten Tabelleninhalt erst in mehrdimensionales Array schreiben
 *
 * + wichtig zu klären: Resetten die Zähler von Wallace am 31.12.?
 */
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 'on');

require_once('classes/Base.php');
require_once('classes/Warmwasser.php');
require_once('classes/Heizkostenverteiler.php');
require_once('classes/Flaechenverteilung.php');

$Base                   = new Base();
$Warmwasser             = new Warmwasser();
$Heizkostenverteiler    = new Heizkostenverteiler( $Warmwasser->Preis_Warmwasser );
$Flaechenverteilung     = new Flaechenverteilung( $Heizkostenverteiler->Preis_Heizung );

# get heat cost allocators
$sql = <<<SQL
    SELECT z.ID z, mi.Nachname n, z.Raum r, w.ID w
    FROM Zaehler z
    LEFT JOIN Messwerte m ON m.Zaehler_ID = z.ID
    LEFT JOIN Heizkoerper h ON h.ID = z.Heizkoerper_ID
    LEFT JOIN Wohnungen w ON w.ID = z.Whg_ID
    LEFT JOIN Mieter mi ON mi.ID = z.Whg_ID
    GROUP BY z.ID
    ORDER BY w.ID, Raum, z.ID
SQL;

?>
<!DOCTYPE html>
<html>
    <head>
        <title>Liste Heizkostenverteiler</title>
        <meta charset="utf-8">
        <link rel="stylesheet" href="css/style.css"/>
    </head>
    <body>
        <?php include("nav.inc.php"); ?>
        <h1>Rohwerte</h1>
        <p>Gemessene Werte vor Korrektur um Kc, Kq und Brennwertfaktor</p>
        <table>
<?php
echo '<th>Zähler</th><th>Mieter</th><th>Raum</th><th>p.a.</th><th>Werte</th>';

$lastName;
foreach ($Base->conn->query( $sql ) as $index => $row) {
    global $lastName; 
    # empty row before new tenant
    if( $lastName !== $row['n'] && $lastName !== null ) { 
        echo "\r\n".'<tr><td colspan="14" style="border:none;"><br/></td></tr>'."\r\n";
    }

    $total = $Heizkostenverteiler->getMeteredData( $Base->Abrechnungsjahr, '2023-01-01', '2023-12-31', '%', $row['z'] );
    # z = Zähler-ID, n = Nachname, r = Raum, total = Jahresgesamt
    echo '<tr>
    <td>'.$row['z'].'</td>
    <td>'.$row['n'].'</td>
    <td>'.$row['r'].'</td>
    <td class="alignRight">'. round( floatval( $total ), 1 ) . '</td>';
/*
    $resArray = $Heizkostenverteiler->getLastMeteredValue( $row['z'], false );
    foreach( $resArray as $val){
        $t = strtotime($val['Zeitpunkt']);
        echo '<td>' . date('My', $t). '<br/>' . round(floatval($val['LetzterWert']),1) . '</td>';
    }
*/
    #print_r( $Heizkostenverteiler->getMeteredDataByMonth( '2023-01-01', '2023-12-31', 1 ) );

    $resArray = $Heizkostenverteiler->getRawData( $row['z'] );
    foreach( $resArray as $val){
        $t = strtotime($val['d']);
        echo '<td nowrap>' . date('My', $t) . '<br/>' . round(floatval($val['v']),2) . '</td>';
    }

    echo "\r\n" . '</tr>';

    $lastName = $row['n']; 
}
?>
</table>
</body>
</html>

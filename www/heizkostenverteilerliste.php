<?php
/* Einzelwerte pro Monat, wie theoretisch nach Heizkostenverordnung vorgeschrieben.
 * Skript ist erst am Anfang aber lovely LAG()-Funktion in Mariadb funktioniert!
 * Es fehlen noch Monatsangaben in der Spalten und die Auswahl des Jahres
 *
 * + wichtig zu klären: Resetten die HKV am 31.12. oder nicht? (den csv-Daten nach zu urteilen sieht es fast so aus)
 * + Müssen HKV bei Niederwettberg noch auf Ultimo umgestellt werden (Monatsletzen)?
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
    SELECT z.ID z, mi.Nachname n, z.Raum r
    FROM Zaehler z
    LEFT JOIN Messwerte m ON m.Zaehler_ID = z.ID
    LEFT JOIN Heizkoerper h ON h.ID = z.Heizkoerper_ID
    LEFT JOIN Wohnungen w ON w.ID = z.Whg_ID
    LEFT JOIN Mieter mi ON mi.ID = z.Whg_ID
    GROUP BY z.ID
    ORDER BY w.ID, Raum, z.ID
SQL;

#print_r( $Heizkostenverteiler->getLastMeteredValue( '21116062', false ) );
#die();

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
        <table><th>Zähler</th><th>Mieter</th><th>Raum</th><th>p.a.</th><th>alle Werte</th>
<?php
$nn;
foreach ($Base->conn->query( $sql ) as $index => $row) {
    global $nn; 
    # empty row before new tenant
    if( $nn !== $row['n'] && $nn !== null ) { 
        echo "\r\n".'<tr><td colspan="14" style="border:none;"><br/></td></tr>'."\r\n";
    }

    $total = $Heizkostenverteiler->getMeteredData( $Base->Abrechnungsjahr, '2023-01-01', '2023-12-31', '%', $row['z'] );
    # z = Zähler-ID, n = Nachname, r = Raum, total = Jahresgesamt
    echo '<tr>
    <td>'.$row['z'].'</td>
    <td>'.$row['n'].'</td>
    <td>'.$row['r'].'</td>
    <td class="alignRight">'. round( floatval( $total ), 1 ) . '</td>';
    $resArray = $Heizkostenverteiler->getLastMeteredValue( $row['z'], false );
    foreach( $resArray as $val){
        echo '<td class="alignRight">' . round(floatval($val['LetzterWert']),1) . '</td>';
    }
    echo "\r\n" . '</tr>';

    $nn = $row['n']; 
}
?>
</table>
</body>
</html>

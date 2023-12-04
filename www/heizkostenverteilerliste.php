<?php
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

?>
<!DOCTYPE html>
<html>
    <head>
        <title>Liste Heizkostenverteiler</title>
        <meta charset="utf-8">
        <link rel="stylesheet" href="css/style.css"/>
    </head>
    <body>
        <a href="/?y= <?=$Base->Abrechnungsjahr?>">zurück</a>
        <table><th>Zähler</th><th>Mieter</th><th>Raum</th><th>p.a.</th><th>alle Werte</th>
<?php
$nn;
foreach ($Base->conn->query( $sql ) as $index => $row) {
    $total = $Heizkostenverteiler->getMeteredData( $Base->Abrechnungsjahr, '2023-01-01', '2023-12-31', '%', $row['z'] );
    $resArray = $Heizkostenverteiler->getLastMeteredValue( $row['z'], false );
    global $nn;
    if( $nn !== $row['n'] && $nn !== null ) { echo "\r\n".'<tr><td colspan="14" style="border:none;"><br/></td></tr>'."\r\n"; }
    echo '<tr>
    <td>'.$row['z'].'</td>
    <td>'.$row['n'].'</td>
    <td>'.$row['r'].'</td>
    <td class="alignRight">'. round( floatval( $total ), 1 ) . '</td>';
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

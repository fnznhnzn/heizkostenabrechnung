<?php
/* Einzelwerte pro Monat, wie theoretisch nach Heizkostenverordnung vorgeschrieben.
 * Es fehlen noch Monatsangaben in der Spalten und die Auswahl des Jahres <= Hierzu kompletten Tabelleninhalt erst in mehrdimensionales Array schreiben
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
    SELECT z.ID z, mi.Nachname n, z.Raum r, w.ID w, h.Kq q, h.Kc c, h.ID h
    FROM Zaehler z
    LEFT JOIN Messwerte m ON m.Zaehler_ID = z.ID
    LEFT JOIN Heizkoerper h ON h.ID = z.Heizkoerper_ID
    LEFT JOIN Wohnungen w ON w.ID = z.Whg_ID
    LEFT JOIN ( SELECT mi.*, MIN(mi.ID) dtID  /* latest tenant only */
		FROM Mieter mi 
		INNER JOIN Zaehler z 
		ON mi.Whg_ID = z.Whg_ID 
		GROUP BY mi.ID ) as dt
	ON z.Whg_ID = dt.Whg_ID
    LEFT JOIN Mieter mi ON mi.ID = dt.dtID
    WHERE z.Whg_ID <> 0 /* ignore the basement */
    GROUP BY z.ID
    ORDER BY w.ID, Raum, z.ID
SQL;

?>
<!DOCTYPE html>
<html>
    <head>
        <title>Liste Heizkostenverteiler</title>
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <meta charset="utf-8">
        <link rel="stylesheet" href="css/style.css"/>
    </head>
    <body>
        <?php include("nav.inc.php"); ?>
        <h1>Rohwerte</h1>
        <p>Gemessene Werte vor und nach Korrektur um Kq, Kc und Brennwertfaktor.<br/>
        <span class="subtle">Kq = Heizkörperleistung in Kw, Kc = Messgeräteträgheit, Brennwertfaktor des Kessels</span>
        <pre>Wert x Kc x Kq / 2,288</pre> </p>
        <table>
<?php
echo '<th>Zähler</th><th></th><th></th><th colspan="8">Monat / Wert roh / Wert bereinigt</th>';

$lastName;
foreach ($Base->conn->query( $sql ) as $index => $row) {
    global $lastName; 
    # empty row before new tenant
    if( $lastName !== $row['n'] && $lastName !== null ) { 
        echo "\r\n".'<tr><td colspan="140" style="border:none;"><br/><hr/></td></tr>'."\r\n";
    }
    
    $total = $Heizkostenverteiler->getMeteredData( $Base->Abrechnungsjahr, '2023-01-01', '2023-12-31', '%', $row['z'] );
    # z = Zähler-ID, n = Nachname, w = Wohnung, r = Raum, total = Jahresgesamt
    echo '<tr>
    <td>'.$row['z'].'<br><span class="subtle">Kq ' . $row['q'] . '</br>Kc ' . $row['c'] . '</span></td>
    <td>Whg. '.$row['w'].'</br><span class="subtle">' . $row['n'] . '</br>' . $row['r'] . '</span></td>
    <td><a href="i/heizkoerper/HeizkoerperID-' . $row['h'] . '.jpg"><img src="i/heizkoerper/thumbs/thumb-HeizkoerperID-' . $row['h'] . '.jpg "/></a></td>';

    $resArray = $Heizkostenverteiler->getRawData( $row['z'] );
    foreach( $resArray as $val){
        # d = Datum, v = Wert, cv = Wert bereinigt
        $t = strtotime($val['d']);
        echo '<td nowrap><span class="subtle">' . date('n/y', $t) . '</span><br/>' . round(floatval($val['v']),2) . '<br/>' . round(floatval($val['cv']),2) . '</td>';
    }

    echo "\r\n" . '</tr>';

    $lastName = $row['n']; 
}
?>
</table>
</body>
</html>

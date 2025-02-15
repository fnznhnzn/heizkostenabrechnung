<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 'on');

require_once('../classes/Base.php');
require_once('../classes/Warmwasser.php');
require_once('../classes/Heizkostenverteiler.php');
require_once('../classes/Flaechenverteilung.php');

$Base                   = new Base();
$Warmwasser             = new Warmwasser();
$Heizkostenverteiler    = new Heizkostenverteiler( $Warmwasser->Preis_Warmwasser );
$Flaechenverteilung     = new Flaechenverteilung( $Heizkostenverteiler->Preis_Heizung );

$y = $_GET['y'] ?? date('Y');
?>
<h1>Summe LAG-Werte = Jahresendwert?</h1>
<p>Die Zähler an den Heizkörpern laufen ab jedem 1.1. von 0 hoch und zeigen am 31.12. den Gesamtwert des Jahres.<br/>
Für den Verbrauch eines Monats wird deshalb per SQL-Funktion "LAG()" der Wert des jeweiligen Vormonats abgezogen.<br/>
Stimmt für <strong>> <?=$y?> <</strong> der Jahresendwert mit der Summe der Monatswerte überein?</p>
<table>
<tr>
    <th>Zähler</th>
    <th>Jahresendwert</th>
    <th>Summe LAG()-Werte</th>
</tr>
<?php
$sql = <<<SQL
SELECT *, SUM(Nettowert) nw, MAX(Wert) w
    FROM Zaehler z
    LEFT JOIN Heizkoerper h ON z.Heizkoerper_ID = h.ID
    LEFT JOIN Messwerte m ON z.ID = m.Zaehler_ID
    WHERE YEAR(Zeitpunkt) = '$y'
    GROUP BY Zaehler_ID
    ORDER BY Zaehler_ID DESC
SQL;

$res = $Base->conn->query($sql);
while( $row = mysqli_fetch_assoc( $res )){
    #print_r($row);
   echo '<tr>';
   echo '<td>' . $row['Zaehler_ID'] . '</td>';
   echo '<td align="right">' . $row['w'] . '</td>';
   echo '<td align="right">' . $row['nw'] . '</td>';
   echo '</tr>';
}
echo '</table>';





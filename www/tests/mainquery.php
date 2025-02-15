<?php
echo "defunct!";
die('needs to be rewritten');

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



# do
# 1. Test schreiben
# 2. Plausibilität prüfen
# 3. Prüftool für einzelne Zähler bzw. Heizkörper
 
echo 'Messwerte jedes Verteilers, berechnet in SQL und PHP. Query ist demnach in Ordnung. Abweichungen nach der vierten Stelle hinter dem Komma sind systembedingt normal.';
echo '<table>';
echo '<tr><th>Whg.</th><th>Raum</th><th>Zaehler</th><th>Rohwert</th><th>Kq</th><th>Kc</th><th>Wert SQL</th><th>Wert php</th></tr>';
$sql = <<<SQL
    SELECT *, MAX(Wert) w FROM Zaehler z
    LEFT JOIN Heizkoerper h ON z.Heizkoerper_ID = h.ID
    LEFT JOIN Messwerte m ON z.ID = m.Zaehler_ID
    GROUP BY Zaehler_ID
    ORDER BY Whg_ID, Raum DESC
    SQL;

$res = $Base->conn->query($sql);
while( $row = mysqli_fetch_assoc( $res )){
    #print_r($row);
   echo '<tr>';
   echo '<td>' . $row['Whg_ID'] . '</td>';
   echo '<td>' . $row['Raum'] . '</td>';
   echo '<td>' . $row['Zaehler_ID'] . '</td>';
   echo '<td>' . $row['w'] . '</td>';
   echo '<td>' . $row['Kq'] . '</td>';
   echo '<td>' . $row['Kc'] . '</td>';
   echo '<td>' . $Heizkostenverteiler->getMeteredData($Base->Abrechnungsjahr, '0000-00-00', '0000-00-00', '%', $row['Zaehler_ID']) . '</td>';
   echo '<td>' . $row['w'] * $row['Kq'] * $row['Kc'] / 1.181 . '</td>';

   echo '</tr>';
}
echo '</table>';
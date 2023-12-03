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

# Werte pro Zähler im Abrechnungsjahr
$sql = "SELECT z.ID zid, MAX(m.Wert) w
FROM Messwerte m
LEFT JOIN Zaehler z
ON m.Zaehler_ID = z.ID
WHERE YEAR(m.Zeitpunkt) = {$Base->Abrechnungsjahr}
AND z.Whg_ID IN (SELECT ID FROM Wohnungen) /* otherwise gateway picks up rogue meters not yet blacklisted */
GROUP BY Zaehler_ID";

foreach ($Base->conn->query( $sql ) as $index => $row) {
    $messwerteGesamt[$row['zid']] = $row['w'];
}

$sql = "SELECT z.ID zid, MAX(m.Wert) vjw, h.Kq q, h.Kc c
FROM Messwerte m
LEFT JOIN Zaehler z
ON m.Zaehler_ID = z.ID
LEFT JOIN Heizkoerper h
ON z.Heizkoerper_ID = h.ID
WHERE YEAR(m.Zeitpunkt) = " . ($Base->Abrechnungsjahr - 1) . " /* Werte aus Vorjahr abziehen, um Jahreswerte zu bekommen */
AND z.Whg_ID IN (SELECT ID FROM Wohnungen)
GROUP BY Zaehler_ID";

?>
<!DOCTYPE html>
<html>
    <head>
        <title>Liste Heizkostenverteiler</title>
        <meta charset="utf-8">
    <link rel="stylesheet" href="style.css"/>
    </head>
    <body>
        <a href="gesamtabrechnung.php?y= <?=$Base->Abrechnungsjahr?>">zurück</a>
        <table><th>Zähler</th><th>Messwert</th><th></th><th>Kq</th><th></th><th>Kc</th><th></th><th>Basis</th><th></th><th>Wert</th>
<?php
foreach ($Base->conn->query( $sql ) as $index => $row) {
    $messwerteLaufendesJahr[$row['zid']] = $messwerteGesamt[$row['zid']] - $row['vjw']; # Jahreswert = Gesamtwert - Vorjahreswert (denn Zähler zählen immer weiter)
    echo "<tr>
    <td>".$row['zid'].'</td>
    <td class="center">'.$messwerteLaufendesJahr[$row['zid']].'</td>
    <td> x </td>
    <td class="center">'.$row['q'].'</td>
    <td> x </td>
    <td class="center">'.$row['c'].'</td>
    <td> / </td>
    <td>1,181</td>
    <td> = </td>
    <td>'. $Base->nf($messwerteLaufendesJahr[$row['zid']] * $row['q'] * $row['c'] / 1.181) . '</td></tr>';
}
?>
</table>
</body>
</html>
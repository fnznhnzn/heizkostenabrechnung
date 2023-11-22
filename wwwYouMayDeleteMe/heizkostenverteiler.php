<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 'on');

require_once('classes/Gasrechnung.php');
$gas = new Gasrechnung();

require_once('classes/Verteilung.php');
$hkv = new Verteilung($gas);

# Werte pro Zähler im Abrechnungsjahr
$sql = "SELECT z.ID zid, MAX(m.Wert) w
FROM Messwerte m
LEFT JOIN Zaehler z
ON m.Zaehler_ID = z.ID
WHERE YEAR(m.Zeitpunkt) = {$gas->Abrechnungsjahr}
AND z.Whg_ID IN (SELECT ID FROM Wohnungen) /* otherwise gateway picks up rogue meters not yet blacklisted */
GROUP BY Zaehler_ID";

foreach ($gas->conn->query( $sql ) as $index => $row) {
    $messwerteGesamt[$row['zid']] = $row['w'];
}

$sql = "SELECT z.ID zid, MAX(m.Wert) vjw, h.Kq q, h.Kc c
FROM Messwerte m
LEFT JOIN Zaehler z
ON m.Zaehler_ID = z.ID
LEFT JOIN Heizkoerper h
ON z.Heizkoerper_ID = h.ID
WHERE YEAR(m.Zeitpunkt) = " . ($gas->Abrechnungsjahr - 1) . " /* Werte aus Vorjahr abziehen, um Jahreswerte zu bekommen */
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
        <a href="index.php?y= <?=$gas->Abrechnungsjahr?>">zurück</a>
        <table><th>Zähler</th><th>Messwert</th><th></th><th>Kq</th><th></th><th>Kc</th><th></th><th>Basis</th><th></th><th>Wert</th>
<?php
foreach ($gas->conn->query( $sql ) as $index => $row) {
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
    <td>'. $hkv->nf($messwerteLaufendesJahr[$row['zid']] * $row['q'] * $row['c'] / 1.181) . '</td></tr>';
}
?>
</table>
</body>
</html>
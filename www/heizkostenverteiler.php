<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 'on');

require_once('classes/Gasrechnung.php');
$gas = new Gasrechnung();

require_once('classes/Verteilung.php');
$hkv = new Verteilung($gas);

# Werte pro Zähler im Abrechnungsjahr (Werte des Vorjahres müssen noch abgezogen werden)
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

$sql = "SELECT z.ID zid, MAX(m.Wert) w, h.Kq q, h.Kc c
FROM Messwerte m
LEFT JOIN Zaehler z
ON m.Zaehler_ID = z.ID
LEFT JOIN Heizkoerper h
ON z.Heizkoerper_ID = h.ID
WHERE YEAR(m.Zeitpunkt) = " . ($gas->Abrechnungsjahr - 1) . "
AND z.Whg_ID IN (SELECT ID FROM Wohnungen)
GROUP BY Zaehler_ID";


echo '<table><th>Zähler</th><th>Messwert</th><th></th><th>Kq</th><th></th><th>Kc</th><th></th><th>Basis</th><th></th><th>Wert</th>';
$messwerteTotal = 0;
foreach ($gas->conn->query( $sql ) as $index => $row) {
    $messwerteLaufendesJahr[$row['zid']] = $messwerteGesamt[$row['zid']] - $row['w'];
    $messwerteTotal += $messwerteLaufendesJahr[$row['zid']]*$row['q']*$row['c']/1.181;
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
echo "</table>";
<?php
error_reporting(E_ALL);
ini_set('display_errors', 'on');

include('GAS.php');
$gas = new Gasrechnung();

include('ZAEHLER.php');
$hkv = new Heizkostenverteiler();

define('WASSERVERBRAUCH', 123); # dummie value!
?>

<!DOCTYPE html>
<html><head><title>Heizkostenabrechnung Übersicht</title>
<meta charset="utf-8">
<link rel="stylesheet" href="style.css"/>
<link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto">
<script src='https://cdnjs.cloudflare.com/ajax/libs/mathjax/2.7.4/MathJax.js?config=default'></script>
</head>
<body>
<h1>Heizkosten <?=$gas->Abrechnungsjahr?></h1>
<p>Erzeugt die Zentralheizung auch das Warmwasser werden die Kosten dafür vorher abgezogen und ebenfalls nach Wohnfläche verteilt (§9 Absatz 1).</p>

<h2>Gasrechnung</h2>
<p>Gasrechnung für <?=$gas->Abrechnungsjahr?> vom <?=$gas->Rechnungsdatum;?> von <?=$gas->Lieferant?> für <?=$gas->Kubikmeter?>m³ Erdgas H: <strong><?=$gas->RechnungsbetragE?></strong>
(entspricht <strong><?=$gas->Kubikmeterpreis?></strong> pro m³)</p>

<!-- --------------------------------------------------------------------------------------------------------- Warmwasser (nach Wohnfläche) -->
<h2>Warmwasser</h2>
<p>Formel für Gasverbrauch pro m³ erwärmtes Wasser (lt. HeizkostenV, §9 Absatz 2-3): $$ {B = \frac{2,5 * V * (tw-10)}{H_{i}}} $$ wobei:</p>
<ul>
    <li>B = Gasverbrauch in m³</li>
    <li>der Wert 2,5 für die Erzeugeraufwandszahl des Wärmeerzeugers, die mittlere spezifische Wärmekapazität des Wassers, die Wärmeverluste für Warmwasserspeicher, Verteilung einschließlich Zirkulation, Messdatenerhebungen zum Warmwasserverbrauch</li>
    <li>V = Warmwasserverbrauch in m³</li>
    <li>tw = Warmwassertemperatur (üblicherweise <strong>55°</strong>)</li>
    <li>der Wert 10 für die übliche Kaltwassereintrittstemperatur in die Warmwasserversorgungsanlage in Grad Celsius</li>
    <li>H<sub>i</sub> = Heizwert des Brennstoffs (10 bei Erdgas H)</li>
</ul>
<p>Warmwasserverbrauch (lt. Kaltwasserzulauf zum Boiler): <strong><?=WASSERVERBRAUCH?> m³</strong>
<p>Mit eingesetzten Werten: $$ {\frac{2,5 * <?=WASSERVERBRAUCH?> * (55-10)}{10} = <?=$gas->VerbrauchWarmwasser?>\ m³} $$</p>
<p><?=$gas->VerbrauchWarmwasser?> m³ Gasverbrauch x <?=$gas->Kubikmeterpreis?> pro m³ = <strong><?=$gas->PreisWarmwasserE?></strong>

<h2>Kostenaufteilung Heizung</h2>

<pre>
  <?=$gas->RechnungsbetragE?> Gasrechnung
- <?=$gas->PreisWarmwasserE?> Wassererwärmung
= <?=$gas->PreisHeizungE?> Heizkosten</pre>

<!-- --------------------------------------------------------------------------------------------------------------- Heizkosten nach Verbrauch -->
<h3>70% nach Verbrauch</h3>
<p>Nach <a href="https://www.gesetze-im-internet.de/heizkostenv/BJNR002610981.html">Heizkostenverordnung</a> müssen  
50-70% der Heizkosten nach Verbrauch aufgeteilt werden (§8 Absatz 1). 70% belohnt die Sparsamen und ist daher üblich.<p>
<pre>  <?=$gas->PreisHeizungE?> x 0,7 = <strong><?=$gas->PreisHeizung70ProzentE?></strong></pre>
<p>Diese Heizkosten teilt man durch die Summe aller Messwerte, um den Preis pro Messwert zu erhalten. 
    Anschließend multipliziert man den Preis pro Messwert mit den Messwerten einer Wohnung und erhält so deren Anteil an den Heizkosten.</p>
<h3>Heizkostenverteiler</h3>
<p>Jeder Messwert wird zwecks Vergleichbarkeit mit der Heizkörperleistung in KW (Kc) und der Trägheit (Kq) des jeweiligen Heizkörpers 
    multipliziert und dann durch die Basisempflindlichkeit der Heizkostenverteiler geteilt (Engelmann: 2,288).</p>
<?php

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

/*
echo "<table><th>Zähler</th><th>Messwert</th><th></th><th>Kq</th><th></th><th>Kc</th><th></th><th>Basis</th><th></th><th>Wert</th>";
$messwerteTotal = 0;
foreach ($gas->conn->query( $sql ) as $index => $row) {
    $messwerteLaufendesJahr[$row['zid']] = $messwerteGesamt[$row['zid']] - $row['w'];
    $messwerteTotal += $messwerteLaufendesJahr[$row['zid']]*$row['q']*$row['c']/2.288;
    echo "<tr>
    <td>".$row['zid']."</td>
    <td>".$messwerteLaufendesJahr[$row['zid']]."</td>
    <td> x </td>
    <td>".$row['q']."</td>
    <td> x </td>
    <td>".$row['c']."</td>
    <td> / </td>
    <td>2,288</td>
    <td> = </td>
    <td>". $messwerteLaufendesJahr[$row['zid']] * $row['q'] * $row['c'] / 2.288 . "</td></tr>";
}
echo "</table>";

echo "<br/>Die Summe aller in $gas->Abrechnungsjahr so berechneten Werte ist (errechnet in SQL und addiert in php) {$hkv->summeAllerZaehlerwerte()} bzw. (errechnet und addiert in php) $messwerteTotal (???)<br/>";
*/
?>
<p>Die Summe aller HKV-Werte beträgt <?=$hkv->summeAllerZaehlerwerte()?>. 
Teilt man dadurch die nach Verbrauch aufzuteilenden Heizkosten erhält man den Preis pro Messwert.</p>
$$ {\frac{<?=$gas->PreisHeizung70ProzentE?>}{<?=$hkv->summeAllerZaehlerwerte()?>} = <?=( $gas->PreisHeizung70Prozent / $hkv->summeAllerZaehlerwerte() )?>} $$

<?php
$messWertFaktor = $gas->PreisHeizung70Prozent / $hkv->summeAllerZaehlerwerte();
echo "<br/>Jetzt die gemessenen Werte pro Wohnung mit diesem Faktor multiplizieren.<br/>";
?>
<table>
    <th>Mieter</th><th>HKV</th><th></th><th>Faktor</th><th></th><th></th><th></th><th>Euro</th>

<?php
$e=0;
foreach ($gas->conn->query( $hkv->zaehlerwerteGesamtProWohnung() ) as $index => $row) {
    echo "<tr>
    <td>{$row['Nachname']}</td>
    <td>{$row['whgTotal']}</td>
    <td>x</td>
    <td>" . $messWertFaktor . "</td>
    <td>=</td>
    <td>" . $hkv->einzelneZaehlerwerte()[$e]*$messWertFaktor . "</td>
    <td>~</td>
    <td>" . $gas->euro($hkv->einzelneZaehlerwerte()[$e]*$messWertFaktor) . "</td>
    </tr>"; 
    $e++;
}
?>
</table>

<!-- ------------------------------------------------------------------------------------------------------------- Heizkosten nach Wohnfläche -->
<h3>30% nach Wohnfläche</h3>
<p>30% der verbleibenden Heizkosten: <?=$gas->PreisHeizungE?> x 0.3 = <?=$gas->PreisHeizung30Prozent?></p>
<p>Ergibt Kosten pro m²: <?=$gas->PreisHeizung30Prozent?> / <?=$gas->getWohnflaeche()?> = <?=$gas->preisProQuadratmeter?></p>
<p>Macht für die einzelnen Wohnungen entsprechend deren Fläche in m²:</p>
<table>
    <th>Mieter</th><th>m²</th><th>Faktor</th><th>Euro</th>
    <?php
foreach( $gas->gaspreisNachWohnflaeche() as $index => $row){
    echo "<tr>
    <td> {$row['Nachname']} </td>
    <td>{$row['qm']}</td>
    <td>x ". $gas->preisProQuadratmeter ." =</td>
    <td> ". $gas->euro($row['gpnw']) . "</td>
    </tr>";
}
?>
</table>
</body>
</html>
<?php
error_reporting(E_ALL);
ini_set('display_errors', 'on');

require_once('Gasrechnung.php');
$gas = new Gasrechnung();

require_once('Heizkostenverteiler.php');
$hkv = new Heizkostenverteiler($gas);

define('WASSERVERBRAUCH', 123); # dummie value!
?>

<!DOCTYPE html>
<html><head><title>Heizkostenabrechnung Übersicht</title>
    <meta charset="utf-8">
    <link rel="stylesheet" href="style.css"/>
    </head>
<body>
<h1>HK-Abrechnung <?=$gas->Abrechnungsjahr?></h1>

<h2>Gasrechnung</h2>
<p>Gasrechnung vom <?=$gas->Rechnungsdatum;?> von <?=$gas->Lieferant?>, Abrechnungsjahr <?=$gas->Abrechnungsjahr?>,  
<?=$gas->KilowattstundenD?> kWh Erdgas: <strong class="skyblue"><?=$gas->RechnungsbetragE?></strong>. Dies entspricht einem Gaspreis von 
<strong class="yellow"><?=$gas->KilowattstundenpreisE?></strong> pro kWh.</p>

<!-- --------------------------------------------------------------------------------------------------------- Warmwasser (nach Wohnfläche) -->
<h2>Warmwassererwärmung abziehen</h2>
<p>Lt. <a href="https://www.gesetze-im-internet.de/heizkostenv/BJNR002610981.html" target="_blank">HeizkostenV</a> muss zentrale Warmwassererwärmung zunächt abgezogen werden. Nach §9 Ziffer 2 ist der Gasverbrauch für zentrale Wasserwerwärmung wie folgt 
zu berechnen:</p>
<pre>                                  Q = 2,5 x V x (tw-10)</pre>
<ul>
    <li>Q = Gasverbrauch in Kilowattstunden</li>
    <li>2,5 der Wert für die Erzeugeraufwandszahl des Wärmeerzeugers, mittlere spezifische Wärmekapazität des Wassers, Wärmeverluste für Warmwasserspeicher, Verteilung einschließlich Zirkulation, Messdatenerhebungen zum Warmwasserverbrauch</li>
    <li>V = Warmwasserverbrauch in m³</li>
    <li>tw = Warmwassertemperatur (üblicherweise <strong>55°</strong>)</li>
    <li>10 der Wert für die übliche Kaltwassereintrittstemperatur in die Warmwasserversorgungsanlage in Grad Celsius</li>
</ul>
<p>Der Warmwasserverbrauch betrug im Jahr <?=$gas->Abrechnungsjahr?>: <strong><?=WASSERVERBRAUCH?> m³</strong>. Damit ergibt sich 
als Gasverbrauch für die Wassererwärmung:</p>
<pre>                            2,5 x <strong><?=WASSERVERBRAUCH?></strong> * (55-10) = <strong class="green"><?=$gas->VerbrauchWarmwasserD?> kWh</strong></pre>

<p>Nun den Gasverbrauch für Wassererwärmung mit dem Preis pro Kilowattstunde multiplizieren:</p>
<pre>                       <strong class="green"><?=$gas->VerbrauchWarmwasserD?></strong> kWh Gasverbrauch x <strong class="yellow"><?=$gas->KilowattstundenpreisE?></strong> = <strong class="pink"><?=$gas->PreisWarmwasserE?></strong></pre>
<p>Die Gasrechnung abzüglich der Warmwasserkosten ergibt die Heizkosten:</p>
<table>
    <tr><td colspan="2">Gasrechnung</td><td class="alignRight"><strong class="skyblue"><?=$gas->RechnungsbetragE?></strong></td></tr>
    <tr><td>minus</td><td>Wassererwärmung</td><td class="alignRight"><strong class="pink"><?=$gas->PreisWarmwasserE?></pink></td></tr>
    <tr><td>gleich</td><td>Heizkosten</td><td class="alignRight"><strong class="orange"><?=$gas->PreisHeizungE?></strong></td></tr>
</table>

<!-- --------------------------------------------------------------------------------------------------------------- Heizkosten nach Verbrauch -->
<h2>70% nach Verbrauch</h2>
<p>50-70% der Heizkosten müssen nach Verbrauch aufgeteilt werden (<a href="https://www.gesetze-im-internet.de/heizkostenv/BJNR002610981.html" target="_blank">HeizkostenV</a> §8 Absatz 1). 70% belohnt die Sparsamen und ist daher üblich.<p>
<pre>  <strong class="orange"><?=$gas->PreisHeizungE?></strong> x 0,7 = <strong class="brown"><?=$gas->PreisHeizung70ProzentE?></strong></pre>

<!-- --------------------------------------------------------------------------------------------------------------- Heizkostenverteiler -->
<h2>Heizkostenverteiler</h2>
<p>Heizkörper haben unterschiedliche Größen und Eigenschaften. Um die Messwerte der Heizkostenverteiler vergleichbar
    zu machen, werden sie zunächst wie folgt bereinigt:</p>
<pre>Bereinigter Messwert = M x Kc x Kq / B </pre>
wobei:
<ul>
    <li>M = Rohwert des Heizkostenverteilers</li>
    <li>Kc = Leistung des Heizkörpers in Kilowatt</li>
    <li>Kq = Trägheit des Heizkörpers</li>
    <li>B = Basisempfindlichkeit des HKV, 1,181 bei Engelmann HCA e2 im 1-Fühler-Modus</li>
</ul>

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

?>
<p>Die Summe aller HKV-Werte <?=$gas->Abrechnungsjahr?> beträgt: <?=$hkv->nf( $hkv->summeAllerZaehlerwerte() )?>.</p> 
<p>Die Heizkosten geteilt durch die Summe aller Messwerte ergibt den Preis pro Wert. 
    Diesen multipliziert man mit den Werten einer Wohnung und erhält so deren Anteil an den Heizkosten.</p>
<pre><strong class="brown"><?=$gas->PreisHeizung70ProzentE?></strong> / <?=$hkv->summeAllerZaehlerwerte()?> = <strong class="white"><?=( $hkv->nf( $gas->PreisHeizung70Prozent / $hkv->summeAllerZaehlerwerte() ) )?> €</strong></pre>

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
<p>30% der verbleibenden Heizkosten: <strong class="orange"><?=$gas->PreisHeizungE?></strong> x 0.3 = <?=$gas->euro( $gas->PreisHeizung30Prozent )?></p>
<p>Ergibt Kosten pro m²: <?=$gas->euro( $gas->PreisHeizung30Prozent )?> / <?=$gas->getWohnflaeche()?> = <?=$gas->preisProQuadratmeter?></p>
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

<?php
/*
1.  Warmwasser
2.  Heizung
3.  70% Heizkosten nach Verbrauch
4.  Heizkostenverteiler
5.  Verteilung auf die Wohnungen
6.  30% nach Fläche
7.  Heizkosten nach HKV pro Wohnung
8.  Heizkosten nach Wohnfläche pro Wohnung
9.  Heizkosten gesamt pro Wohnung
10. Warmwasserkosten
11. Kohlendioxidkostenverteilungsgesetz
*/
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 'on');

require_once('classes/Base.php');
require_once('classes/Warmwasser.php');
require_once('classes/Heizkostenverteiler.php');
require_once('classes/Flaechenverteilung.php');
require_once('classes/CO2AufG.php');

$Base                   = new Base();
$Warmwasser             = new Warmwasser();
$Heizkostenverteiler    = new Heizkostenverteiler( $Warmwasser->Preis_Warmwasser );
$Flaechenverteilung     = new Flaechenverteilung( $Heizkostenverteiler->Preis_Heizung );
$CO2AufG                = new CO2AufG( $Base->Gesamtwohnflaeche );
?>

<!DOCTYPE html>
<html lang="de">
    <head>
        <title>Heizkostenabrechnung Übersicht</title>
        <meta charset="utf-8">
        <link rel="stylesheet" href="css/style.css"/>
    </head>
    <body>
        <?php include("nav.inc.php"); ?>
        <h1>Übersicht HK-Abrechnung <?=$Base->Abrechnungsjahr?></h1>

        <h2>Gasrechnung</h2>
            <p>Gasrechnung vom <?=$Base->Rechnungsdatum?> von <?=$Base->Lieferant?> für das Jahr <?=$Base->Abrechnungsjahr?>:<br/>  
            <strong class="red"><?=$Base->Kilowattstunden?></strong> Kilowattstunden Erdgas kosteten <strong class="skyblue"><?=$Base->RechnungsbetragE?></strong><br/>
            Der Preis für eine Kilowattstunde beträgt damit <strong class="yellow"><?=$Base->KilowattstundenpreisE?></strong></p>

<!-- 1. --------------------------------------------------------------------------------------------------------- Warmwasser (nach Wohnfläche) -->
        <h2>Wassererwärmung</h2>
        <p>Lt. <a href="https://www.gesetze-im-internet.de/heizkostenv/" target="_blank">HeizkostenV</a> müssen die Kosten für die Warmwassererwärmung zunächt abgezogen werden. Nach §9 Ziffer 2 ergibt sich der Gasverbrauch für eine zentrale Wassererwärmung wie folgt:</p>
        <pre>                            2,5 x V x (<?=$Warmwasser::TW?>-<?=$Warmwasser::Hi?>) = Q</pre>
        <p>Dabei ist:</p>
        <ul>
            <li>2,5 der Wert für die Erzeugeraufwandszahl des Wärmeerzeugers, mittlere spezifische Wärmekapazität des Wassers, Wärmeverluste für Warmwasserspeicher, Verteilung einschließlich Zirkulation, Messdatenerhebungen zum Warmwasserverbrauch</li>
            <li>V = Warmwasserverbrauch in m³</li>
            <li>tw = Warmwassertemperatur (üblicherweise <strong><?=$Warmwasser::TW?>°</strong>)</li>
            <li><?=$Warmwasser::Hi?> der Wert für die übliche Kaltwassereintrittstemperatur in die Warmwasserversorgungsanlage in Grad Celsius</li>
            <li>Q = Gasverbrauch in Kilowattstunden</li>
        </ul>
        <p>In <?=$Base->Abrechnungsjahr?> wurden insgesamt <strong><?=$Warmwasser::WARMWASSERKUBIKMETER?></strong> Kubikmeter warmes Wasser verbraucht. Damit ergibt sich 
        als Gasverbrauch für die Wassererwärmung (s.o.):</p>
        <pre>                            2,5 x <strong><?=$Warmwasser::WARMWASSERKUBIKMETER?></strong> x (<?=$Warmwasser::TW?>-<?=$Warmwasser::Hi?>) = <?=$Warmwasser->kWh_Gas_fuer_Warmwasser?> kWh</pre>
        <p>Gemäß §9 HeizkostenV muss der Gasverbrauch bei brennwertbezogener Abrechnung mit 1,11 multipliziert werden:
        <pre>    <?=$Warmwasser->kWh_Gas_fuer_Warmwasser?> x 1,11 = <strong class="green"><?=$Warmwasser->kWh_Gas_fuer_Warmwasser_mit_Brennwertfaktor?></strong></pre>
        <p>Die Kosten für die gesamte Wassererwärmung betragen folglich:</p>
        <pre>    <strong class="green"><?=$Warmwasser->kWh_Gas_fuer_Warmwasser_mit_Brennwertfaktor?></strong> kWh Gasverbrauch x <strong class="yellow"><?=$Base->KilowattstundenpreisE?></strong> = <strong class="pink"><?=$Warmwasser->Preis_WarmwasserE?></strong></pre>
        <p>Oder mit Verhälnissen gerechnet:</p>
        <pre>    <strong class="green"><?=$Warmwasser->kWh_Gas_fuer_Warmwasser_mit_Brennwertfaktor?></strong> kWh für Warmwasser : <strong class="red"><?=$Base->Kilowattstunden?></strong> kWh Gesamtverbrauch x <strong class="skyblue"><?=$Base->RechnungsbetragE?></strong> = <strong class="pink"><?=$Base->euro($Warmwasser->Preis_Warmwasser_BrunataStyle)?></strong></pre>

<!-- 2. ---------------------------------------------------------------------------------------------------------------------------- Heizung -->
        <h2>Heizung</h2>
        <p>Die Gasrechnung abzüglich der Kosten für die Wassererwärmung ergibt die eigentlichen Heizkosten:</p>
        <table>
            <tr><td colspan="2">Gasrechnung</td><td class="alignRight"><strong class="skyblue"><?=$Base->RechnungsbetragE?></strong></td></tr>
            <tr><td>minus</td><td>Wassererwärmung</td><td class="alignRight"><strong class="pink"><?=$Warmwasser->Preis_WarmwasserE?></pink></td></tr>
            <tr><td>gleich</td><td>Heizkosten</td><td class="alignRight"><strong class="orange"><?=$Heizkostenverteiler->Preis_HeizungE?></strong></td></tr>
        </table>

<!-- 3. -------------------------------------------------------------------------------------------------------- 70% Heizkosten nach Verbrauch -->
        <h2>Verbrauchsabhängige Aufteilung</h2>
        <p>50-70% der verbleibenden Heizkosten müssen nach Verbrauch aufgeteilt werden (<a href="https://www.gesetze-im-internet.de/heizkostenv/BJNR002610981.html" target="_blank">HeizkostenV</a> §6 + §8 Absatz 1). 70% belohnt die Sparsamen und ist daher üblich.<p>
        <p>70% der Heizkosten: <strong class="orange"><?=$Heizkostenverteiler->Preis_HeizungE?></strong> x 0,7 = <strong class="brown"><?=$Heizkostenverteiler->Preis_Heizung_70ProzentE?></strong></p>

<!-- 4. -------------------------------------------------------------------------------------------------------- Heizkostenverteiler -->
        <h3>Heizkostenverteiler</h3>
        <p>An jedem Heizkörper ist ein Heizkostenverteiler (HKV) befestigt. Heizkörper haben jedoch unterschiedliche Größen und Eigenschaften. Um die Messwerte der HKV vergleichbar zu machen, werden deren Messwerte wie folgt bereinigt:</p>
        <pre>Bereinigter Messwert = M x Kc x Kq / B </pre>
        <p>Dabei ist:</p>
        <ul>
            <li>M = Rohwert des Heizkostenverteilers</li>
            <li>Kc = Leistung des Heizkörpers in Kilowatt</li>
            <li>Kq = Trägheit des Heizkörpers</li>
            <li>B = Basisempfindlichkeit des HKV, 1,181 bei Engelmann HCA e2 im 1-Fühler-Modus</li>
        </ul>
        <a href="heizkostenverteilerliste.php?y=<?=$Base->Abrechnungsjahr?>">=> Liste Heizkostenverteiler</a>

<!-- 5. ------------------------------------------------------------------------------------------------- Verteilung auf die Wohnungen -->
        <h3>Verteilung auf die Wohnungen</h3>
        <p>Die Summe der jeweils wie s.o. bereinigten HKV-Messwerte des gesamten Hauses im Jahr <?=$Base->Abrechnungsjahr?> betrug: <?=$Base->nf($Heizkostenverteiler->Messergebnis_Haus)?>. 
        Teilt man die Heizkosten durch diese Summe erhält man den Preis pro Wert.</p>

<!-- 6. -------------------------------------------------------------------------------------------------------- 30% Heizkosten nach Wohnfläche -->
<h2>30% nach Wohnfläche</h2>
        <p>30% der Heizkosten: <strong class="orange"><?=$Heizkostenverteiler->Preis_HeizungE?></strong> x 0.3 = <strong class="violet"><?=$Flaechenverteilung->PreisHeizung30ProzentE?></strong></p>
        <p>Ergibt Kosten pro m²: <strong class="violet"><?=$Flaechenverteilung->PreisHeizung30ProzentE?></strong> / <?=$Base->Gesamtwohnflaeche?> m² Gesamtfläche = <?=$Flaechenverteilung->Preis_pro_Quadratmeter?> €</p>

             
        <br/>
        <table>
            <tr><th>Anteil</th><th>Preis</th><th>Aufteilung</th><th>Gesamteinheiten</th><th>Preis pro Einheit</th></tr>
            <tr><td>70%</td><td><strong class="brown"><?=$Heizkostenverteiler->Preis_Heizung_70ProzentE?></strong></td><td>HKV</td><td><?=$Base->nf($Heizkostenverteiler->Messergebnis_Haus)?></td><td><?=$Heizkostenverteiler->Preis_pro_MesswertD?> €</td></tr>
            <tr><td>30%</td><td><strong class="violet"><?=$Flaechenverteilung->PreisHeizung30ProzentE?></strong></td><td>m²</td><td><?=$Base->Gesamtwohnflaeche?></td><td><?=$Flaechenverteilung->Preis_pro_QuadratmeterD?> €</td></tr>
        </table>
        <small>HKV = Heizkostenverteiler (Messgeräte an den Heizkörpern)</small>
        <br/>

<!-- 7. ------------------------------------------------------------------------------------------------------ Heizkosten nach HKV pro Wohnung -->
<br/>
<h2>Heizkosten pro Wohnung nach HKV</h2>
        <table>
            <tr><th>Mieter</th><th>HKV</th><th></th><th>Faktor</th><th></th><th></th><th></th><th>Euro</th></tr>

<?php
$hkvSum = 0;
foreach( $Heizkostenverteiler->getBillReceivers() as $index => $row ) {
    $consumption = $Heizkostenverteiler->getMeteredData( $Base->Abrechnungsjahr, $row['Abrechnungsbeginn'], $row['Abrechnungsende'], $row['Whg_ID']);
    $price = $consumption * $Heizkostenverteiler->Preis_pro_Messwert;
    $hkvSum += $price;
    echo '<tr>
    <td>' . $row['Nachname'] . '</td>
    <td class="alignRight">' . $consumption . '</td>
    <td>x</td>
    <td class="alignRight">' . $Heizkostenverteiler->Preis_pro_MesswertD . '</td>
    <td>=</td>
    <td class="alignRight">' . $Base->euro( $price ) . '</td>
    </tr>'; 

}
?>
            <tr><td colspan="5"></td><td class="alignRight"><strong class="brown"><?=$Base->euro( $hkvSum )?></strong></td></tr>
        </table>

<!-- 8. ------------------------------------------------------------------------------------------------------ Heizkosten nach Wohnfläche pro Wohnung -->
<br/>
<h2>Heizkosten pro Wohnung nach Wohnfläche</h2>
        <table>
            <tr><th>Mieter</th><th>m²</th><th>Faktor</th><th>Euro</th></tr>
<?php
$sqmSum = 0;
foreach( $Heizkostenverteiler->getBillReceivers() as $index => $row ){
    $proportionateCost = $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] );
    $sqmSum += $proportionateCost;
    echo '<tr>';
    echo '<td>' . $row['Nachname'] . '</td>';
    echo '<td>' . $row['qm'] . '</td>';
    echo '<td>x '. $Flaechenverteilung->Preis_pro_QuadratmeterD . ' =</td>';
    echo '<td class="alignRight">' . $Base->euro( $proportionateCost ) . '</td>';
    echo '</tr>';
}
?>
            <tr><td colspan="3"></td><td class="alignRight"><strong class="violet"><?=$Base->euro( $sqmSum )?></strong></td></tr>
        </table>
<!-- 9. ------------------------------------------------------------------------------------------------------- Heizkosten gesamt pro Wohnung -->
<br/>
<h2>Heizkosten gesamt pro Wohnung für <?=$Base->Abrechnungsjahr?></h2>

            <table>
                <tr><th>Mieter</th><th>p.Verbrauch</th><th>p.Fläche</th><th>Summe</th></tr>
<?php
$consumptionCostSum = 0;
$proportionateCostSum = 0;
foreach( $Heizkostenverteiler->getBillReceivers() as $index => $row){
    $consumption = $Heizkostenverteiler->getMeteredData( $Base->Abrechnungsjahr, $row['Abrechnungsbeginn'], $row['Abrechnungsende'], $row['Whg_ID']);
    $consumptionCost = $consumption * $Heizkostenverteiler->Preis_pro_Messwert;
    $proportionateCost = $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] );
    $consumptionCostSum += $consumptionCost;
    $proportionateCostSum += $proportionateCost;
    $totalHeatingCost += $consumptionCost + $proportionateCost;

    echo '<tr>
        <td>' . $row['Nachname'] . '</td>
        <td class="alignRight">' . $Base->euro( $consumptionCost ) . '</td>
        <td class="alignRight">' . $Base->euro( $proportionateCost ) . '</td>
        <td class="alignRight"><strong>' . $Base->euro( $consumptionCost + $proportionateCost ) . '</strong></td>
        </tr>';
}
?>
            <tr><td></td><td class="alignRight"><strong class="brown"><?=$Base->euro($consumptionCostSum)?></strong></td>
            <td class="alignRight"><strong class="violet"><?=$Base->euro($proportionateCostSum)?></strong></td>
            <td class="alignRight"><strong class="orange"><?=$Base->euro($totalHeatingCost)?></strong></td></tr>
        </table>
<!-- 10. -------------------------------------------------------------------------------------------------------------------------- Warmwasserkosten -->
<h2>Warmwasserkosten pro Wohnung</h2>
    <table>
        <tr>
            <th>Preis</th><th>Aufteilung</th><th>Gesamteinheiten</th><th>Preis pro Einheit</th>
        </tr>
        <tr>
            <td><strong class="violet"><?=$Warmwasser->Preis_WarmwasserE?></strong></td>
            <td>m²</td><td><?=$Base->Gesamtwohnflaeche?></td><td><?=$Warmwasser->Preis_Warmwasser_pro_Quadratmeter?></td>
        </tr>
    </table>
    <br/>
    <table>
        <tr><th>Mieter</th><th>Zeitraum</th><th>Fläche</th><th>Kosten</th></tr>
<?php
$totalqm = 0;
$totalww = 0;
foreach( $Heizkostenverteiler->getBillReceivers() as $index => $row){
    $totalqm += $row['qm'];
    $totalww += $row['qm'] * $Warmwasser->Preis_Warmwasser_pro_Quadratmeter;
    echo '<tr>
    <td>' . $row['Nachname'] . '</td>
    <td>' . $row['Abrechnungsbeginn'] . ' - ' . $row['Abrechnungsende'] . '</td>
    <td class="alignRight">' . $row['qm'] . '</td>
    <td class="alignRight">' . $Base->euro( $row['qm'] * $Warmwasser->Preis_Warmwasser_pro_Quadratmeter ) . '</td>
    </tr>';
}
?>
        <tr><td colspan="3"></td><td><?=$Base->euro( $totalww )?></td></tr>
    </table>

<br/>
<!-- 11. -------------------------------------------------------------------------------------------------------- Kohlendioxidkostenverteilungsgesetz -->
<h2>Kohlendioxidkostenaufteilungsgesetz</h2>
<p>CO₂KostAufG</p>
<p>Das <a href="https://www.gesetze-im-internet.de/co2kostaufg/CO2KostAufG.pdf" target="_blank">CO2KostAufG</a> regelt seit 1.1.2023 die Aufteilung der Kosten zwischen Mieter und Vermieter und soll zusätzliche Anreize für Energieeffiezienz schaffen. <a href="https://www.bmwk.de/Redaktion/DE/Artikel/Energie/berechnung-aufteilung-kohlendioxidkosten.html" target="_blank">Leitfaden zur Berechnung BMWK</a></p>
<p>Berechnung:</p>
<pre>   Jährlicher Brennstoffverbrauch (kWh/a) * Emissionsfaktor (kg CO₂/kWh) = Jährlicher Kohlendioxidausstoß kg CO₂/a</pre>
<p>Der Emissionsfaktor für Erdgas beträgt 0,20088 kg CO₂/kWh. Damit ergibt sich für das Jahr <?=$Base->Abrechnungsjahr?>:</p>
<pre>   <?=$Base->Kilowattstunden?> kWh/a x 0,20088 kg CO₂/kWh = <?=$Base->Kilowattstunden * 0.20088?> kg CO₂/a</pre>
<p>Geteilt durch die Gesamtwohnfläche des Hauses ergibt sich ein Wert pro Quadratmeter und Jahr:</p>
<pre>   <?=$Base->Kilowattstunden * 0.20088?> kg CO₂/a : <?=$Base->Gesamtwohnflaeche?> m² = <?=$Base->Kilowattstunden * 0.20088 / $Base->Gesamtwohnflaeche?> kg CO₂/m²/a</pre>
<p>Bei einem denkmalgeschützten und damit sanierungsbeschränkten Altbau gilt für diese Menge eine Aufteilung von 80/20 Mieter/Vermieter.</p>
<p>Der Preis pro Tonne CO₂ betrug in 2023 30 € pro Tonne, also:</p>
<pre>   <?=$Base->Kilowattstunden*0.20088?> : 1000 x 30 = <?=$Base->euro($Base->Kilowattstunden*0.20088/1000*30)?></pre>
<p>20% oder <?=$Base->euro($Base->Kilowattstunden*0.20088/1000*30*0.2)?> davon entfallen auf den Vermieter. Die verbleibenden 80% verteilen sich nach m²:</p>
<p><a href="https://co2kostenaufteilung.bmwk.de" target="_blank">Online-Rechner des BMWK</a></p>


<h2>Energieeffizienz-Richtlinie (EED)</h2>
<p>Die <a href="https://bak.de/politik-und-praxis/klima-energie-und-ressourcen/gesetze-und-richtlinien/eu-gesetzgebung-2/eu-energieeffizienzrichtlinie-eed/" target="_blank">EED</a> verlangt folgende Informationen in der Abrechnung:
<ul>
    <li>Vorjahresverbrauch des Nutzers (klimabereinigt)</li>
    <li>klimabereinigter Vergleich zu einem genormten Durchschnittsnutzer</li>
    <li>Informationen zum Brennstoffmix (zum Beispiel bei Fernwärme)</li>
    <li>der CO2-Gehalt des Brennstoffmixes oder Brennstoffes</li>
    <li>aktuelle Energiepreise, der Gesamt-Energiekosten</li>

    </body>
</html>

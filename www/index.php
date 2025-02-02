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
9.  Kohlendioxidkostenaufteilungsgesetz
10. Zusammenfassung
11. Energieeffizienz Richtlinie
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
$CO2AufG                = new CO2AufG();
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
        <h1>Heizkostenabbrechnung <?=$Base->Abrechnungsjahr?></h1>

        <h3>Gasverbrauch</h3>
            <p>Laut Gasrechnung der <?=$Base->Lieferant?> vom <?=$Base->Rechnungsdatum?> wurden im Jahr <?=$Base->Abrechnungsjahr?>  
            <strong class="red"><?=$Base->KilowattstundenD?></strong> Kilowattstunden Erdgas verbraucht und zum Preis von <strong class="skyblue"><?=$Base->RechnungsbetragE?></strong> abgerechnet.
            Eine Kilowattstunde kostete damit <strong class="yellow"><?=$Base->KilowattstundenpreisE?></strong>.</p>

<!-- 1. --------------------------------------------------------------------------------------------------------- Warmwasser (nach Wohnfläche) -->
        <h2>Wassererwärmung</h2>
        <p>Gemäß  Heizkostenverordnung (<a href="https://www.gesetze-im-internet.de/heizkostenv/" target="_blank">HeizkostenV</a>) müssen die Kosten für die Warmwassererwärmung zunächt abgezogen werden. Nach deren §9 Ziffer 2 ergibt sich der Gasverbrauch bei zentraler Wassererwärmung wie folgt:</p>
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
        <p>Oder mit Verhältnissen gerechnet:</p>
        <pre>    <strong class="green"><?=$Warmwasser->kWh_Gas_fuer_Warmwasser_mit_Brennwertfaktor?></strong> kWh für Warmwasser : <strong class="red"><?=$Base->Kilowattstunden?></strong> kWh Gesamtverbrauch x <strong class="skyblue"><?=$Base->RechnungsbetragE?></strong> = <strong class="pink"><?=$Base->euro($Warmwasser->Preis_Warmwasser_BrunataStyle)?></strong></pre>

<!-- 2. ---------------------------------------------------------------------------------------------------------------------------- Heizung -->
        <h2>Heizung</h2>
        <p>Die Gasrechnung abzüglich der Kosten für die Wassererwärmung ergibt die Heizkosten:</p>
        <table>
            <tr><td colspan="2">Gasrechnung</td><td class="alignRight"><strong class="skyblue"><?=$Base->RechnungsbetragE?></strong></td></tr>
            <tr><td>-</td><td>Wassererwärmung</td><td class="alignRight"><strong class="pink"><?=$Warmwasser->Preis_WarmwasserE?></pink></td></tr>
            <tr><td>=</td><td>Heizkosten</td><td class="alignRight"><strong class="orange"><?=$Heizkostenverteiler->Preis_HeizungE?></strong></td></tr>
        </table>

<!-- 3. -------------------------------------------------------------------------------------------------------- 70% Heizkosten nach Verbrauch -->
        <h3>70% nach Verbrauch</h3>
        <p>50-70% der Heizkosten müssen nach Verbrauch aufgeteilt werden (<a href="https://www.gesetze-im-internet.de/heizkostenv/BJNR002610981.html" target="_blank">HeizkostenV</a> §6 + §8 Absatz 1). 70% belohnt die Sparsamen und ist daher üblich.<p>
        <p>70% der Heizkosten: <strong class="orange"><?=$Heizkostenverteiler->Preis_HeizungE?></strong> x 0,7 = <strong class="brown"><?=$Heizkostenverteiler->Preis_Heizung_70ProzentE?></strong></p>

<!-- 4. -------------------------------------------------------------------------------------------------------- Heizkostenverteiler -->
        <h4>Heizkostenverteiler</h4>
        <p>An jedem Heizkörper ist ein Heizkostenverteiler (HKV) befestigt. Heizkörper haben jedoch unterschiedliche Größen und Eigenschaften. Um die Messungen der HKV vergleichbar zu machen werden die Messwerte deshalb wie folgt bereinigt:</p>
        <pre>Bereinigter Messwert = M x Kc x Kq / B </pre>
        <p>Dabei ist:</p>
        <ul>
            <li>M = Rohwert des Heizkostenverteilers</li>
            <li>Kc = Leistung des Heizkörpers in Kilowatt</li>
            <li>Kq = Trägheit des Heizkörpers</li>
            <li>B = Basisempfindlichkeit des HKV, 2,288 bei Engelmann HCA e2 im 2-Fühler-Modus</li>
        </ul>
        <a href="heizkostenverteilerliste.php?y=<?=$Base->Abrechnungsjahr?>">=> Liste Heizkostenverteiler</a>

<!-- 5. ------------------------------------------------------------------------------------------------- Verteilung auf die Wohnungen -->
        <h4>Verteilung auf die Wohnungen</h4>
        <p>Die Summe der bereinigten HKV-Messwerte im Jahr <?=$Base->Abrechnungsjahr?> betrug: <?=$Base->nf($Heizkostenverteiler->Messergebnis_Haus)?>. 
        Teilt man o.g. 70% der Heizkosten durch diese Summe erhält man den Preis pro Wert. Diesen multipliziert man mit den Gesamtwerten einer Wohnung.</p>

<!-- 6. -------------------------------------------------------------------------------------------------------- 30% Heizkosten nach Wohnfläche -->
        <h3>30% nach Wohnfläche</h3>
        <p>30% der Heizkosten: <strong class="orange"><?=$Heizkostenverteiler->Preis_HeizungE?></strong> x 0.3 = <strong class="violet"><?=$Flaechenverteilung->PreisHeizung30ProzentE?></strong></p>
        <p>Ergibt Kosten pro m²: <strong class="violet"><?=$Flaechenverteilung->PreisHeizung30ProzentE?></strong> / <?=$Base->Gesamtwohnflaeche?> m² Gesamtfläche = <?=$Flaechenverteilung->Preis_pro_Quadratmeter?> €</p>
        <h2>Zusammenfassung Heizkosten</h2>
        <table>
            <tr><th>Anteil</th><th>Preis</th><th>Aufteilung</th><th>Gesamteinheiten</th><th>Preis pro Einheit</th></tr>
            <tr><td>70%</td><td><strong class="brown"><?=$Heizkostenverteiler->Preis_Heizung_70ProzentE?></strong></td><td>HKV</td><td><?=$Base->nf($Heizkostenverteiler->Messergebnis_Haus)?></td><td><?=$Heizkostenverteiler->Preis_pro_MesswertD?> €</td></tr>
            <tr><td>30%</td><td><strong class="violet"><?=$Flaechenverteilung->PreisHeizung30ProzentE?></strong></td><td>m²</td><td><?=$Base->Gesamtwohnflaeche?></td><td><?=$Flaechenverteilung->Preis_pro_QuadratmeterD?> €</td></tr>
        </table>
        <br/>

<!-- 7. ------------------------------------------------------------------------------------------------------ Heizkosten nach HKV pro Wohnung -->
<!--
        <br/>
        <h2>Heizkosten nach HKV</h2>
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
->
<!-- 8. ------------------------------------------------------------------------------------------------------ Heizkosten nach Wohnfläche pro Wohnung -->
<!--
<br/>
<h2>Heizkosten nach Wohnfläche</h2>
        <table>
            <tr><th>Mieter</th><th>Zeitraum</th><th>Tage</th><th>m²</th><th>Faktor</th><th>Euro</th></tr>
<?php
$sqmSum = 0;
foreach( $Heizkostenverteiler->getBillReceivers() as $index => $row ){
    $proportionateCost = $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] );
    $sqmSum += $proportionateCost;
    echo '<tr>';
    echo '<td>' . $row['Nachname'] . '</td>';
    echo '<td>' . $row['Abrechnungsbeginn'] . ' - ' . $row['Abrechnungsende'] . '</td>';
    echo '<td>' . $CO2AufG->ComputeDays($row['Abrechnungsbeginn'], $row['Abrechnungsende']) . ' </td>';
    echo '<td>' . $row['qm'] . '</td>';
    echo '<td>x '. $Flaechenverteilung->Preis_pro_QuadratmeterD . ' =</td>';
    echo '<td class="alignRight">' . $Base->euro( $proportionateCost ) . '</td>';
    echo '</tr>';
}
?>
            <tr><td colspan="5"></td><td class="alignRight"><strong class="violet"><?=$Base->euro( $sqmSum )?></strong></td></tr>
        </table>
</br>
-->
<!-- 9. -------------------------------------------------------------------------------------------------------- Kohlendioxidkostenverteilungsgesetz -->
<h1>Kohlendioxidkostenaufteilungsgesetz / CO₂KostAufG</h1>
    <p>Energieversorger müssen nach dem 2021 in Kraft getretenen Bundesemissionshandelsgesetz (BEHG) für den von ihnen gelieferten Brennstoff CO₂-Gebühren zahlen 
        und stellen diese ihren Kunden in Rechnung. Bisher zahlte folglich der Mieter die vollen CO₂-Kosten.</p>
    <p>Das <a href="https://www.gesetze-im-internet.de/co2kostaufg/CO2KostAufG.pdf" target="_blank">CO2KostAufG</a> regelt seit 1.1.2023 die Aufteilung dieser 
    Kosten zwischen Mieter und Vermieter. Der mit steigendem CO₂-Austoß größer werdende Vermieteranteil soll zusätzliche Anreize für Energieeffizienzmaßnahmen 
    schaffen.</p>
    <p>Der CO₂-Preis wird Energieverbrauch ermittelt, nominell nach Wohnfläche verteilt und der Vermieteranteil dem Mieter erlassen.</p>
    [<a href="https://www.bmwk.de/Redaktion/DE/Artikel/Energie/berechnung-aufteilung-kohlendioxidkosten.html" target="_blank">Leitfaden zur Berechnung BMWK</a>] [<a href="https://co2kostenaufteilung.bmwk.de" target="_blank">Online-Rechner des BMWK</a>]</p>
    <p>Berechnung:</p>
    
    <pre>   Jährlicher Brennstoffverbrauch (kWh/a) * Emissionsfaktor (kg CO₂/kWh) = Jährlicher Kohlendioxidausstoß kg CO₂/a</pre>
    
    <p>Der Emissionsfaktor für Erdgas beträgt <?=$CO2AufG->Emissionsfaktor()?> kg CO₂/kWh. Damit ergibt sich für das Jahr <?=$Base->Abrechnungsjahr?>:</p>
    
    <pre>   <strong class="red"><?=$Base->Kilowattstunden?></strong> kWh/a x 0,20088 kg CO₂/kWh = <strong class="yellowgreen"><?=$CO2AufG->Emission()?></strong> kg CO₂/a</pre>
    
    <p>Geteilt durch die Gesamtwohnfläche des Hauses ergibt sich ein Wert pro Quadratmeter und Jahr:</p>
    
    <pre>   <strong class="yellowgreen"><?=$CO2AufG->Emission()?></strong> kg CO₂/a : <?=$Base->Gesamtwohnflaeche?> m² = <?=$CO2AufG->co2proQm()?> kg CO₂/m²/a</pre>
    
    <p>Der Preis pro Tonne CO₂ betrug in 2023 <?=$CO2AufG->Kohlendioxydpreis()?> € pro Tonne oder <?=$CO2AufG->Kohlendioxydpreis(true)?> € pro kg. Es ergibt sich ergo ein Gesamtpreis:</p>
    
    <pre>   <strong class="yellowgreen"><?=$CO2AufG->Emission()?></strong> kg CO₂/m²/a x <?=$CO2AufG->Kohlendioxydpreis(true)?> € = <?=$Base->euro($CO2AufG->Emissionspreis())?></pre>
    
    <p>Bei einem denkmalgeschützten und damit sanierungsbeschränkten Altbau gilt für diese Menge eine Aufteilung von <?=$CO2AufG->Verteilung()?> für den 
    Mieter/Vermieter. Der Vermieteranteil beträgt also <?=$Base->euro($CO2AufG->Vermieterkosten())?> und wird den Mietern anteilig nach Wohnfläche erlassen.</p>
    
    <pre>   <?=$Base->euro($CO2AufG->Vermieterkosten())?> : <?=$Base->Gesamtwohnflaeche?> m² = <?=$Base->euro($CO2AufG->co2proQmLandlord())?>/m²/a CO₂Kosten für den Vermieter</pre>
    <pre>   <?=$Base->euro($CO2AufG->Mieterkosten())?> : <?=$Base->Gesamtwohnflaeche?> m² = <?=$Base->euro($CO2AufG->co2proQmTenant())?>/m²/a CO₂Kosten für den Mieter</pre>

    <h2>Zusammenfassung CO₂</h2>
    <p>Heizkosten, Wassererwärmung, CO₂-Emission</p>
    <table>
        <tr><th>Kosten</th><th>Preis</th><th>Aufteilung</th><th>Gesamteinheiten</th><th>Preis pro Einheit</th></tr>
        <tr><td>70% Heizung</td><td class="alignRight"><strong class="brown"><?=$Heizkostenverteiler->Preis_Heizung_70ProzentE?></strong></td><td class="center">HKV</td><td><?=$Base->nf($Heizkostenverteiler->Messergebnis_Haus)?></td><td><?=$Heizkostenverteiler->Preis_pro_MesswertD?></td><td>€/HKV-Wert</td></tr>
        <tr><td>+ 30% Heizung</td><td class="alignRight"><strong class="violet"><?=$Flaechenverteilung->PreisHeizung30ProzentE?></strong></td><td class="center">m²</td><td><?=$Base->Gesamtwohnflaeche?></td><td><?=$Flaechenverteilung->Preis_pro_QuadratmeterD?></td><td>€/m²/a</td></tr>
        <tr><td>+ Warmwasser</td><td class="alignRight"><strong class="pink"><?=$Warmwasser->Preis_WarmwasserE?></strong></td><td class="center">m²</td><td><?=$Base->Gesamtwohnflaeche?></td><td><?=$Warmwasser->Preis_Warmwasser_pro_Quadratmeter?></td><td>€/m²/a</td></tr>
        <?php
                $heating7030Water = $Heizkostenverteiler->Preis_Heizung_70Prozent + $Flaechenverteilung->PreisHeizung30Prozent + $Warmwasser->Preis_Warmwasser;
                ?>
            <tr><td>= Gasrechnung</td><td class="alignRight"><strong class="skyblue"><?=$Base->euro($heating7030Water)?></strong></td><td colspan="4"></td></tr>
            <tr class="annotation"><td>davon CO₂-Kosten</td><td class="alignRight"><?=$Base->euro($CO2AufG->Emissionspreis())?></td><td class="center">m²</td><td><?=$CO2AufG->EmissionTons()?> t</td><td colspan="2"></td></tr>
            <tr><td>- CO₂-Vermieter</td><td class="alignRight"><strong><?=$Base->euro($CO2AufG->Vermieterkosten())?></strong></td><td class="center">m²</td><td><?=$Base->Gesamtwohnflaeche?></td><td><?=$Base->euro($CO2AufG->co2proQmLandlord())?></td><td>€/m²/a</td></tr>
            <?php
                $heatingMinusLandlordCo2 = $heating7030Water - $CO2AufG->Vermieterkosten();
                ?>
            <tr><td>= Gas Mieter</td><td class="alignRight"><strong><?=$Base->euro($heatingMinusLandlordCo2)?></strong></td><td colspan="4"></td></tr>
            <tr class="annotation"><td>CO₂-Mieter</td><td class="alignRight"><strong><?=$Base->euro($CO2AufG->Mieterkosten())?></strong></td><td class="center">m²</td><td><?=$Base->Gesamtwohnflaeche?></td><td><?=$Base->euro($CO2AufG->co2proQmTenant())?></td><td>€/m²/a</td></tr>
        </table>
        <small>HKV = Heizkostenverteiler (Messgeräte an den Heizkörpern)</small>
        <br/>
    </table>
    <br/>
<!-- 10. -------------------------------------------------------------------------------------------------------- Zusammenfassung -->
<h1>Gesamtübersicht</h1>
<table>
    <tr><th>Mieter</th><th>Zeitraum</th><th>Tage</th><th>HKV-Werte</th><th>70%</th><th>Fläche</th><th>30%</th><th>WW</th><th>Gesamt</th><th>CO2</th>
    <th>CO₂ Vermieter</th><th>Netto</th><th>CO₂ Mieter</th></tr>
    <tr class="subline"><td colspan="3"></td><td class="center">bereinigt um Kc und Kq</td><td class="center">per HKV</td><td class="center">m²</td><td class="center">per m²</td>
    <td class="center">per m²</td><td class="center">Heizung + WW</td><td class="center">Tonnen</td><td class="center">abzüglich</td><td class="center">Heat - Carbon</td>
    <td class="center">zur Info</td></tr>
<?php
$totalHeatConsumptionCost = 0;
$totalHeatProportionateCost = 0;
$totalHotWaterCost = 0;
$totalHeatCost = 0;
$totalCarbon = 0;
$totalCo2TenantCost = 0;
$totalCo2LandlordCost = 0;
$totalNetSum = 0;
foreach( $Heizkostenverteiler->getBillreceivers() as $index => $row){
    # heat
    $consumption = $Heizkostenverteiler->getMeteredData( $Base->Abrechnungsjahr, $row['Abrechnungsbeginn'], $row['Abrechnungsende'], $row['Whg_ID']);
    $consumptionCost = $consumption * $Heizkostenverteiler->Preis_pro_Messwert;
    $proportionateCost = $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] );
    $hotWaterCost = $Warmwasser->preis_pro_wohnung( $row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende']);
    # carbon
    $carbonPerTenant = $CO2AufG->carbonPerTenant( $row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] );
    $co2TenantCost = $CO2AufG->costPerTenant( $row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'], false );
    $co2TenantCostE = $CO2AufG->costPerTenant( $row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'], true );
    $co2LandlordCost = $CO2AufG->landlordCostPerTenant( $row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'], false );
    $co2LandlordCostE = $CO2AufG->landlordCostPerTenant( $row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'], true );
    # total carbon
    $totalCarbon += $carbonPerTenant;
    # totals on right
    $totalHeatCostPerTenant = $consumptionCost + $proportionateCost + $hotWaterCost;
    $totalHeatCostPerTenantMinusCarbon = $totalHeatCostPerTenant - $co2LandlordCost;
    # totals on bottom
    $totalHeatConsumptionCost += $consumptionCost;
    $totalHeatProportionateCost += $proportionateCost;
    $totalHotWaterCost += $hotWaterCost;
    $totalHeatCost += $totalHeatCostPerTenant;
    $totalCo2LandlordCost += $co2LandlordCost;
    $totalNetSum += $totalHeatCostPerTenantMinusCarbon;
    $totalCo2TenantCost += $co2TenantCost;
    echo '<tr>
    <td>' . $row['Nachname'] . '</td>
    <td nowrap>' . $Base->formatDate($row['Abrechnungsbeginn']) . ' - ' . $Base->formatDate($row['Abrechnungsende']) . '</td>
    <td class="alignRight">' . $CO2AufG->ComputeDays( $row['Abrechnungsbeginn'], $row['Abrechnungsende'] ) . '</td>
    <td class="alignRight">' . $consumption . '</td>
    <td class="alignRight">' . $Base->euro( $consumptionCost ) . '</td>
    <td class="alignRight"><i>' . $row['qm'] . '</i></td>
    <td class="alignRight">' . $Base->euro( $proportionateCost ) . '</td>
    <td class="alignRight">' . $Base->euro( $hotWaterCost ) . '</td>
    <td class="alignRight">' . $Base->euro( $totalHeatCostPerTenant ) . '</td>
    <td class="alignRight" nowrap><i>' . number_format($carbonPerTenant, 3, ',', '.') . ' t</i></td>
    <td class="alignRight">' . $co2LandlordCostE . ' </td>
    <td class="alignRight"><strong>' . $Base->euro( $totalHeatCostPerTenantMinusCarbon ) . '</strong></td>
    <td class="alignRight">' . $co2TenantCostE . '</td>
    </tr>';
}
?>
    <tr><td colspan="4"></td>
        <td class="alignRight"><strong class="brown"><?=$Base->euro($totalHeatConsumptionCost)?></strong></td>
        <td></td>
        <td class="alignRight"><strong class="violet"><?=$Base->euro($totalHeatProportionateCost)?></strong></td>
        <td class="alignRight"><strong class="pink"><?=$Base->euro($totalHotWaterCost)?></strong></td>
        <td class="alignRight"><strong><?=$Base->euro($totalHeatCost)?></strong></td>
        <td><i><?=number_format($totalCarbon, 3, ',', '.')?></i></td>
        <td class="alignRight"><strong class="gray"><?=$Base->euro($totalCo2LandlordCost)?></strong></td>
        <td><strong><?=$Base->euro($totalNetSum)?></strong></td>
        <td><strong><?=$Base->euro($totalCo2TenantCost)?></strong></td>
    </tr> 
</table>
<br/>
<!-- 11. -------------------------------------------------------------------------------------------------------- Erneuerbare Energien Gesetz -->
<h2>Energieeffizienz-Richtlinie (EED)</h2>
<p>Die <a href="https://www.bfee-online.de/BfEE/DE/Effizienzpolitik/EuropaeischeEnergieeffizienzpolitik/europaeischeenergieeffizienzpolitik.html" target="_blank">EED</a> verlangt folgende Informationen in der Abrechnung:
<ul>
    <li>Vorjahresverbrauch des Nutzers (klimabereinigt)</li>
    <li>klimabereinigter Vergleich zu einem genormten Durchschnittsnutzer</li>
    <li>Informationen zum Brennstoffmix (zum Beispiel bei Fernwärme)</li>
    <li>der CO2-Gehalt des Brennstoffmixes oder Brennstoffes</li>
    <li>aktuelle Energiepreise, der Gesamt-Energiekosten</li>

    </body>
</html>

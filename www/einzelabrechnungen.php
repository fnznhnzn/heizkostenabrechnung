<?php
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
$CO2AufG                = new CO2AufG( $Base->Kilowattstunden );
?>
<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="utf-8"/>
        <title>Energierechnung</title>
        <link rel="stylesheet" href="css/print.css"/>
        <script>
            if(navigator.userAgent.toLowerCase().includes('edg')){ alert('Please print me in Chrome'); }
        </script>
    </head>
    <body>
<!-- begin "hyper" loop ---------------------------------------------------------------------------------------------- -->
<?php foreach($Base->getBillReceivers() as $index => $row){ 
    # water
    $hotWater = $Warmwasser->preis_pro_Wohnung($row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende']);
    
    # heat
    $tenantsConsumption = $Heizkostenverteiler->getMeteredData( $Base->Abrechnungsjahr, $row['Abrechnungsbeginn'], $row['Abrechnungsende'],  $row['Whg_ID'] );
    $heatProportionate = $Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption;
    $heatBySquareMeter = $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] );
    
    # carbon
    $carbonPerTenant = $CO2AufG->carbonPerTenant( $row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] );
    $co2LandlordCost = $CO2AufG->landlordCostPerTenant( $row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'], false );

    # totals
    $totalHeat = $heatProportionate + $heatBySquareMeter;
    $total = $hotWater + $heatProportionate + $heatBySquareMeter - $co2LandlordCost;
    
    # formatted
    $hotWaterE = $Warmwasser->preis_pro_WohnungE($row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende']);
    $heatProportionateE = $Base->euro($Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption);
    $heatBySquareMeterE = $Base->euro($Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] ));
    $co2LandlordCostE = $CO2AufG->landlordCostPerTenant( $row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'], true );
    $totalE = $Base->euro($total);
?>

<h1>Energierechnung für <?=$Base->Abrechnungsjahr?></h1> 
<h2><?=$row['Etage']?> <?=$row['Lage']?> - <?=$row['Nachname']?>, <?=$row['Vorname']?></h2>
<p>Abrechnungszeitraum: <?=$Base->formatDate($row['Abrechnungsbeginn'])?> - <?=$Base->formatDate($row['Abrechnungsende'])?> 
(<?=$Base->ComputeDays($row['Abrechnungsbeginn'], $row['Abrechnungsende'])?> Tage)</p>

<!-- Brennstoff ------------------------------------------------------------------------------------------------ -->
    <h3>Brennstoff</h3>
    <p><?=$Base->KilowattstundenD?> Kilowattstunden Erdgas, <?=$Base->RechnungsbetragE?><br/></p>

<!-- Wassererwärmung ------------------------------------------------------------------------------------------- -->
    <h3>Wassererwärmung</h3>
    <p>Die Erwärmung von <?=$Warmwasser::WARMWASSERKUBIKMETER?> Kubikmetern Wasser verbrauchte <?=$Warmwasser->kWh_Gas_fuer_WarmwasserD?> 
    Kilowattstunden Gas zu <?=$Warmwasser->Preis_WarmwasserE?>. Verteilt auf die Gesamtfläche von <?=$Base->GesamtwohnflaecheD?> m² 
    entspricht dies <?=$Warmwasser->Preis_Warmwasser_pro_QuadratmeterD?> € pro m². Auf <?=number_format(floatval($row['qm']),2,',','.')?> m² 
    entfallen somit <strong><?=$hotWaterE?></strong>.</p>

<!-- Heizung --------------------------------------------------------------------------------------------------- -->
    <h3>Heizung</h3>
    <p><?=$Heizkostenverteiler->Preis_HeizungE?> wurden gem. HeizkostenV zu 70% nach Verbrauch und zu 30% nach Fläche verteilt. Die Verbrauchswerte 
    werden mit Heizkostenverteilern ermittelt und jeweils um Leistung und Trägheit der Heizkörper bereinigt.</p>
    
    <p>70% oder <?=$Heizkostenverteiler->Preis_Heizung_70ProzentE?> geteilt durch die Summe der Messwerte von <?=$Heizkostenverteiler->Messergebnis_HausD?> ergibt 
    <?=$Heizkostenverteiler->Preis_pro_MesswertD?> € pro Messwert. <?=number_format(floatval($tenantsConsumption), 2,',','.')?> in der Wohnung gemessenen Werte 
    entsprechen <strong><?=$Base->euro($Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption)?></strong>.</p>

    <p>30% oder <?=$Flaechenverteilung->PreisHeizung30ProzentE?> geteilt durch die Gesamtfläche ergibt <?=$Flaechenverteilung->Preis_pro_QuadratmeterD?> € 
    pro Quadratmeter. <?=number_format(floatval($row['qm']),2,',','.')?> m² Wohnfläche entsprechen <strong><?=$heatBySquareMeterE?></strong></p>

    <p>Verhältnis Heizung/Wassererwärmung: <?=$Base->percentage($hotWater,$totalHeat)[0]?>% zu <?=$Base->percentage($hotWater,$totalHeat)[1]?>%</p>

<!-- CO2 ------------------------------------------------------------------------------------------------------- -->
    <h3>Kohlendioxid</h3>
    <p>Mit dem am 1.1.2023 inkraftgetretenen CO₂KostAufG übernimmt der Vermieter einen Teil der im Brennstoffpreis enthaltenen CO₂-Kosten.</p>
    <p><?=$Base->KilowattstundenD?> kWh verbranntes Erdgas emittierten <?=$CO2AufG->EmissionTons()?> Tonnen CO₂. 
    Auf <?=number_format(floatval($row['qm']),2,',','.')?> m² entfallen <?=number_format($carbonPerTenant,3,',','.')?> Tonnen.
    Der Vermieteranteil der Emissionsabgabe beträgt <strong><?=$co2LandlordCostE?></strong> und wird erstattet.</p>
 
<!-- Zusammenfassung -------------------------------------------------------------------------------------------- -->
    <h3>Zusammenfassung</h3>
    <table>
        <tr><td></td><td>Wassererwärmung</td><td align="right"><?=$hotWaterE?></td></tr>
        <tr><td>+</td><td>Heizung 70%</td><td align="right"><?=$heatProportionateE?></td></tr>
        <tr><td>+</td><td>Heizung 30%</td><td align="right"><?=$heatBySquareMeterE?></td></tr>
        <tr><td>-</td><td>Vermieteranteil CO₂</td><td align="right"><?=$co2LandlordCostE?></td></tr>
        <tr><td>=</td><td><strong>Gesamt</strong></td><td align="right"><strong><?=$totalE?></strong></td></tr>
    </table>
    <div class="page_break"></div>
<br/>
<!-- end loop ---------------------------------------------------------------------------------------------------- -->
<?php } ?>

</body>
</html>

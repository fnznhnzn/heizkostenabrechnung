<?php
# to do:
# - Warmwasser nach Köpfen? NO! (vgl. §8 HeizkostenV)
# - Verhältnis Warmwasser / Heizung in Prozent
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
        <title>Einzelabrechnungen</title>
        <link rel="stylesheet" href="css/print.css"/>
        <script>
            if(navigator.userAgent.toLowerCase().includes('edg')){ alert('Please print me in Chrome'); }
        </script>
    </head>
    <body>
<!-- begin "hyper" loop ---------------------------------------------------------------------------------------------- -->
<?php foreach($Base->getBillReceivers() as $index => $row){ 
    $tenantsConsumption = $Heizkostenverteiler->getMeteredData( $Base->Abrechnungsjahr, $row['Abrechnungsbeginn'], $row['Abrechnungsende'],  $row['Whg_ID'] );
    $carbonPerTenant = $CO2AufG->carbonPerTenant( $row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] );

    # raw values
    $hotWater = $Warmwasser->preis_pro_Wohnung($row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende']);
    $heatProportionate = $Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption;
    $heatBySquareMeter = $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] );
    $co2LandlordCost = $CO2AufG->landlordCostPerTenant( $row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'], false );
    
    # formatted values
    $hotWaterE = $Warmwasser->preis_pro_WohnungE($row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende']);
    $heatProportionateE = $Base->euro($Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption);
    $heatBySquareMeterE = $Base->euro($Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] ));
    $co2LandlordCostE = $CO2AufG->landlordCostPerTenant( $row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'], true );

    $total = $hotWater + $heatProportionate + $heatBySquareMeter - $co2LandlordCost;
    $totalE = $Base->euro($total);
?>

<h1>Energiekostenabrechnung für <?=$Base->Abrechnungsjahr?></h1> 
<h2><?=$row['Etage']?> <?=$row['Lage']?> - <?=$row['Nachname']?>, <?=$row['Vorname']?></h2>
<p>Abrechnungszeitraum: <?=$Base->formatDate($row['Abrechnungsbeginn'])?> - <?=$Base->formatDate($row['Abrechnungsende'])?></p>

    <h3>Energiekosten</h3>
    <p><?=$Base->KilowattstundenD?> Kilowattstunden Erdgas kosteten <i><?=$Base->RechnungsbetragE?></i><br/></p>

    <h3>Warmwasser</h3>
    <p>Der Anteil am Gasverbrauch zur Erwärmung von insgesamt <?=$Warmwasser::WARMWASSERKUBIKMETER?> Kubikmetern in <?=$Base->Abrechnungsjahr?> verbrauchten Wassers beträgt nach HeizkostenV <?=$Warmwasser->kWh_Gas_fuer_WarmwasserD?> Kilowattstunden bzw. <?=$Warmwasser->Preis_WarmwasserE?>.</p>
    <p>Verteilt auf eine Gesamtfläche von <?=$Base->GesamtwohnflaecheD?> m² entspricht dies <?=$Warmwasser->Preis_Warmwasser_pro_QuadratmeterD?> € pro m².</p>
    <p>Mit einer Fläche von <?=number_format(floatval($row['qm']),2,',','.')?> m² entfallen <strong><?=$hotWaterE?></strong> auf diese Wohnung.</p>

    <h3>Heizung</h3>
    <p>Die verbleibenden Heizkosten von <?=$Heizkostenverteiler->Preis_HeizungE?> wurden gem. HeizkostenV zu 70% nach Verbrauch und zu 30% nach Fläche aufgeteilt. Die Verbrauchswerte werden, rechnerisch um Leistung und Trägheit des jeweiligen Heizkörpers bereinigt mit daran befestigten Heizkostenverteilern ermittelt.</p>
    <p>70% oder <?=$Heizkostenverteiler->Preis_Heizung_70ProzentE?> geteilt durch die Gesamtsumme der Messwerte (<?=$Heizkostenverteiler->Messergebnis_HausD?>) ergibt <?=$Heizkostenverteiler->Preis_pro_MesswertD?> € pro Wert. 
    <?=number_format(floatval($tenantsConsumption), 12,',','.')?> in der Wohnung gemessenen Werte ergeben <strong><?=$Base->euro($Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption)?></strong>.</p>

    <p>30% oder <?=$Flaechenverteilung->PreisHeizung30ProzentE?> geteilt durch die gesamte Wohnfläche des Hauses von <?=$Base->GesamtwohnflaecheD?> m² ergibt den Preis 
    von <?=$Flaechenverteilung->Preis_pro_QuadratmeterD?> € pro Quadratmeter. Dieser mal <?=number_format(floatval($row['qm']),2,',','.')?> m² Wohnfläche 
    an <?=$Flaechenverteilung->getDaysStayed($row['Abrechnungsbeginn'], $row['Abrechnungsende'])?> Tagen (<?=$row['Abrechnungsbeginn']?> bis <?=$row['Abrechnungsende']?>) 
    macht <strong><?=$heatBySquareMeterE?></strong></p>

    <?php
        $ww = $Warmwasser->preis_pro_Wohnung($row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende']);
        $hk = $Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption;
        $hk += $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] );
    ?>

    <p>Das Verhältnis von Heizung zu Wassererwärmung entspricht dabei <?=$Base->percentage($ww,$hk)[0]?>% zu <?=$Base->percentage($ww,$hk)[1]?>%</p>

    <h3>Kohlendioxidkostenaufteilungsgesetz (CO₂KostAufG)</h3>
    <p>Mit dem am 1.1.2023 inkraft getretenen CO₂KostAufG übernimmt der Vermieter einen Teil der im Energiepreis enthaltenen CO₂-Abgabe.</p>
    <p><?=$Base->KilowattstundenD?> kWh verbranntes Erdgas emittierten <?=$CO2AufG->EmissionTons()?> Tonnen CO₂ in die Erdatmosphäre. 
    Auf diese Wohnung entfallen davon <?=number_format($carbonPerTenant,3,',','.')?> Tonnen. <strong><?=$co2LandlordCostE?></strong> der Emissionsabgabe werden übernommen.</p>
    <h3>Zusammenfassung</h3>
    <table>
        <tr><td></td><td>Wassererwärmung</td><td align="right"><?=$hotWaterE?></td></tr>
        <tr><td>+</td><td>Heizung 70%</td><td align="right"><?=$heatProportionateE?></td></tr>
        <tr><td>+</td><td>Heizung 30%</td><td align="right"><?=$heatBySquareMeterE?></td></tr>
        <tr><td>-</td><td>Vermieteranteil CO2</td><td align="right"><?=$co2LandlordCostE?></td></tr>
        <tr><td>=</td><td><strong>Gesamt</strong></td><td align="right"><strong><?=$totalE?></strong></td></tr>
    </table>
    <div class="page_break"></div>
<br/>
<!-- end loop ------------------------------------------------------------------------------------------------ -->
<?php } ?>

</body>
</html>

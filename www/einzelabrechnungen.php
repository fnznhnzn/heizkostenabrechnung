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

$Base                   = new Base();
$Warmwasser             = new Warmwasser();
$Heizkostenverteiler    = new Heizkostenverteiler( $Warmwasser->Preis_Warmwasser );
$Flaechenverteilung     = new Flaechenverteilung( $Heizkostenverteiler->Preis_Heizung );
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
?>

<h1>Energiekostenabrechnung für <?=$Base->Abrechnungsjahr?></h1> 
<h2><?=$row['Etage']?> <?=$row['Lage']?> - <?=$row['Nachname']?>, <?=$row['Vorname']?></h2>
<p>Abrechnungszeitraum: <?=$Base->formatDate($row['Abrechnungsbeginn'])?> - <?=$Base->formatDate($row['Abrechnungsende'])?></p>

    <h3>Energiekosten</h3>
    <p><?=$Base->KilowattstundenD?> Kilowattstunden Erdgas kosteten <?=$Base->Abrechnungsjahr?> <i><?=$Base->RechnungsbetragE?></i><br/>
    Dies entspricht <?=$Base->KilowattstundenpreisE?> pro Kilowattstunde.</p>

    <h3>Warmwasser</h3>
    <p>Der Anteil am Gasverbrauch zur Erwärmung von insgesamt <?=$Warmwasser::WARMWASSERKUBIKMETER?> Kubikmetern in <?=$Base->Abrechnungsjahr?> verbrauchten Wassers beträgt nach HeizkostenV <?=$Warmwasser->kWh_Gas_fuer_WarmwasserD?> Kilowattstunden bzw. <?=$Warmwasser->Preis_WarmwasserE?>.</p>
    <p>Verteilt auf eine Gesamtfläche von <?=$Base->GesamtwohnflaecheD?> m² entspricht dies <?=$Warmwasser->Preis_Warmwasser_pro_QuadratmeterD?> € pro m².</p>
    <p>Mit einer Fläche von <?=number_format(floatval($row['qm']),2,',','.')?> m² entfallen davon somit <?=$Warmwasser->preis_pro_WohnungE($row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'])?> auf diese Wohnung.</p>

    <h3>Heizung</h3>
    <p><i><?=$Base->RechnungsbetragE?></i> Gasrechnung abzüglich <?=$Warmwasser->Preis_WarmwasserE?> Warmwasser = <?=$Heizkostenverteiler->Preis_HeizungE?> Heizkosten. Aufteilung lt. HeizkostenV: 70% per Verbrauch, 30% per Fläche.</p>

    <p>70% der Heizkosten sind <?=$Heizkostenverteiler->Preis_Heizung_70ProzentE?>. Geteilt durch die Gesamtsumme der Messwerte (<?=$Heizkostenverteiler->Messergebnis_HausD?>) ergibt das einen Preis pro Messwert von <?=$Heizkostenverteiler->Preis_pro_MesswertD?> €.</p> 
    <p>Letzterer multipliziert mit <?=number_format(floatval($tenantsConsumption), 12,',','.')?>
    in der Wohnung gemessenen Werten ergibt <?=number_format($Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption,12,',','.')?> € verbrauchsmäßig der Wohnung zugeordnete Heizkosten.</p>

    <p>30% der Heizkosten sind <?=$Flaechenverteilung->PreisHeizung30ProzentE?>. Geteilt durch die gesamte Wohnfläche des Hauses von <?=$Base->GesamtwohnflaecheD?> m² ergibt das einen Preis von <?=$Flaechenverteilung->Preis_pro_QuadratmeterD?> € pro Quadratmeter. Dieser mal <?=number_format(floatval($row['qm']),2,',','.')?> m² Wohnfläche an <?=$Flaechenverteilung->getDaysStayed($row['Abrechnungsbeginn'], $row['Abrechnungsende'])?> Tagen von <?=$row['Abrechnungsbeginn']?> bis <?=$row['Abrechnungsende']?> ergibt <?=number_format($Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] ),12,',','.')?> €</p>

    <p>Die Heizkosten betragen somit <?=$Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption?> + <?= $proportionateCost = $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] );?> = <?=$Base->euro( ($Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption) + $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] ))?></p>

    <p>Warmwasser 
    <?=$Warmwasser->preis_pro_WohnungE($row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'])?>
    + Heizung 
    <?=$Base->euro( ($Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption) + $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] ))?> 
    = 
    <strong><?=$Base->euro($Warmwasser->preis_pro_Wohnung($row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende']) + ($Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption) + $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende']))?></strong></p>

    <?php
        $ww = $Warmwasser->preis_pro_Wohnung($row['qm'], $row['Abrechnungsbeginn'], $row['Abrechnungsende']);
        $hk = $Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption;
        $hk += $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] );
    ?>

    <p>Das Verhältnis von Warmwasser- zu Heizkosten ist <?=$Base->percentage($ww,$hk)[0]?>% zu <?=$Base->percentage($ww,$hk)[1]?>%</p>
<div class="page_break"></div>
<br/>
<!-- end loop ------------------------------------------------------------------------------------------------ -->
<?php } ?>

</body>
</html>

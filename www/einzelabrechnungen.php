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
    </head>
    <body>
<!-- begin hyperloop ---------------------------------------------------------------------------------------------- -->
<?php foreach($Base->getBillReceivers() as $index => $row){ 
    $tenantsConsumption = $Heizkostenverteiler->getMeteredData( $Base->Abrechnungsjahr, $row['Abrechnungsbeginn'], $row['Abrechnungsende'],  $row['Whg_ID'] );
?>

<h1>Energiekostenabrechnung für <?=$Base->Abrechnungsjahr?></h1> 
<h2><?=$row['Etage']?> <?=$row['Lage']?> - <?=$row['Nachname']?>, <?=$row['Vorname']?></h2>
<p>Abrechnungszeitraum: <?=$Base->formatDate($row['Abrechnungsbeginn'])?> - <?=$Base->formatDate($row['Abrechnungsende'])?></p>

    <h3>Energiekosten</h3>
    <p><?=$Base->Kilowattstunden?> Kilowattstunden Erdgas kosteten <?=$Base->Abrechnungsjahr?> <?=$Base->RechnungsbetragE?>, also <?=$Base->KilowattstundenpreisE?> pro Kilowattstunde.</p>

    <h3>Warmwasser</h3>
    <p>Der Gasverbrauch zur Erwärmung von insgesamt <?=$Warmwasser::WARMWASSERKUBIKMETER?> Kubikmetern in <?=$Base->Abrechnungsjahr?> verbrauchtem Wasser beträgt berechnet nach HeizkostenV <strong><?=$Warmwasser->kWh_Gas_fuer_Warmwasser?></strong> Kilowattstunden. Damit ergeben sich Energiekosten von <strong><?=$Warmwasser->Preis_WarmwasserE?></strong>, was bei einer Gesamtfläche von <?=$Base->Gesamtwohnflaeche?> Quadratmetern Kosten von <?=$Warmwasser->Preis_Warmwasser_pro_Quadratmeter?> € pro m² entspricht.</p>
    <p>Mit einer Fläche von <?=$row['qm']?> m² entfallen davon somit <strong><?=$Warmwasser->preis_pro_WohnungE($row['qm'])?></strong> auf diese Wohnung.</p>

    <h3>Heizung</h3>
    <p><?=$Base->RechnungsbetragE?> Gasrechnung abzüglich <?=$Warmwasser->Preis_WarmwasserE?> Warmwasser = <?=$Heizkostenverteiler->Preis_HeizungE?> Heizkosten. Aufteilung lt. HeizkostenV: 70% per Verbrauch, 30% per Fläche.</p>

    <p>70% der Heizkosten sind <?=$Heizkostenverteiler->Preis_Heizung_70ProzentE?>. Geteilt durch die Gesamtsumme der Messwerte (<?=$Heizkostenverteiler->Messergebnis_Haus?>) ergibt das einen Preis pro Messwert von <?=$Heizkostenverteiler->Preis_pro_Messwert?> €. Letzterer multipliziert mit <strong><?=$tenantsConsumption?></strong> in der Wohnung gemessenen Werten ergibt <strong><?=$Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption?> €</strong> verbrauchsmäßig der Wohnung zugeordnete Heizkosten.</p>

    <p>30% der Heizkosten sind <?=$Flaechenverteilung->PreisHeizung30ProzentE?>. Geteilt durch die gesamte Wohnfläche des Hauses (<?=$Base->Gesamtwohnflaeche?>m²) ergibt das einen Preis von <?=$Flaechenverteilung->Preis_pro_Quadratmeter?> € pro Quadratmeter. Dieser mal <strong><?=$row['qm']?></strong> m² Wohnfläche an <?=$Flaechenverteilung->getDaysStayed($row['Abrechnungsbeginn'], $row['Abrechnungsende'])?> Tagen (von <?=$row['Abrechnungsbeginn']?> bis <?=$row['Abrechnungsende']?>)  ergibt <strong><?=$Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] )?> €</strong></p>

    <p>Die Heizkosten betragen insgesamt somit <?=$Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption?> + <?= $proportionateCost = $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] );?> = <strong><?=$Base->euro( ($Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption) + $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] ))?></strong></p>

    <?php
        $ww = $Warmwasser->preis_pro_Wohnung($row['qm']);
        $hk = $Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption;
        $hk += $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] );
    ?>

    <p>Das Verhältnis von Warmwasser- zu Heizkosten ist <?=$Base->percentage($ww,$hk)[0]?>% zu <?=$Base->percentage($ww,$hk)[1]?>%</p>
<div class="page_break"></div>
<br/>
<!-- end hyperloop ------------------------------------------------------------------------------------------------ -->
<?php } ?>

</body>
</html>

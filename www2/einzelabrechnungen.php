<?php
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
    $tenantsConsumption = $Heizkostenverteiler->getMeteredData($Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende']);
    ?>

    <h1>Heizkostenabrechnung für <?=$Base->Abrechnungsjahr?></h1> 
    <h2><?=$row['Etage']?> <?=$row['Lage']?> - <?=$row['Nachname']?>, <?=$row['Vorname']?></h2>
    <p>Abrechnungszeitraum: <?=$Base->formatDate($row['Abrechnungsbeginn'])?> - <?=$Base->formatDate($row['Abrechnungsende'])?></p>

    <p><?=$Base->RechnungsbetragE?> Gasrechnung abzüglich <?=$Warmwasser->Preis_WarmwasserE?> Warmwasser = <?=$Heizkostenverteiler->Preis_HeizungE?> Heizkosten. Aufteilung lt. HeizkostenV: 70% per Verbrauch, 30% per Fläche.</p>

    <p>70% der Heizkosten sind <?=$Heizkostenverteiler->Preis_Heizung_70ProzentE?>. Geteilt durch die Gesamtsumme der Messwerte (<?=$Heizkostenverteiler->getMeteredData($Base->Abrechnungsjahr)?>) ergibt das einen Preis pro Messwert von <?=$Heizkostenverteiler->Preis_pro_Messwert?> €.</p>

    <p>Letzterer multipliziert mit <strong><?=$tenantsConsumption?></strong> gemessene Werten macht <strong><?=$Heizkostenverteiler->euro($Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption)?></strong> verbrauchsmäßig zugeordnete Heizkosten.</p>

    <p>30% der Heizkosten entspricht <?=$Flaechenverteilung->PreisHeizung30ProzentE?>, geteilt durch die gesamte Wohnfläche des Hauses von <?=$Base->Gesamtwohnflaeche?> ergibt einen Preis pro m² von <?=$Flaechenverteilung->Preis_pro_QuadratmeterE?></p>

    <p>Dieser mal Wohnfläche von <strong><?=$row['qm']?></strong> m² ist <strong><?=$Base->euro( $Flaechenverteilung->Preis_pro_Quadratmeter * $row['qm'] )?></strong></p>

    <p>Die Heizkosten betragen insgesamt also <?=$Base->euro($Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption)?> + <?=$Base->euro( $Flaechenverteilung->Preis_pro_Quadratmeter * $row['qm'] )?> = <strong><?=$Base->euro( ($Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption) + ($Flaechenverteilung->Preis_pro_Quadratmeter * $row['qm']) )?></strong></p>

<div class="page_break"></div>
<br/>
<!-- end hyperloop ------------------------------------------------------------------------------------------------ -->
<?php } ?>

</body>
</html>
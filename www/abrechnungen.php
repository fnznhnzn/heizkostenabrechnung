<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 'on');
require_once('classes/Gasrechnung.php');
require_once('classes/Verteilung.php');
require_once('classes/Abrechnung.php');
$gas = new Gasrechnung();
$hkv = new Verteilung($gas);
$abr = new Abrechnung();
?>
<!DOCTYPE html>
<html lang="de">
    <head>
        <meta charset="utf-8"/>
        <title>Abrechnung</title>
        <link rel="stylesheet" href="css/print.css"/>
    </head>
    <body>
<!-- begin hyperloop ---------------------------------------------------------------------------------------------- -->
<?php foreach($abr->getBillReceivers() as $index => $row){ 
    $mC = $hkv->meteredConsumption($gas->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende']);
    ?>

    <h1>Heizkostenabrechnung für <?=$gas->Abrechnungsjahr?></h1> 
    <h2><?=$row['Etage']?> <?=$row['Lage']?> - <?=$row['Nachname']?>, <?=$row['Vorname']?></h2>
    <p>Abrechnungszeitraum: <?=$hkv->formatDate($row['Abrechnungsbeginn'])?> - <?=$hkv->formatDate($row['Abrechnungsende'])?></p>
    <p><?=$gas->RechnungsbetragE?> Gasrechnung abzüglich <?=$gas->PreisWarmwasserE?> Warmwasser = <?=$gas->PreisHeizungE?> Heizkosten. Aufteilung lt. HeizkostenV: 70% per Verbrauch, 30% per Fläche.</p>
    <p>70% der Heizkosten sind <?=$gas->PreisHeizung70ProzentE?>, geteilt durch die Gesamtsumme der Verbrauchswerte <?=$hkv->summeAllerZaehlerwerte()?> ergibt <?=$hkv->preisProMesswert?> Preis pro Messwert.</p>
    <p>Dieser mal <?=$mC?> im Zeitraum gemessene Verbrauchswerte macht <strong><?=$gas->euro($hkv->preisProMesswert * $mC)?></strong> verbrauchsmäßig zugeordnete Heizkosten.</p>
    <p>30% der Heizkosten entspricht <?=$gas->PreisHeizung30ProzentE?>, geteilt durch die gesamte Wohnfläche des Hauses von <?=$abr->Gesamtflaeche?> ergibt einen Preis pro m² von <?=$gas->preisProQuadratmeter?></p>
    <p>Multipliziert mit der Wohnfläche von <?=$row['qm']?> m² sind das <strong><?=$gas->euro( $gas->preisProQuadratmeter * $row['qm'] )?></strong></p>
    <p>Die Heizkosten betragen insgesamt ergo <?=$gas->euro($hkv->preisProMesswert * $mC)?> plus <?=$gas->euro( $gas->preisProQuadratmeter * $row['qm'] )?> = <strong><?=$gas->euro( ($hkv->preisProMesswert * $mC) + ($gas->preisProQuadratmeter * $row['qm']) )?></strong></p>
    
    
    

<div class="page_break"></div>
<br/>
<!-- end hyperloop ------------------------------------------------------------------------------------------------ -->
<?php } ?>

</body>
</html>
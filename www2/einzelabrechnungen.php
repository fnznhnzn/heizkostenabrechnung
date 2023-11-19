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
    $tenantsConsumption = $Heizkostenverteiler->getMeteredData( $Base->Abrechnungsjahr, $row['Abrechnungsbeginn'], $row['Abrechnungsende'],  $row['Whg_ID'] );
?>

<h1>Energiekostenabrechnung für <?=$Base->Abrechnungsjahr?></h1> 
<h2><?=$row['Etage']?> <?=$row['Lage']?> - <?=$row['Nachname']?>, <?=$row['Vorname']?></h2>
<p>Abrechnungszeitraum: <?=$Base->formatDate($row['Abrechnungsbeginn'])?> - <?=$Base->formatDate($row['Abrechnungsende'])?></p>

    <h3>Warmwasser</h3>
    <p><?=$Base->Kilowattstunden?> Kilowattstunden Erdgas kosteten <?=$Base->Abrechnungsjahr?> die Summe von <?=$Base->RechnungsbetragE?>, also <?=$Base->KilowattstundenpreisE?> pro Kilowattstunde.</p>
    <p>Der Gasverbrauch zur Erwärmung von <?=$Warmwasser::WARMWASSERKUBIKMETER?> Kubikmetern insgesamt in <?=$Base->Abrechnungsjahr?> verbrauchtem Wasser beträgt berechnet nach HeizkostenV <strong><?=$Warmwasser->kWh_Gas_fuer_Warmwasser?></strong> Kilowattstunden. Damit ergeben sich Energiekosten von <strong><?=$Warmwasser->Preis_WarmwasserE?></strong>, was bei einer Gesamtfläche von <?=$Base->Gesamtwohnflaeche?> Quadratmetern Kosten von <?=$Warmwasser->Preis_Warmwasser_pro_Quadratmeter?> € pro Quadratmeter entspricht.</p>
    <p>Mit einer Fläche von <?=$row['qm']?> m² entfallen davon somit <strong><?=$Warmwasser->preis_pro_Wohnung($row['qm'])?></strong> auf diese Wohnung.</p>

    <h3>Heizung</h3>
    <p><?=$Base->RechnungsbetragE?> Gasrechnung abzüglich <?=$Warmwasser->Preis_WarmwasserE?> Warmwasser = <?=$Heizkostenverteiler->Preis_HeizungE?> Heizkosten. Aufteilung lt. HeizkostenV: 70% per Verbrauch, 30% per Fläche.</p>

    <p>70% der Heizkosten sind <?=$Heizkostenverteiler->Preis_Heizung_70ProzentE?>. Geteilt durch die Gesamtsumme der Messwerte (<?=$Heizkostenverteiler->Messergebnis_Haus?>) ergibt das einen Preis pro Messwert von <?=$Heizkostenverteiler->Preis_pro_Messwert?> €. Letzterer multipliziert mit <strong><?=$tenantsConsumption?></strong> in der Wohnung gemessenen Werten ergibt <strong><?=$Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption?> €</strong> verbrauchsmäßig der Wohnung zugeordnete Heizkosten.</p>

    <p>30% der Heizkosten sind <?=$Flaechenverteilung->PreisHeizung30ProzentE?>. Geteilt durch die gesamte Wohnfläche des Hauses (<?=$Base->Gesamtwohnflaeche?>m²) ergibt das einen Preis von <?=$Flaechenverteilung->Preis_pro_Quadratmeter?> € pro Quadratmeter. Dieser mal Wohnfläche von <strong><?=$row['qm']?></strong> m² ergibt <strong><?=$Flaechenverteilung->Preis_pro_Quadratmeter * $row['qm']?> €</strong></p>

    <p>Die Heizkosten betragen insgesamt somit <?=$Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption?> + <?= $Flaechenverteilung->Preis_pro_Quadratmeter * $row['qm']?> = <strong><?=$Base->euro( ($Heizkostenverteiler->Preis_pro_Messwert * $tenantsConsumption) + ($Flaechenverteilung->Preis_pro_Quadratmeter * $row['qm']) )?></strong></p>


<div class="page_break"></div>
<br/>
<!-- end hyperloop ------------------------------------------------------------------------------------------------ -->
<?php } ?>

</body>
</html>
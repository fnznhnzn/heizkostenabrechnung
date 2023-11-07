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
<?php foreach($abr->getBillReceivers() as $index => $row){ ?>

    <h1>Heizkostenabrechnung für <?=$gas->Abrechnungsjahr?></h1> 
    <h2><?=$row['Etage']?> <?=$row['Lage']?> - <?=$row['Nachname']?>, <?=$row['Vorname']?></h2>
    <p>Abrechnungszeitraum <?=$row['Einzug']?> - <?=$row['Auszug']?></p>
    <p>Verbrauch <?=$hkv->meteredConsumption($gas->Abrechnungsjahr, $row['Whg_ID'], 1, 12)?></p>
    
    

<div class="page_break"></div>
<br/>
<!-- end hyperloop ------------------------------------------------------------------------------------------------ -->
<?php } ?>

</body>
</html>
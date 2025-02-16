<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 'on');

require_once('../classes/Base.php');
require_once('../classes/Warmwasser.php');
require_once('../classes/Heizkostenverteiler.php');
require_once('../classes/Flaechenverteilung.php');

$Base                   = new Base();
$Warmwasser             = new Warmwasser();
$Heizkostenverteiler    = new Heizkostenverteiler( $Warmwasser->Preis_Warmwasser );
$Flaechenverteilung     = new Flaechenverteilung( $Heizkostenverteiler->Preis_Heizung );

$Base->totalFlats();

$y = $_GET['y'] ?? $Base->Abrechnungsjahr;
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <title>Zählerwerte pro Wohnung <?=$y?></title>
    <style>
        @media (min-width: 40rem) {
            img { float: right; max-width: 50%; margin: 0 0 1rem 1rem; }
            .column { columns: 4; column-rule: 1px solid #ccc; }
            .column.whg { break-inside: avoid; }
        }
        @media (min-width: 80rem) { }
        .val { width:50px; text-align: right; }
    </style>
</head>
<?php include('../nav.inc.php'); ?>
<h1>Zählerwerte pro Wohnung in <?=$y?></h1>
<div class="column">
<?php
$total = 0;
for($i=0; $i<$Base->totalFlats(); $i++){
    $sql = <<<SQL
    SELECT z.ID zid, MAX(Wert * h.Kq * h.Kc / 2.288) w
        FROM Wohnungen w
        LEFT JOIN Zaehler z ON z.Whg_ID = w.ID
        LEFT JOIN Heizkoerper h ON z.Heizkoerper_ID = h.ID
        LEFT JOIN Messwerte m ON z.ID = m.Zaehler_ID
        WHERE YEAR(Zeitpunkt) = '$y'
        AND w.ID = $i+1
        GROUP BY Whg_ID, z.ID
    SQL;

    $res = $Base->conn->query($sql);
    $sum = 0;
    echo '<table class="whg">';
    echo '<tr><th colspan="2">Whg. ' . $i+1 . '</th></tr>';
    while( $row = mysqli_fetch_assoc( $res )){
        echo '<tr><td>' . $row['zid'] . '</td><td class="val">' . $row['w'] . '</td></tr>';
        $sum += $row['w'];
    }
    echo '<tr><td colspan="2" align="right"><strong>' . $sum . '</strong></td></tr>';
    echo '</table>' . "\r\n";
    $total += $sum;
}
echo '<p>Gesamt: <strong>' . $total . '</strong></p>';
?>
</div>







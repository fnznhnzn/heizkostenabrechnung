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
        <title>Heizkostenabrechnung Übersicht</title>
        <meta charset="utf-8">
        <link rel="stylesheet" href="css/style.css"/>
    </head>
    <body>
        <h1>Übersicht HK-Abrechnung <?=$Base->Abrechnungsjahr?></h1>

        <h2>Gasrechnung</h2>
            <p>Gasrechnung von <?=$Base->Lieferant?> vom <?=$Base->Rechnungsdatum;?> für das Jahr <?=$Base->Abrechnungsjahr?>:<br/>  
            <?=$Base->Kilowattstunden?> Kilowattstunden Erdgas kosteten <strong class="skyblue"><?=$Base->RechnungsbetragE?></strong><br/>
            Der Preis für eine Kilowattstunde beträgt damit genau <strong class="yellow"><?=$Base->KilowattstundenpreisE?></strong></p>

<!-- --------------------------------------------------------------------------------------------------------- Warmwasser (nach Wohnfläche) -->
        <h2>Wassererwärmung</h2>
        <p>Lt. <a href="https://www.gesetze-im-internet.de/heizkostenv/" target="_blank">HeizkostenV</a> müssen die Kosten für die Warmwassererwärmung zunächt abgezogen werden. Nach §9 Ziffer 2 ergibt sich der Gasverbrauch für eine zentrale Wassererwärmung wie folgt:</p>
        <pre>                            2,5 x V x (tw-10) = Q</pre>
        <ul>
            <li>2,5 der Wert für die Erzeugeraufwandszahl des Wärmeerzeugers, mittlere spezifische Wärmekapazität des Wassers, Wärmeverluste für Warmwasserspeicher, Verteilung einschließlich Zirkulation, Messdatenerhebungen zum Warmwasserverbrauch</li>
            <li>V = Warmwasserverbrauch in m³</li>
            <li>tw = Warmwassertemperatur (üblicherweise <strong>55°</strong>)</li>
            <li>10 der Wert für die übliche Kaltwassereintrittstemperatur in die Warmwasserversorgungsanlage in Grad Celsius</li>
            <li>Q = Gasverbrauch in Kilowattstunden</li>
        </ul>
        <p><?=$Base->Abrechnungsjahr?> wurden insgesamt <strong><?=$Warmwasser::WARMWASSERKUBIKMETER?></strong> Kubikmeter warmes Wasser verbraucht. Damit ergibt sich 
        als Gasverbrauch für die Wassererwärmung (s.o.):</p>
        <pre>                            2,5 x <strong><?=$Warmwasser::WARMWASSERKUBIKMETER?></strong> x (55-10) = <?=$Warmwasser->kWh_Gas_fuer_Warmwasser?> kWh</pre>

        <p>Gemäß §9 HeizkostenV muss der Gasverbrauch mit 1,11 multipliziert werden:
        <pre>    <?=$Warmwasser->kWh_Gas_fuer_Warmwasser?> x 1,11 = <strong class="green"><?=$Warmwasser->kWh_Gas_fuer_Warmwasser_mit_Brennwertfaktor?></strong></pre>
        <p>Die Kosten für die gesamte Wassererwärmung betragen folglich:</p>
        <pre>    <strong class="green"><?=$Warmwasser->kWh_Gas_fuer_Warmwasser_mit_Brennwertfaktor?></strong> kWh Gasverbrauch x <strong class="yellow"><?=$Base->KilowattstundenpreisE?></strong> = <strong class="pink"><?=$Warmwasser->Preis_WarmwasserE?></strong></pre>
        
<!-- ---------------------------------------------------------------------------------------------------------------------------- Heizung -->
        <h2>Heizung</h2>
        <p>Die Gasrechnung abzüglich der Kosten für die Wassererwärmung ergibt die Heizkosten:</p>
        <table>
            <tr><td colspan="2">Gasrechnung</td><td class="alignRight"><strong class="skyblue"><?=$Base->RechnungsbetragE?></strong></td></tr>
            <tr><td>minus</td><td>Wassererwärmung</td><td class="alignRight"><strong class="pink"><?=$Warmwasser->Preis_WarmwasserE?></pink></td></tr>
            <tr><td>gleich</td><td>Heizkosten</td><td class="alignRight"><strong class="orange"><?=$Heizkostenverteiler->Preis_HeizungE?></strong></td></tr>
        </table>

<!-- -------------------------------------------------------------------------------------------------------- 70% Heizkosten nach Verbrauch -->
        <h2>Verbrauchsabhängige Aufteilung</h2>
        <p>50-70% der verbleibenden Heizkosten müssen nach Verbrauch aufgeteilt werden (<a href="https://www.gesetze-im-internet.de/heizkostenv/BJNR002610981.html" target="_blank">HeizkostenV</a> §6 + §8 Absatz 1). 70% belohnt die Sparsamen und ist daher üblich.<p>
        <pre>  <strong class="orange"><?=$Heizkostenverteiler->Preis_HeizungE?></strong> x 0,7 = <strong class="brown"><?=$Heizkostenverteiler->Preis_Heizung_70ProzentE?></strong></pre>

<!-- -------------------------------------------------------------------------------------------------------- Heizkostenverteiler -->
        <h3>Heizkostenverteiler</h3>
        <p>An jedem Heizkörper ist ein Heizkostenverteiler (HKV) befestigt. Heizkörper haben jedoch unterschiedliche Größen und Eigenschaften. Um die Messwerte der HKV vergleichbar zu machen, werden deren Messwerte wie folgt bereinigt:</p>
        <pre>Bereinigter Messwert = M x Kc x Kq / B </pre>
        <ul>
            <li>M = Rohwert des Heizkostenverteilers</li>
            <li>Kc = Leistung des Heizkörpers in Kilowatt</li>
            <li>Kq = Trägheit des Heizkörpers</li>
            <li>B = Basisempfindlichkeit des HKV, 1,181 bei Engelmann HCA e2 im 1-Fühler-Modus</li>
        </ul>
        <a href="heizkostenverteilerliste.php?y=<?=$Base->Abrechnungsjahr?>">Liste Heizkostenverteiler</a>

<!-- ------------------------------------------------------------------------------------------------- Verteilung auf die Wohnungen -->
        <h3>Verteilung auf die Wohnungen</h3>
        <p>Die Summe aller HKV-Messwerte des gesamten Hauses im Jahr <?=$Base->Abrechnungsjahr?> betrug: <?=$Heizkostenverteiler->Messergebnis_Haus?>. 
        Teilt man die Heizkosten durch diese Summe erhält man den Preis pro Wert:</p>
        <pre><strong class="brown"><?=$Heizkostenverteiler->Preis_pro_Messwert?></strong> / <?=$Heizkostenverteiler->Messergebnis_Haus?> = <strong class="white"><?=$Base->nf( $Heizkostenverteiler->Preis_pro_Messwert )?> €</strong></pre>
        <p>Diesen multipliziert man mit den Werten einer Wohnung und erhält so deren Anteil an den Heizkosten.</p>
        <table>
            <tr><th>Mieter</th><th>HKV</th><th></th><th>Faktor</th><th></th><th></th><th></th><th>Euro</th></tr>

<?php
foreach( $Heizkostenverteiler->getBillReceivers() as $index => $row ) {
    $consumption = $Heizkostenverteiler->getMeteredData( $Base->Abrechnungsjahr, $row['Abrechnungsbeginn'], $row['Abrechnungsende'], $row['Whg_ID']);
    echo '<tr>
    <td>' . $row['Nachname'] . '</td>
    <td>' . $consumption . '</td>
    <td>x</td>
    <td>' . $Heizkostenverteiler->Preis_pro_Messwert . '</td>
    <td>=</td>
    <td class="alignRight">' . $Base->euro($consumption * $Heizkostenverteiler->Preis_pro_Messwert ) . '</td>
    </tr>'; 
}
?>
        </table>

<!-- -------------------------------------------------------------------------------------------------------- 30% Heizkosten nach Wohnfläche -->
        <h2>30% nach Wohnfläche</h2>
        <p>30% der Heizkosten: <strong class="orange"><?=$Heizkostenverteiler->Preis_HeizungE?></strong> x 0.3 = <strong class="violet"><?=$Flaechenverteilung->PreisHeizung30ProzentE?></strong></p>
        <p>Ergibt Kosten pro m²: <strong class="violet"><?=$Flaechenverteilung->PreisHeizung30ProzentE?></strong> / <?=$Base->Gesamtwohnflaeche?> m² Gesamtfläche = <?=$Flaechenverteilung->Preis_pro_Quadratmeter?> €</p>
        <p>Macht für die einzelnen Wohnungen entsprechend deren Fläche in m²:</p>
        <table>
            <tr><th>Mieter</th><th>m²</th><th>Faktor</th><th>Euro</th></tr>
<?php
foreach( $Heizkostenverteiler->getBillReceivers() as $index => $row ){
    $proportionateCost = $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] );
    echo '<tr>';
    echo '<td>' . $row['Nachname'] . '</td>';
    echo '<td>' . $row['qm'] . '</td>';
    echo '<td>x '. $Flaechenverteilung->Preis_pro_Quadratmeter . ' =</td>';
    echo '<td class="alignRight"> ' . $proportionateCost . '</td>';
    echo '</tr>';
}
?>
        </table>
<!-- ------------------------------------------------------------------------------------------------------- Heizkosten gesamt pro Wohnung -->
        <h2>Heizkosten gesamt pro Wohnung für <?=$Base->Abrechnungsjahr?></h2>
            <table>
                <tr><th>Mieter</th><th>p.Verbrauch</th><th>p.Fläche</th><th>Summe</th></tr>
<?php
foreach( $Heizkostenverteiler->getBillReceivers()    as $index => $row){
    $consumption = $Heizkostenverteiler->getMeteredData( $Base->Abrechnungsjahr, $row['Abrechnungsbeginn'], $row['Abrechnungsende'], $row['Whg_ID']);
    $consumptionCost = $consumption * $Heizkostenverteiler->Preis_pro_Messwert;
    $proportionateCost = $Flaechenverteilung->calculatedHeatingCostPerFlat( $Base->Abrechnungsjahr, $row['Whg_ID'], $row['Abrechnungsbeginn'], $row['Abrechnungsende'] );

    echo '<tr>
        <td>' . $row['Nachname'] . '</td>
        <td class="alignRight">' . $consumptionCost . '</td>
        <td class="alignRight">' . $proportionateCost . '</td>
        <td class="alignRight"><strong>' . ( $consumptionCost + $proportionateCost ) . '</strong></td>
        </tr>';
}
?>
    </body>
</html>

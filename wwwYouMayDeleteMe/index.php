<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 'on');

require_once('classes/Gasrechnung.php');
$gas = new Gasrechnung();

require_once('classes/Verteilung.php');
$hkv = new Verteilung($gas);

define('WASSERVERBRAUCH', 123); # dummie value! 

 
?>

<!DOCTYPE html>
<html lang="de">
    <head>
        <title>Heizkostenabrechnung Übersicht</title>
        <meta charset="utf-8">
        <link rel="stylesheet" href="css/style.css"/>
    </head>
    <body>
        <h1>Übersicht HK-Abrechnung <?=$gas->Abrechnungsjahr?></h1>

        <h2>Gasrechnung</h2>
            <p>Gasrechnung von <?=$gas->Lieferant?> vom <?=$gas->Rechnungsdatum;?> für das Jahr <?=$gas->Abrechnungsjahr?>:<br/>  
            <?=$gas->KilowattstundenD?> Kilowattstunden Erdgas, <strong class="skyblue"><?=$gas->RechnungsbetragE?></strong><br/>
            Eine Kilowattstunde kostet damit <strong class="yellow"><?=$gas->KilowattstundenpreisE?></strong></p>

<!-- --------------------------------------------------------------------------------------------------------- Warmwasser (nach Wohnfläche) -->
        <h2>Wassererwärmung</h2>
        <p>Lt. <a href="https://www.gesetze-im-internet.de/heizkostenv/" target="_blank">HeizkostenV</a> müssen die Kosten für die Warmwassererwärmung zunächt abgezogen werden. Nach §9 Ziffer 2 ergibt sich der Gasverbrauch für eine zentrale Wassererwärmung wie folgt:</p>
        <pre>                                  Q = 2,5 x V x (tw-10)</pre>
        <ul>
            <li>Q = Gasverbrauch in Kilowattstunden</li>
            <li>2,5 der Wert für die Erzeugeraufwandszahl des Wärmeerzeugers, mittlere spezifische Wärmekapazität des Wassers, Wärmeverluste für Warmwasserspeicher, Verteilung einschließlich Zirkulation, Messdatenerhebungen zum Warmwasserverbrauch</li>
            <li>V = Warmwasserverbrauch in m³</li>
            <li>tw = Warmwassertemperatur (üblicherweise <strong>55°</strong>)</li>
            <li>10 der Wert für die übliche Kaltwassereintrittstemperatur in die Warmwasserversorgungsanlage in Grad Celsius</li>
        </ul>
        <p><?=$gas->Abrechnungsjahr?> wurden insgesamt <strong><?=WASSERVERBRAUCH?></strong> Kubikmeter warmes Wasser verbraucht. Damit ergibt sich 
        als Gasverbrauch für die Wassererwärmung (s.o.):</p>
        <pre>                            2,5 x <strong><?=WASSERVERBRAUCH?></strong> * (55-10) = <strong class="green"><?=$gas->VerbrauchWarmwasserD?> kWh</strong></pre>

        <p>Die Kosten für die gesamte Wassererwärmung betragen folglich:</p>
        <pre>                       <strong class="green"><?=$gas->VerbrauchWarmwasserD?></strong> kWh Gasverbrauch x <strong class="yellow"><?=$gas->KilowattstundenpreisE?></strong> = <strong class="pink"><?=$gas->PreisWarmwasserE?></strong></pre>
        
        <h2>Heizkosten</h2>
        <p>Die Gasrechnung abzüglich der Kosten für die Wassererwärmung ergibt die Heizkosten:</p>
        <table>
            <tr><td colspan="2">Gasrechnung</td><td class="alignRight"><strong class="skyblue"><?=$gas->RechnungsbetragE?></strong></td></tr>
            <tr><td>minus</td><td>Wassererwärmung</td><td class="alignRight"><strong class="pink"><?=$gas->PreisWarmwasserE?></pink></td></tr>
            <tr><td>gleich</td><td>Heizkosten</td><td class="alignRight"><strong class="orange"><?=$gas->PreisHeizungE?></strong></td></tr>
        </table>

<!-- -------------------------------------------------------------------------------------------------------- 70% Heizkosten nach Verbrauch -->
        <h2>Verbrauchsabhängige Aufteilung</h2>
        <p>50-70% der verbleibenden Heizkosten müssen nach Verbrauch aufgeteilt werden (<a href="https://www.gesetze-im-internet.de/heizkostenv/BJNR002610981.html" target="_blank">HeizkostenV</a> §6 + §8 Absatz 1). 70% belohnt die Sparsamen und ist daher üblich.<p>
        <pre>  <strong class="orange"><?=$gas->PreisHeizungE?></strong> x 0,7 = <strong class="brown"><?=$gas->PreisHeizung70ProzentE?></strong></pre>

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
        <a href="heizkostenverteiler.php?y=<?=$gas->Abrechnungsjahr?>">Liste Heizkostenverteiler</a>

<!-- ------------------------------------------------------------------------------------------------- Verteilung auf die Wohnungen -->
        <h3>Verteilung auf die Wohnungen</h3>
        <p>Die Summe aller HKV-Messwerte des gesamten Hauses im Jahr <?=$gas->Abrechnungsjahr?> betrug: <?=$hkv->totalMeteredConsumption($gas->Abrechnungsjahr)?>. 
        Teilt man die Heizkosten durch diese Summe erhält man den Preis pro Wert:</p>
        <pre><strong class="brown"><?=$gas->PreisHeizung70ProzentE?></strong> / <?=$hkv->totalMeteredConsumption($gas->Abrechnungsjahr)?> = <strong class="white"><?=( $hkv->nf( $gas->PreisHeizung70Prozent / $hkv->totalMeteredConsumption($gas->Abrechnungsjahr) ) )?> €</strong></pre>
        <p>Diesen multipliziert man mit den Werten einer Wohnung und erhält so deren Anteil an den Heizkosten.</p>
        <table>
            <tr><th>Mieter</th><th>HKV</th><th></th><th>Faktor</th><th></th><th></th><th></th><th>Euro</th></tr>

<?php
$e=0;
foreach ($gas->conn->query( $hkv->zaehlerwerteGesamtProWohnung() ) as $index => $row) {
    echo '<tr>
    <td>' . $row['Nachname'] . '</td>
    <td>' . $row['whgTotal'] . '</td>
    <td>x</td>
    <td>' . $hkv->preisProMesswert . '</td>
    <td>=</td>
    <td class="alignRight">' . $gas->euro($hkv->einzelneZaehlerwerte()[$e] * $hkv->preisProMesswert) . '</td>
    </tr>'; 
    $kostenNachVerbrauchProWohnung[$e]['Nachname'] = $row['Nachname'];
    $kostenNachVerbrauchProWohnung[$e]['Heizkosten'] = $hkv->einzelneZaehlerwerte()[$e] * $hkv->preisProMesswert; 
    $e++;
}
?>
        </table>

<!-- -------------------------------------------------------------------------------------------------------- 30% Heizkosten nach Wohnfläche -->
        <h2>30% nach Wohnfläche</h2>
        <p>30% der Heizkosten: <strong class="orange"><?=$gas->PreisHeizungE?></strong> x 0.3 = <strong class="violet"><?=$gas->euro( $gas->PreisHeizung30Prozent )?></strong></p>
        <p>Ergibt Kosten pro m²: <strong class="violet"><?=$gas->euro( $gas->PreisHeizung30Prozent )?></strong> / <?=$gas->getWohnflaeche()?> m² Gesamtfläche = <?=$hkv->nf( $gas->preisProQuadratmeter )?> €</p>
        <p>Macht für die einzelnen Wohnungen entsprechend deren Fläche in m²:</p>
        <table>
            <tr><th>Mieter</th><th>m²</th><th>Faktor</th><th>Euro</th></tr>
<?php
foreach( $gas->gaspreisNachWohnflaeche() as $index => $row){
    echo "<tr>
    <td> {$row['Nachname']} </td>
    <td>{$row['qm']}</td>
    <td>x ". $gas->preisProQuadratmeter .' =</td>
    <td class="alignRight"> '. $gas->euro($row['gpnw']) . "</td>
    </tr>";
    $kostenNachFlaecheProWohnung[] = $row['gpnw'];
}
?>
        </table>
<!-- ------------------------------------------------------------------------------------------------------- Heizkosten gesamt pro Wohnung -->
        <h2>Heizkosten gesamt pro Wohnung für <?=$gas->Abrechnungsjahr?></h2>
            <table>
                <tr><th>Mieter</th><th>p.Verbrauch</th><th>p.Fläche</th><th>Summe</th></tr>
<?php
$i=0;
foreach( $kostenNachVerbrauchProWohnung as $index => $row){
    echo '<tr>
        <td>' . $row['Nachname'] . '</td>
        <td class="alignRight">' . $gas->euro( $row['Heizkosten'] ) . '</td>
        <td class="alignRight">' . $gas->euro( $kostenNachFlaecheProWohnung[$i] ) . '</td>
        <td class="alignRight"><strong>' . $gas->euro( $row['Heizkosten'] + $kostenNachFlaecheProWohnung[$i] ) . '</strong></td>
        </tr>';
        $i++;
}
?>
    </body>
</html>

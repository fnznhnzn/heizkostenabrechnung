<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 'on');

require_once('../classes/Base.php');
require_once('../classes/Heizkostenverteiler.php');

$Heizkostenverteiler = new Heizkostenverteiler(null);
?>
<!DOCTYPE html>
<html lang="de">
    <head>
        <title>Monatswerte</title>
    </head>
    <body>
        <table>
            <tr><th>Jahr</th><th>Monat</th><th>Wert</th></tr>
<?php
foreach( $Heizkostenverteiler->getMeteredDataByMonth('2023-01-01', '2023-12-31', 1) as $index => $row ) {
    echo '<tr><td>' . $row['y'] . '</td><td>' . $row['m'] . '</td><td>' . $row['v'] . '</td></tr>';  
}
?>
        </table>
</body>
</html>

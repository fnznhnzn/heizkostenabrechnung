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
        <script src="../js/canvasjs.min.js"></script>
        <script type="text/javascript">
            window.onload = function () {
                var chart = new CanvasJS.Chart("chartContainer", {
                    title:{
                        text: "Energieverbrauch pro Monat"              
                    },
                    data: [              
                    {
                        // Change type to "doughnut", "line", "splineArea", etc.
                        type: "line",
                        dataPoints: [
                            <?php
                            $consumption = $Heizkostenverteiler->getMeteredDataByMonth('2023-01-01', '2023-12-31', 1);
                            $last_key = array_key_last( $consumption );
                            foreach( $consumption as $key => $row ) {
                                echo '{ label: "' . $row['d'] . '", y:' . $row['v'] . '}'; 
                                if( $key !== $last_key ) { echo ','; }
                            }
                            ?>
                        ]
                    }
                    ]
                });
                chart.render();
            }
        </script>
        <title>Monatswerte</title>
    </head>
    <body>
        <div id="chartContainer" style="height: 300px; width: 100%;"></div>
</body>
</html>

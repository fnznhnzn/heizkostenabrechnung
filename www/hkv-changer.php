<?php
# April 1st 2026, ten (!) Engelmann HCAe2 broke down and have to be replaced
# To faciliate the process, this script does all the necessary database edits 
declare(strict_types=1);
error_reporting(E_ALL); 
ini_set('display_errors', 'on');
require_once('classes/Base.php');
$Base = new Base();

# We are our own ajax endpoint! Check input and update db:
if( $_GET && isEightDigits($_GET['oldHkv']) && isEightDigits($_GET['newHkv']) ){

    $oldHkv = $_GET['oldHkv'];
    $newHkv = $_GET['newHkv'];

    $updateOld = <<<SQL
        UPDATE Zaehler
        SET Ersetzt_durch = "$newHkv"
        WHERE ID = "$oldHkv"
    SQL;

    $insertNew = <<<SQL
        INSERT INTO Zaehler (ID, Whg_ID, Raum, Heizkoerper_ID, Installiert, Kennung)
        SELECT "$newHkv", Whg_ID, Raum, Heizkoerper_ID, CURDATE(), "$newHkv-EFE-FF-FF"
        FROM ZaehlerBK
        WHERE ID = "$oldHkv"
    SQL;

    $Base->conn->query( $updateOld );
    $Base->conn->query( $insertNew );

    return;
}

function isEightDigits($x){
    if( preg_match('/^\d{8}$/', $x) ) { 
        return true; 
    }
}

?>
<!DOCTYPE html>
<html lang="de">
<head>
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <link rel="stylesheet" href="css/style.css"/>
 <meta charset="utf-8">
 <style>
   .inputs { font-size: 8vw; margin:80px 0 0 40px; width: 200px; }
   #oldHkvChooser { color:red;   font-weight:bold; }
   #newHkvChooser { color:green; font-weight:bold; }
   #processButton { margin:40px 0 0 40px; font-size 5vw; width:200px; height:30px; }
 </style>

 <script>
    async function sendRequest(o,n) {
      const params = new URLSearchParams({ oldHkv: o, newHkv: n });
      try {
        const response = await fetch(`hkv-changer.php?${params}`);
      } catch (err) {
        console.error(err);
      }
    }

    let oldHkv, newHkv;
    document.addEventListener("DOMContentLoaded", () => {

        const oldHkvChooser = document.querySelector('#oldHkvChooser');
        oldHkvChooser.addEventListener("change", function() {
            oldHkv = oldHkvChooser.value; 
        });

        const newHkvChooser = document.querySelector('#newHkvChooser');
        newHkvChooser.addEventListener("keyup", function() {
            newHkv = newHkvChooser.value;
        });

        const processButton = document.querySelector('#processButton');
        processButton.addEventListener("click", function() {
            if (!/^\d{8}/.test(oldHkv) || !/^\d{8}/.test(newHkv)) { 
                alert('Falsche Eingaben'); 
                return;
            }
            let confirmed = confirm('Sind '+ oldHkv +' (alt) und '+ newHkv +' (neu) wirklich korrekt?');
            if(confirmed){
                sendRequest(oldHkv, newHkv);
                oldHkvChooser.value = '';
                newHkvChooser.value = '';
            }

        });

        oldHkvChooser.value = '';
        newHkvChooser.value = '';
    });
 </script>
</head>
<body>
<?php include("nav.inc.php"); ?>

 <select id="oldHkvChooser" class="inputs" name="oldHkv">
    <option value="">alt</option>
    <?php
    $sql = "SELECT ID FROM HKV_aktiv ORDER BY ID";
    foreach ($Base->conn->query( $sql ) as $index => $row) {
        echo '<option value="' . $row['ID'] . '">'. $row['ID'] . '</option>' . "/r/n";
    }
    ?>
 </select>
 
 <br/>

 <input type="number" id="newHkvChooser" class="inputs" name="newHkv" placeholder="neu" />
 
 <br/>

 <button id="processButton">HKV-Wechsel eintragen</button>
</body>
</html>

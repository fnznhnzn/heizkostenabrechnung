<?php
require_once('classes/Base.php');
$Base = new Base();
$sql = "SELECT * FROM Heizkoerper ORDER BY Kc";
?>
<!DOCTYPE html>
<html lang="de">
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="css/style.css"/>
    <meta charset="utf-8">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto">
</head>
<body>
<?php include("nav.inc.php"); ?>
<table>
<tr><th>ID</th><th>B</th><th>H</th><th>T</th><th>Hersteller</th><th>Art</th><th>Segmente</th><th>Segmentbreite</th><th>Schichtung</th><th>Kq</th><th>Kc</th><th>Foto</th></tr>
<?php foreach ($Base->conn->query( $sql ) as $index => $row) { ?>
<tr>
    <td><?=$row['ID']?></td>
    <td><?=$row['Breite']?></td>
    <td><?=$row['Hoehe']?></td>
    <td><?=$row['Tiefe']?></td>
    <td class="l"><?=$row['Hersteller']?></td>
    <td class="l"><?=$row['Art']?></td>
    <td><?=$row['Segmente']?></td>
    <td><?=$row['Segmentbreite']?></td>
    <td><?=$row['Schichtung']?></td>
    <td><?=$row['Kq']?></td>
    <td><?=$row['Kc']?></td>
    <td><a href="i/heizkoerper/HeizkoerperID-<?=$row['ID']?>.jpg"><img src="i/heizkoerper/thumbs/thumb-HeizkoerperID-<?=$row['ID']?>.jpg"/></a></td>
</tr>
<?php } ?>
</table>
</body>
</html>

<?php
$conn = new mysqli("localhost", 'heizkostenabrechnung', "KA-)1*hf[u7Qw[A.", "heizkostenabrechnung");
$sql = "SELECT * FROM Heizkoerper ORDER BY Kc";
?>
<!DOCTYPE html>
<html lang="de">
<head>
 <meta name="viewport" content="width=device-width, initial-scale=1.0">
 <link rel="stylesheet" href="css/style.css"/>
 <meta charset="utf-8">
 <script src="https://cdn.jsdelivr.net/npm/tesseract.js@4" defer></script>
</head>
<body>

<?php include("nav.inc.php"); ?>
<div style="margin-top:20px;">
 <select name="oldHkv" id="oldHkv">
  <option value="">Alter Verteiler</option>
  <option>ölkj</option>
 </select>
</div>


<video id="video" style="width:300px;" autoplay playsinline></video>
  <canvas id="canvas" style="display:none;"></canvas>

  <br>
  <div style="margin:10px;">
   <button style="width:100px; height:100px; margin-left:150px;" onclick="scan()">Scan Numbers</button>
  </div>

  <div id="output">Detected: —</div>

  <script>
    const video = document.getElementById('video');
    const canvas = document.getElementById('canvas');
    const ctx = canvas.getContext('2d');
    const output = document.getElementById('output');

    // Start camera
    //navigator.mediaDevices.getUserMedia({ video: { facingMode: "environment" } })
    navigator.mediaDevices.getUserMedia({
      video: {
        facingMode: "environment", // back camera
        width: { ideal: 1920 },    // high resolution
        height: { ideal: 1080 },
        frameRate: { max: 30 },

        // optional: advanced constraints
        advanced: [
          { focusMode: "manual", focusDistance: 0.05 },       // sharp, close text
          { exposureMode: "manual", iso: 200, exposureTime: 100 }, // reduce blur/noise
          { whiteBalanceMode: "manual", colorTemperature: 4500 },  // consistent light
          { zoom: 2 }  // optional digital zoom if needed
        ]
      }
    }).then(stream => {
        video.srcObject = stream;
      })
      .catch(err => {
        alert("Camera access denied or not available.");
        console.error(err);
      });

    async function scan() {
      // Set canvas size to video size
      canvas.width = video.videoWidth;
      canvas.height = video.videoHeight;

      // Draw current frame
      ctx.drawImage(video, 0, 0, canvas.width, canvas.height);

      output.innerText = "Scanning...";

      try {
        const result = await Tesseract.recognize(
          canvas,
          'eng',
          {
            tessedit_char_whitelist: 'R 0123456789',
          }
        );

        // Extract only numbers
        var numbers = result.data.text.replace(/[^0-9]/g, '');
	numbers = numbers.replace(/^0+/, '');
	

        output.innerText = numbers
          ? "Detected: " + numbers
          : "No numbers found";
      } catch (err) {
        console.error(err);
        output.innerText = "Error scanning";
      }
    }
  </script>
 
</body>
</html>

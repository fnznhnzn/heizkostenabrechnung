<?php
# wir holen alle Telegramme aus der vom Lobaro per mqtt hochgeladenen uplinks.csv,
# base64-dekodieren sie, 
# bilden die Meter-ID, indem wir Bytes 2-5 umdrehen
# und schicken das dekodierte Telegram und die ID zusammen mit dem AES-Schlüssel an wmbusmeters
#
# todo: Ergebnis in Datenbank schreiben

$AES_key = "48C1A9B237A0FE7D30AF25B6CD29014C";
$deviceName = "mein_zaehler";  // Der [some name] Parameter

// 1. Inhalt der Datei einlesen
$csvContent = file_get_contents('/home/heizkostengateway/refend-master/data/devices/70b3d5e050028bcc/uplinks.csv');
// RegEx für die Telegramme
$pattern = '/"telegram":\s*"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/';

// 2. Alle Vorkommen finden
if (preg_match_all($pattern, $csvContent, $matches)) {
    foreach ($matches[1] as $base64Telegram) {
        
        // Base64 in Hex wandeln (Das originale Telegramm für wmbusmeters)
        $binaryData = base64_decode($base64Telegram);
        $originalFullHex = bin2hex($binaryData);
        
        // Bytes extrahieren, um die Meter-ID zu berechnen
        // Wir brauchen nur die ersten 4 Bytes (Länge, Prüfsumme, Zählerhersteller) 
        // und die darauffolgenden Bytes 5-8
        if (preg_match('/^([0-9a-fA-F]{8})([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})([0-9a-fA-F]{2})/', $originalFullHex, $m)) {
            
            // Die extrahierten und umgedrehten Bytes 5-8 ergeben die Meter-ID
            $meterId = $m[5] . $m[4] . $m[3] . $m[2];
            
            // 3. Den wmbusmeters-Befehl zusammenbauen
            // Syntax (from the man page):
            // wmbusmeters {options} [hex] { [name] [driver] [id] [key] }*
            // hier:
            // wmbusmeters --format=json [Original-Telegramm] [some name] [auto oder driver] [Meter-ID] [$AES_key]
            $cmd = sprintf(
                'wmbusmeters --format=json %s %s %s %s %s',
                escapeshellarg($originalFullHex),
                escapeshellarg($deviceName),
                escapeshellarg("auto"),
                escapeshellarg($meterId),
                escapeshellarg($AES_key)
            );
            
            // 4. Befehl ausführen
            $output = shell_exec($cmd);
            
            // 5. Ergebnis ausgeben
            echo "Ergebnisse für Meter-ID ($meterId):\n" . $output . "\n";
        }
    }
}


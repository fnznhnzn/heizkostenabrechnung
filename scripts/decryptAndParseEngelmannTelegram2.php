<?php

// ============================================================================
// 1. EINGABE UND ENTSCHLÜSSELUNG
// ============================================================================
$base64Telegram = "0kTFFJBAYWAxCHooAMAlGT6SGj5FY6DeTyV6doXIaAL+LZXCVU51xPEcTUYh8eqiINDZG4LFck81peI+w6ENun71Q2SMUW1P5Y3Vu3Xd3IY2hunJPhd4+QXmhv4wsjewWCofXVemViNIbc9Uiilw6hF5bgh8L+zaIIy9pZuGeK1hd7l04FEirh+1TOFWCPrYSNrIW4jobubWp0FGtNH+ndV5q4ZAzCVCLxkqqOIGwvYEA4Y7QHD4UWA13sXG7lozcNE9FcuNLiI0f+q0dF7H";
$rawTelegram = base64_decode($base64Telegram);

// DEIN AES-KEY (16 Bytes / 32 Hex-Zeichen)
$aesKeyHex = "48C1A9B237A0FE7D30AF25B6CD29014C"; // <--- HIER DEINEN ECHTEN KEY EINTRAGEN
$keyBytes  = hex2bin($aesKeyHex);

// Header-Komponenten für den IV extrahieren
$mfgIdBytes  = substr($rawTelegram, 2, 2);   // C5 14
$serialBytes = substr($rawTelegram, 4, 4);   // 90 40 61 60
$versionByte = substr($rawTelegram, 8, 1);   // 31
$mediumByte  = substr($rawTelegram, 9, 1);   // 08
$accessCtrl  = substr($rawTelegram, 11, 1);  // 28

// Seriennummer lesbar machen für die Ausgabe (z.B. "60614090")
$meterSerial = bin2hex(strrev($serialBytes));

// IV exakt nach ermitteltem Treffer zusammenbauen
$ivBytes = $mfgIdBytes . $serialBytes . $versionByte . $mediumByte . str_repeat($accessCtrl, 8);

// Ciphertext ab dem verifizierten Byte-Offset 15 extrahieren
$ciphertext = substr($rawTelegram, 15);

// Auf 16-Byte-Blöcke runden (schneidet eventuelle Funk-Füllbytes am Ende ab)
$validLength = floor(strlen($ciphertext) / 16) * 16;
$ciphertext = substr($ciphertext, 0, $validLength);

// Entschlüsselung ausführen
$decryptedRaw = openssl_decrypt(
    $ciphertext,
    'aes-128-cbc',
    $keyBytes,
    OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
    $ivBytes
);

if ($decryptedRaw === false) {
    die("Fehler bei der Entschlüsselung.\n");
}

$decryptedHex = bin2hex($decryptedRaw);

echo "=== TELEGRAMM ERFOLGREICH ENTSCHLÜSSELT ===\n";
echo "Zähler-Seriennummer: " . $meterSerial . "\n";
echo "Entschlüsselter Payload (Hex):\n" . $decryptedHex . "\n\n";


// ============================================================================
// 2. AUTOMATISCHES PARSING & ÜBERSETZUNG (M-BUS PARSER)
// ============================================================================
$length = strlen($decryptedRaw);
$pointer = 0;

$currentTimestamp = null;
$lastParsedDate   = null; 

echo "=== M-BUS PAYLOAD TELEMETRIE ÜBERSETZUNG ===\n";
printf("%-26s | %-25s | %s\n", "Kontext", "Daten-Typ / Register", "Wert");
echo str_repeat("-", 85) . "\n";

// 1. OMS-Padding / Krypto-Marker am Anfang überspringen (2F 2F)
while ($pointer < $length && ord($decryptedRaw[$pointer]) == 0x2F) {
    $pointer++;
}

// 2. Sequenzieller Parser-Loop durch die DIF/VIF-Strukturen
while ($pointer < $length) {
    if ($pointer >= $length) break;
    
    $dif = ord($decryptedRaw[$pointer]);
    
    // Wenn wir am Ende wieder auf AES-Padding (0x2F) stoßen, abbrechen
    if ($dif == 0x2F) {
        break;
    }
    
    $pointer++;
    
    // Extraktion der erweiterten Speicher-Bits (Storage Number)
    $storageNumber = ($dif & 0x40) >> 6; 
    $difeCount = 0;
    $tempDif = $dif;
    
    while ($tempDif & 0x80) {
        if ($pointer >= $length) break 2;
        $dife = ord($decryptedRaw[$pointer]);
        $pointer++;
        $difeCount++;
        $storageNumber |= ($dife & 0x0F) << ($difeCount * 4 + 1);
        $tempDif = $dife;
    }
    
    // VIF einlesen
    if ($pointer >= $length) break;
    $vif = ord($decryptedRaw[$pointer]);
    $pointer++;
    
    // Erweiterte VIF-Bytes (VIFE) überspringen, falls vorhanden
    $tempVif = $vif;
    while ($tempVif & 0x80) {
        if ($pointer >= $length) break 2;
        $vife = ord($decryptedRaw[$pointer]);
        $pointer++;
        $tempVif = $vife;
    }
    
    // Datenlänge anhand des extrahierten Grund-DIFs ermitteln
    $dataLengthIndicator = $dif & 0x0F;
    $valueLength = 0;
    
    switch ($dataLengthIndicator) {
        case 0x01: $valueLength = 1; break;
        case 0x02: $valueLength = 2; break;
        case 0x03: $valueLength = 3; break;
        case 0x04: $valueLength = 4; break;
        default:   $valueLength = 0; break;
    }
    
    if ($pointer + $valueLength > $length) {
        break;
    }
    
    // Rohdaten-Bytes ausschneiden
    $valueBytes = substr($decryptedRaw, $pointer, $valueLength);
    $pointer += $valueLength;
    
    // --- Daten-Interpretation und Ausgabe ---
    
    // A. Aktueller Sende-Zeitstempel (Typ F)
    if ($vif == 0x6D && $storageNumber == 0) {
        $currentTimestamp = parseMbusTypeF($valueBytes);
        printf("%-26s | %-25s | %s\n", "[ Aktuell ]", "Datum & Uhrzeit", $currentTimestamp);
    } 
    
    // B. Aktueller Zählerstand
    elseif ($vif == 0x6E && $storageNumber == 0) {
        $points = parseMbusInteger($valueBytes);
        printf("%-26s | %-25s | %d Punkte\n", "[ Aktuell ]", "Zählerstand (Punkte)", $points);
    }
    
    // C. Historische Datums-Register (Typ G)
    elseif ($vif == 0x6C && $storageNumber > 0) {
        $lastParsedDate = parseMbusTypeG($valueBytes);
    } 
    
    // D. Historische Verbrauchs-Register (Monat oder Halbmonat)
    elseif ($vif == 0x6E && $storageNumber > 0) {
        $points = parseMbusInteger($valueBytes);
        
        if ($lastParsedDate) {
            // Linearisierung des Speicher-Index (CSV-konform)
            $linearIndex = ($storageNumber % 64);
            
            // Erkennung ob Monat oder Halbmonat anhand des Tages (15. = Halbmonat)
            $isHalfMonth = (substr($lastParsedDate, 8, 2) === '15');
            $contextName = $isHalfMonth ? "Halbmonat [$linearIndex]" : "Monat [$linearIndex]";
            $labelName   = $isHalfMonth ? "Halbmonats-Wert" : "Monats-Wert";
            
            printf("%-26s | %-25s | Stichtag: %s -> %d Punkte\n", "[ " . $contextName . " ]", $labelName, $lastParsedDate, $points);
        }
    }
    
    // E. Hersteller-spezifische Diagnose-Bytes
    elseif ($vif == 0x7F) {
        printf("%-26s | %-25s | Hex: %s\n", "[ Aktuell ]", "Hersteller-Spezifisch", bin2hex($valueBytes));
    }
}

echo str_repeat("-", 85) . "\n";


// ============================================================================
// 3. MATHEMATISCHE HILFSFUNKTIONEN (ARRAY-KORRIGIERT)
// ============================================================================

function parseMbusInteger($bytes) {
    if (strlen($bytes) == 0) return 0;
    return hexdec(bin2hex(strrev($bytes)));
}

function parseMbusTypeF($bytes) {
    if (strlen($bytes) < 4) return "Ungültig";
    // unpack gibt ein Array mit Indizes 1 bis 4 zurück
    $b = unpack("C*", $bytes);
    
    $minute = $b[1] & 0x3F;
    $hour   = $b[2] & 0x1F;
    $day    = $b[3] & 0x1F;
    
    // Monat: 3 Bits aus Byte 3 (5-7) + 1 Bit aus Byte 4 (Bit 4)
    $month  = (($b[3] & 0xE0) >> 5) | (($b[4] & 0x10) >> 1);
    
    // Jahr: 4 Bits aus Byte 4 (0-3) + 3 Bits aus Byte 4 (5-7)
    $year   = ($b[4] & 0x0F) | (($b[4] & 0xE0) >> 1);
    
    return sprintf("20%02d-%02d-%02d %02d:%02d:00", $year, $month, $day, $hour, $minute);
}

function parseMbusTypeG($bytes) {
    if (strlen($bytes) < 2) return "Ungültig";
    $b = unpack("C*", $bytes);
    
    $day   = $b[1] & 0x1F;
    
    // Monat: 3 Bits aus Byte 1 (5-7) + 1 Bit aus Byte 2 (Bit 4)
    $month = (($b[1] & 0xE0) >> 5) | (($b[2] & 0x10) >> 1);
    
    // Jahr: 4 Bits aus Byte 2 (0-3) + 3 Bits aus Byte 2 (5-7)
    $year  = ($b[2] & 0x0F) | (($b[2] & 0xE0) >> 1);
    
    if ($day == 0 || $month == 0) return '2000-00-00';
    
    return sprintf("20%02d-%02d-%02d", $year, $month, $day);
}

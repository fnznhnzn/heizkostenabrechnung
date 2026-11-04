<?php
# Parse M-Bus telegrams and send them to wmbusmeters
#
# 1. extract telegrams from CSV file
# 2. base64 decode and convert to hex
# 3. get header data from  first eight bytes
# 4. send to wmbusmeters
#
# todo: 
# write to database

require_once __DIR__ . '/../config.inc.php';
$AES_key = AES_KEY;

$csvContent = file_get_contents(CSV_UPLOAD_DIR . '/uplinks2.csv');

# 1. extract telegram
$pattern = '/"telegram":\s*"([^"\\\\]*(?:\\\\.[^"\\\\]*)*)"/';
if (preg_match_all($pattern, $csvContent, $matches)) {
    $i = 1;
    foreach ($matches[1] as $base64Telegram) {
        
        $deviceName = "mein_zaehler-" . $i;  // Der [some name] Parameter
        $i++;
        
        # 2. decode and convert
        $binaryData = base64_decode($base64Telegram);
        $originalFullHex = bin2hex($binaryData);
        
        # 3. get header data
        $header = substr($originalFullHex, 0, 16);
        $bytes = str_split($header, 2); # instead of lenghty regex
        
        # header bytes:
        #   0  |   1  |  2   |   3  |  4   |   5  |   6  |   7  |   8  |   9    |  10  |  11  | -> Application Layer
        # 0x2E | 0x44 | 0xB0 | 0x5C | 0x12 | 0x13 | 0x00 | 0x00 | 0x02 | 0x1B   | 0x8A |  ... |
        # Len  |  C   |  M   |  M   |   ID |  ID  |  ID  |  ID  | Ver  | Device |  CRC |  CRC |       
        # (Len = length, C = checksum, M = manufacturer, ID = meter ID, Ver = version, Device = device, CRC = cyclic redundancy check)
        
        # reverse byte order
        $manufacturer   = $bytes[3] . $bytes[2]; # 14c5 = EFE, 12fa = DWZ (see https://www.m-bus.de/man.html)
        $meterId        = $bytes[7] . $bytes[6] . $bytes[5] . $bytes[4];
        
        # to do this properly, we would need to use the meters table or better yet, a meter type table
        if ($manufacturer === '12fa') { # DWZ water meter (no AES key)
            $driver = "waterstarm";
            $AES_key = '';
        } 

        if ($manufacturer === '14c5') { # EFE heat cost allocator
            $driver = "hcae2"; # EFE heat cost allocator
            $AES_key = AES_KEY;
        }
            
        // 4. wmbusmeters {options} [hex] { [name] [driver] [id] [key] }
        // wmbusmeters --format=json [Original-Telegramm] [some name] [auto or driver] [Meter-ID] [$AES_key]
        $cmd = sprintf(
            'wmbusmeters --format=json --ppjson --silent %s %s %s %s %s',
            escapeshellarg($originalFullHex),
            escapeshellarg($deviceName),
            escapeshellarg($driver),
            escapeshellarg($meterId),
            escapeshellarg($AES_key)
        );
        
        $output = shell_exec($cmd);
        $meterData = json_decode($output);

        if ($meterData === null && json_last_error() !== JSON_ERROR_NONE) {
            # no valid json, no going further    
            continue;
        }
            
        echo '---------------------------------------------------------------------------' . "\n";
        
        echo 'media: ' . $meterData->media . "\n";
        echo 'meter: ' . $meterData->meter . "\n";
        echo 'name: ' . $meterData->name . "\n";
        echo 'id: ' . $meterData->id . "\n";
        echo 'timestamp: ' . (new DateTime($meterData->timestamp))->format('Y-m-d H:i:s') . "\n";
        echo 'status: ' . $meterData->status . "\n";
        
        if($manufacturer === '14c5'){ # EFE / Engelmann
            echo 'Current Consumption HCA: ' . $meterData->current_consumption_hca . "\n";
            echo 'Consumption at Set Date 1 HCA: ' . $meterData->consumption_at_set_date_1_hca . "\n";
            echo 'Consumption at Set Date 2 HCA: ' . $meterData->consumption_at_set_date_2_hca . "\n";
            echo 'Consumption at Set Date 3 HCA: ' . $meterData->consumption_at_set_date_3_hca . "\n";
            echo 'Consumption at Set Date 4 HCA: ' . $meterData->consumption_at_set_date_4_hca . "\n";
            echo 'Consumption at Set Date 5 HCA: ' . $meterData->consumption_at_set_date_5_hca . "\n";
            echo 'Consumption at Set Date 6 HCA: ' . $meterData->consumption_at_set_date_6_hca . "\n";
            echo 'Consumption at Set Date 7 HCA: ' . $meterData->consumption_at_set_date_7_hca . "\n";
            echo 'Consumption at Set Date 8 HCA: ' . $meterData->consumption_at_set_date_8_hca . "\n";
            echo 'Consumption at Set Date 9 HCA: ' . $meterData->consumption_at_set_date_9_hca . "\n";
            echo 'Consumption at Set Date 10 HCA: ' . $meterData->consumption_at_set_date_10_hca . "\n";
            echo 'Consumption at Set Date 11 HCA: ' . $meterData->consumption_at_set_date_11_hca . "\n";
            echo 'Consumption at Set Date 12 HCA: ' . $meterData->consumption_at_set_date_12_hca . "\n";
            echo 'Consumption at Set Date 13 HCA: ' . $meterData->consumption_at_set_date_13_hca . "\n";
            echo 'Consumption at Set Date 14 HCA: ' . $meterData->consumption_at_set_date_14_hca . "\n";
            echo 'Consumption at Set Date 15 HCA: ' . $meterData->consumption_at_set_date_15_hca . "\n";
            echo 'Consumption at Set Date 16 HCA: ' . $meterData->consumption_at_set_date_16_hca . "\n";
            echo 'Consumption at Set Date 17 HCA: ' . $meterData->consumption_at_set_date_17_hca . "\n";
        }
        
        if($manufacturer === '12fa'){ # DWZ, Lorenz
            echo 'consumption_at_history_1_m3 :' . $meterData->consumption_at_history_1_m3 . "\n";
            echo 'consumption_at_history_2_m3 :' . $meterData->consumption_at_history_2_m3 . "\n";
            echo 'consumption_at_history_3_m3 :' . $meterData->consumption_at_history_3_m3 . "\n";
            echo 'consumption_at_history_4_m3 :' . $meterData->consumption_at_history_4_m3 . "\n";
            echo 'consumption_at_history_5_m3 :' . $meterData->consumption_at_history_5_m3 . "\n";
            echo 'consumption_at_history_6_m3 :' . $meterData->consumption_at_history_6_m3 . "\n";
            echo 'consumption_at_history_7_m3 :' . $meterData->consumption_at_history_7_m3 . "\n";
            echo 'consumption_at_history_8_m3 :' . $meterData->consumption_at_history_8_m3 . "\n";
            echo 'consumption_at_history_9_m3 :' . $meterData->consumption_at_history_9_m3 . "\n";
            echo 'consumption_at_history_10_m3 :' . $meterData->consumption_at_history_10_m3 . "\n";
            echo 'consumption_at_history_11_m3 :' . $meterData->consumption_at_history_11_m3 . "\n";
            echo 'consumption_at_history_12_m3 :' . $meterData->consumption_at_history_12_m3 . "\n";
            echo 'consumption_at_history_13_m3 :' . $meterData->consumption_at_history_13_m3 . "\n";
            echo 'consumption_at_history_14_m3 :' . $meterData->consumption_at_history_14_m3 . "\n";
            echo 'consumption_at_history_15_m3 :' . $meterData->consumption_at_history_15_m3 . "\n";
            echo 'history_1_date :' . $meterData->history_1_date . "\n";
            echo 'history_2_date :' . $meterData->history_2_date . "\n";
            echo 'history_3_date :' . $meterData->history_3_date . "\n";
            echo 'history_4_date :' . $meterData->history_4_date . "\n";
            echo 'history_5_date :' . $meterData->history_5_date . "\n";
            echo 'history_6_date :' . $meterData->history_6_date . "\n";
            echo 'history_7_date :' . $meterData->history_7_date . "\n";
            echo 'history_8_date :' . $meterData->history_8_date . "\n";
            echo 'history_9_date :' . $meterData->history_9_date . "\n";
            echo 'history_10_date :' . $meterData->history_10_date . "\n";
            echo 'history_11_date :' . $meterData->history_11_date . "\n";
            echo 'history_12_date :' . $meterData->history_12_date . "\n";
            echo 'history_13_date :' . $meterData->history_13_date . "\n";
            echo 'history_14_date :' . $meterData->history_14_date . "\n";
            echo 'history_15_date :' . $meterData->history_15_date . "\n";
            echo 'total_m3: ' . $meterData->total_m3 . "\n";
        }
    }
}
            
            
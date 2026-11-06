<?php
declare(strict_types=1);

/*
 * Engelmann HCA e2 decoder.
 *
 * Input:
 *   - Base64-encoded wireless M-Bus telegram
 *   - Link-layer CRC bytes already removed
 *   - CI = 0x7A, encryption mode = 5
 *
 * Usage:
 *   HCA_AES_KEY=00112233445566778899AABBCCDDEEFF php decode_hca.php
 *
 * Optional alternative telegram:
 *   HCA_AES_KEY=... php decode_hca.php telegram.txt
 *
 * The key above is an example, not your meter's key.
 */

const DEFAULT_TELEGRAM =
    'zkTFFHhgESExCHpfAMAlRze6cMLR3qtkx8l4tUHA6et4hV4bBZVjOt7D1SJBQEjpdnqnNYqtZ1I/riwryFYbo1effiMnVZZCKO2P7bO97PBg7yPGkDq/LweT3Tj3yGb9nUJ7LE4OwIWG9ShsU8sfrJnILvM99WMiqJ8Ej7guyd+ySW46pKA3UjuOe6m+NImvNxbG56+oXppIV2TKF3Gkb2008I1CAuIwOihtoZ1pFS01X+FcamP1f8mftnSFUCTnhauQQgSGelC6xQVhwP1Z';

function hexBytes(string $bytes): string
{
    return strtoupper(implode(' ', str_split(bin2hex($bytes), 2)));
}

function takeBytes(string $buffer, int &$offset, int $count): string
{
    if ($count < 0 || $offset + $count > strlen($buffer)) {
        throw new RuntimeException(
            "Truncated data at offset {$offset}; need {$count} bytes."
        );
    }

    $result = substr($buffer, $offset, $count);
    $offset += $count;
    return $result;
}

function unsignedLE(string $bytes): int
{
    $value = 0;
    for ($i = 0; $i < strlen($bytes); $i++) {
        $value |= ord($bytes[$i]) << (8 * $i);
    }
    return $value;
}

function signedLE(string $bytes): int
{
    $value = unsignedLE($bytes);
    $bits = strlen($bytes) * 8;

    if (($value & (1 << ($bits - 1))) !== 0) {
        $value -= 1 << $bits;
    }

    return $value;
}

function meterId(string $bytes): string
{
    $id = strtoupper(bin2hex(strrev($bytes)));

    if (!ctype_digit($id)) {
        throw new RuntimeException('Meter identification is not valid BCD.');
    }

    return $id;
}

function manufacturerName(string $bytes): string
{
    $value = unsignedLE($bytes) & 0x7FFF;

    return chr((($value >> 10) & 31) + 64)
        . chr((($value >> 5) & 31) + 64)
        . chr(($value & 31) + 64);
}

/*
 * M-Bus type G: two-byte date.
 * Return components as well as a formatted date.
 * The raw record is retained separately by the parser.
 */
function decodeDate(string $bytes): array
{
    $lo = ord($bytes[0]);
    $hi = ord($bytes[1]);

    $day = $lo & 0x1F;
    $month = $hi & 0x0F;
    $year = 2000 + (($lo >> 5) | (($hi & 0xF0) >> 1));
    $valid = checkdate($month, $day, $year);

    return [
        'year' => $year,
        'month' => $month,
        'day' => $day,
        'valid' => $valid,
        'date' => $valid
            ? sprintf('%04d-%02d-%02d', $year, $month, $day)
            : null,
    ];
}

/*
 * M-Bus type F: minute, hour, date-low, date-high.
 * No timezone is inferred from the meter's local timestamp.
 */
function decodeDateTime(string $bytes): array
{
    $date = decodeDate(substr($bytes, 2, 2));
    $minuteByte = ord($bytes[0]);
    $hourByte = ord($bytes[1]);

    $minute = $minuteByte & 0x3F;
    $hour = $hourByte & 0x1F;
    $invalidFlag = ($minuteByte & 0x80) !== 0;

    $valid = $date['valid']
        && !$invalidFlag
        && $minute <= 59
        && $hour <= 23;

    return array_merge($date, [
        'hour' => $hour,
        'minute' => $minute,
        'invalid_time_flag' => $invalidFlag,
        'summer_time_flag' => ($hourByte & 0x80) !== 0,
        'valid' => $valid,
        'datetime' => $valid
            ? sprintf('%sT%02d:%02d:00', $date['date'], $hour, $minute)
            : null,
    ]);
}

function decodeState(int $state): array
{
    $definitions = [
        0 => 'Memory/data-store access error',
        1 => 'Unexpected reset: POR, RAM parity, or access violation',
        2 => 'Operating period exceeds 10 years; not a hardware error',
        3 => 'Tamper contact released / drop detected',
        4 => 'Sensor open circuit',
        5 => 'Sensor short circuit',
        6 => 'Watchdog reset; not a hardware error',
        7 => 'Remote sensor connected; not a hardware error',
    ];

    $flags = [];
    foreach ($definitions as $bit => $description) {
        if (($state & (1 << $bit)) !== 0) {
            $flags[] = [
                'bit' => $bit,
                'description' => $description,
            ];
        }
    }

    return [
        'raw' => $state,
        'hex' => sprintf('%02X', $state),
        'flags' => $flags,
    ];
}

/*
 * Parse the fixed record types documented for the HCA e2.
 * Fail explicitly if an unsupported DIF/VIF is encountered.
 */
function parseRecords(string $payload): array
{
    $offset = 0;
    $records = [];
    $values = [];
    $history = [];

    while ($offset < strlen($payload)) {
        $start = $offset;
        $dif = ord(takeBytes($payload, $offset, 1));

        // M-Bus idle/filler byte.
        if ($dif === 0x2F) {
            continue;
        }

        $difes = [];
        $extension = $dif;

        while (($extension & 0x80) !== 0) {
            if (count($difes) >= 10) {
                throw new RuntimeException('Too many DIFE bytes.');
            }

            $extension = ord(takeBytes($payload, $offset, 1));
            $difes[] = $extension;
        }

        $vif = ord(takeBytes($payload, $offset, 1));
        if (($vif & 0x80) !== 0) {
            throw new RuntimeException('VIF extensions are not supported.');
        }

        $sizes = [1 => 1, 2 => 2, 3 => 3, 4 => 4];
        $encoding = $dif & 0x0F;

        if (!isset($sizes[$encoding])) {
            throw new RuntimeException(
                sprintf('Unsupported DIF %02X at offset %d.', $dif, $start)
            );
        }

        $data = takeBytes($payload, $offset, $sizes[$encoding]);

        $storage = ($dif >> 6) & 1;
        foreach ($difes as $index => $dife) {
            $storage |= ($dife & 0x0F) << (1 + 4 * $index);

            if (($dife & 0x70) !== 0) {
                throw new RuntimeException(
                    'Tariff/subunit DIFE fields are outside this decoder scope.'
                );
            }
        }

        $function = ($dif >> 4) & 3;
        $name = null;
        $value = null;

        if ($vif === 0x6D && strlen($data) === 4) {
            $name = $function === 3 ? 'state_datetime' : 'current_datetime';
            $value = decodeDateTime($data);
            $values[$name] = $value;
        } elseif ($vif === 0x6C && strlen($data) === 2) {
            $value = decodeDate($data);

            if ($storage === 1) {
                $name = 'last_billing_date';
                $values[$name] = $value;
            } elseif ($storage === 2) {
                $name = 'history_anchor_date';
                $values[$name] = $value;
            } else {
                throw new RuntimeException('Unexpected date storage number.');
            }
        } elseif ($vif === 0x6E) {
            if ($storage === 0 && strlen($data) === 3) {
                $name = 'current_hca_units';
                $value = unsignedLE($data);
                $values[$name] = $value;
            } elseif ($storage === 1 && strlen($data) === 3) {
                $name = 'last_billing_hca_units';
                $value = unsignedLE($data);
                $values[$name] = $value;
            } elseif ($storage === 2 && strlen($data) === 3) {
                $name = 'history_anchor_hca_units';
                $value = unsignedLE($data);
                $history[] = [
                    'index' => 1,
                    'kind' => 'absolute',
                    'hca_units' => $value,
                ];
                $values[$name] = $value;
            } elseif ($storage >= 3 && strlen($data) === 2) {
                $name = 'history_increment';
                $value = signedLE($data);
                $history[] = [
                    'index' => $storage - 1,
                    'kind' => 'increment',
                    'hca_units' => $value,
                ];
            } else {
                throw new RuntimeException('Unexpected HCA units record.');
            }
        } elseif (
            $dif === 0x31 &&
            $vif === 0x7F &&
            strlen($data) === 1
        ) {
            $name = 'state';
            $value = decodeState(ord($data));
            $values[$name] = $value;
        } else {
            throw new RuntimeException(
                sprintf(
                    'Unsupported record at offset %d: DIF=%02X VIF=%02X.',
                    $start,
                    $dif,
                    $vif
                )
            );
        }

        $records[] = [
            'offset' => $start,
            'name' => $name,
            'dif' => sprintf('%02X', $dif),
            'dife' => array_map(
                static fn(int $v): string => sprintf('%02X', $v),
                $difes
            ),
            'vif' => sprintf('%02X', $vif),
            'storage_number' => $storage,
            'function' => $function,
            'raw_data_hex' => hexBytes($data),
            'value' => $value,
        ];
    }

    return [
        'values' => $values,
        'half_monthly_history' => $history,
        'records' => $records,
    ];
}

function decodeTelegram(string $base64, string $keyHex): array
{
    if (PHP_INT_SIZE < 8) {
        throw new RuntimeException('Use a 64-bit PHP build.');
    }

    if (!extension_loaded('openssl')) {
        throw new RuntimeException('The PHP OpenSSL extension is required.');
    }

    $keyHex = trim($keyHex);
    if (!preg_match('/^[0-9a-fA-F]{32}$/D', $keyHex)) {
        throw new InvalidArgumentException(
            'AES key must contain exactly 32 hexadecimal characters.'
        );
    }

    $normalized = preg_replace('/\s+/', '', $base64);
    $frame = base64_decode($normalized, true);

    if ($frame === false || strlen($frame) < 15) {
        throw new InvalidArgumentException('Invalid or truncated Base64 frame.');
    }

    $length = ord($frame[0]);

    if (strlen($frame) !== $length + 1) {
        throw new RuntimeException(
            'Expected a CRC-stripped format-A frame of L+1 bytes. '
            . 'CRC-bearing frames and other framing formats are unsupported.'
        );
    }

    $control = ord($frame[1]);
    $manufacturer = substr($frame, 2, 2);
    $address = substr($frame, 4, 6);
    $ci = ord($frame[10]);

    if ($manufacturer !== hex2bin('C514') || ord($address[5]) !== 0x08) {
        throw new RuntimeException('Not an expected Engelmann HCA telegram.');
    }

    if ($ci !== 0x7A) {
        throw new RuntimeException(
            sprintf(
                'CI=%02X is unsupported; this script requires CI=7A.',
                $ci
            )
        );
    }

    $access = ord($frame[11]);
    $status = ord($frame[12]);
    $configBytes = substr($frame, 13, 2);
    $config = unsignedLE($configBytes);

    $mode = ($config >> 8) & 0x1F;
    $blocks = ($config >> 4) & 0x0F;

    if ($mode !== 5) {
        throw new RuntimeException(
            "Encryption mode {$mode} is unsupported; expected mode 5."
        );
    }

    $body = substr($frame, 15);
    $encryptedLength = $blocks * 16;

    if ($blocks === 0 || strlen($body) < $encryptedLength) {
        throw new RuntimeException('Invalid encrypted-block count or length.');
    }

    $iv = $manufacturer . $address . str_repeat(chr($access), 8);

    $decrypted = openssl_decrypt(
        substr($body, 0, $encryptedLength),
        'aes-128-cbc',
        hex2bin($keyHex),
        OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING,
        $iv
    );

    if ($decrypted === false) {
        throw new RuntimeException('OpenSSL AES decryption failed.');
    }

    if (substr($decrypted, 0, 2) !== "\x2F\x2F") {
        throw new RuntimeException(
            'Decryption verification failed: expected 2F 2F. '
            . 'Check the AES key, telegram bytes, and framing.'
        );
    }

    // Preserve any unencrypted tail after the declared encrypted blocks.
    $plaintext = $decrypted . substr($body, $encryptedLength);
    $parsed = parseRecords(substr($plaintext, 2));

    return [
        'header' => [
            'frame_bytes' => strlen($frame),
            'l_field' => $length,
            'crc_bytes_present' => false,
            'control_hex' => sprintf('%02X', $control),
            'manufacturer' => manufacturerName($manufacturer),
            'meter_id' => meterId(substr($address, 0, 4)),
            'version_hex' => sprintf('%02X', ord($address[4])),
            'device_type_hex' => sprintf('%02X', ord($address[5])),
            'ci_hex' => sprintf('%02X', $ci),
            'access_counter' => $access,
            'access_counter_hex' => sprintf('%02X', $access),
            'transport_status_hex' => sprintf('%02X', $status),
            'configuration_wire_hex' => hexBytes($configBytes),
            'configuration_word_hex' => sprintf('%04X', $config),
            'encryption_mode' => $mode,
            'encrypted_blocks' => $blocks,
            'encrypted_bytes' => $encryptedLength,
            'iv_hex' => hexBytes($iv),
        ],
        'decryption_check_passed' => true,
        'plaintext_payload_hex' => hexBytes($plaintext),
        ...$parsed,
    ];
}

try {
    $key = getenv('HCA_AES_KEY');
    if ($key === false || trim($key) === '') {
        throw new RuntimeException(
            'Set HCA_AES_KEY to your 32-character hexadecimal AES key.'
        );
    }

    $base64 = DEFAULT_TELEGRAM;

    if (isset($argv[1])) {
        $contents = file_get_contents($argv[1]);
        if ($contents === false) {
            throw new RuntimeException('Cannot read telegram input file.');
        }
        $base64 = $contents;
    }

    $result = decodeTelegram($base64, $key);

    echo json_encode(
        $result,
        JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
    ), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, 'Error: ' . $error->getMessage() . PHP_EOL);
    exit(1);
}
<?php

/**
 * TTS для виджета контейнеров: Edge (WebSocket) → Google Translate → null.
 */

const EDGE_TTS_TRUSTED_TOKEN = '6A5AA1D4EAFF4E9FB37E23D68491D6F4';
const EDGE_TTS_CHROMIUM_VERSION = '131.0.2903.112';
const EDGE_TTS_SEC_MS_GEC_VERSION = '1-' . EDGE_TTS_CHROMIUM_VERSION;

function synthesizeContainersTtsMp3(string $text, string $voice = 'ru-RU-DmitryNeural'): ?string
{
    $text = trim(preg_replace('/\s+/u', ' ', $text));
    if ($text === '') {
        return null;
    }

    if (mb_strlen($text) > 220) {
        $text = mb_substr($text, 0, 220);
    }

    $provider = strtolower(trim((string)(getenv('TTS_PROVIDER') ?: 'auto')));

    if ($provider === 'google') {
        return synthesizeGoogleTtsMp3($text);
    }

    if ($provider !== 'google') {
        $edge = synthesizeEdgeTtsMp3($text, $voice);
        if ($edge !== null) {
            return $edge;
        }
    }

    if ($provider === 'edge') {
        return null;
    }

    return synthesizeGoogleTtsMp3($text);
}

function edgeTtsGenerateSecMsGec(): string
{
    $winEpoch = 11644473600;
    $ticks = time() + $winEpoch;
    $ticks -= $ticks % 300;
    $ticks = (int)round($ticks * 10000000);

    return strtoupper(hash('sha256', $ticks . EDGE_TTS_TRUSTED_TOKEN));
}

function edgeTtsConnectionId(): string
{
    return bin2hex(random_bytes(16));
}

function edgeTtsDateString(): string
{
    return gmdate('D M d Y H:i:s') . ' GMT+0000 (Coordinated Universal Time)';
}

function edgeTtsEscapeXml(string $text): string
{
    return htmlspecialchars($text, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

function edgeTtsSsml(string $text, string $voice): string
{
    $escaped = edgeTtsEscapeXml($text);

    return "<speak version='1.0' xmlns='http://www.w3.org/2001/10/synthesis' xml:lang='ru-RU'>"
        . "<voice name='" . edgeTtsEscapeXml($voice) . "'>"
        . "<prosody rate='-5.00%' pitch='-2.00%'>{$escaped}</prosody>"
        . '</voice></speak>';
}

function synthesizeEdgeTtsMp3(string $text, string $voice): ?string
{
    if (!function_exists('stream_socket_client')) {
        return null;
    }

    $connectionId = edgeTtsConnectionId();
    $secGec = edgeTtsGenerateSecMsGec();
    $path = '/consumer/speech/synthesize/readaloud/edge/v1'
        . '?TrustedClientToken=' . EDGE_TTS_TRUSTED_TOKEN
        . '&ConnectionId=' . $connectionId
        . '&Sec-MS-GEC=' . $secGec
        . '&Sec-MS-GEC-Version=' . rawurlencode(EDGE_TTS_SEC_MS_GEC_VERSION);

    $major = explode('.', EDGE_TTS_CHROMIUM_VERSION)[0];
    $ua = "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 "
        . "(KHTML, like Gecko) Chrome/{$major}.0.0.0 Safari/537.36 Edg/{$major}.0.0.0";

    $socket = @stream_socket_client(
        'ssl://speech.platform.bing.com:443',
        $errno,
        $errstr,
        12,
        STREAM_CLIENT_CONNECT,
        stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
                'peer_name' => 'speech.platform.bing.com',
            ],
        ])
    );

    if (!$socket) {
        error_log('edge tts connect failed: ' . $errstr);
        return null;
    }

    stream_set_timeout($socket, 12);

    $headers = [
        "GET {$path} HTTP/1.1",
        'Host: speech.platform.bing.com',
        'Upgrade: websocket',
        'Connection: Upgrade',
        'Pragma: no-cache',
        'Cache-Control: no-cache',
        'User-Agent: ' . $ua,
        'Origin: chrome-extension://jdiccldimpdaibmpdkjnbmckianbfold',
        'Sec-WebSocket-Version: 13',
        'Accept-Encoding: gzip, deflate, br',
        'Accept-Language: ru-RU,ru;q=0.9,en;q=0.8',
        'Cookie: muid=' . strtoupper(bin2hex(random_bytes(16))) . ';',
    ];

    $wsKey = base64_encode(random_bytes(16));
    $headers[] = 'Sec-WebSocket-Key: ' . $wsKey;

    fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n");

    $handshake = '';
    while (($line = fgets($socket)) !== false) {
        $handshake .= $line;
        if ($line === "\r\n") {
            break;
        }
    }

    if (strpos($handshake, ' 101 ') === false) {
        fclose($socket);
        error_log('edge tts handshake failed: ' . substr($handshake, 0, 200));
        return null;
    }

    $configMsg = "X-Timestamp:" . edgeTtsDateString() . "\r\n"
        . "Content-Type:application/json; charset=utf-8\r\n"
        . "Path:speech.config\r\n\r\n"
        . '{"context":{"synthesis":{"audio":{"metadataoptions":{"sentenceBoundaryEnabled":"false","wordBoundaryEnabled":"false"},'
        . '"outputFormat":"audio-24khz-48kbitrate-mono-mp3"}}}}';

    $ssml = edgeTtsSsml($text, $voice);
    $ssmlMsg = 'X-RequestId:' . edgeTtsConnectionId() . "\r\n"
        . "Content-Type:application/ssml+xml\r\n"
        . 'X-Timestamp:' . edgeTtsDateString() . "Z\r\n"
        . "Path:ssml\r\n\r\n"
        . $ssml;

    edgeTtsWsSendText($socket, $configMsg);
    edgeTtsWsSendText($socket, $ssmlMsg);

    $audio = '';
    $deadline = time() + 12;

    while (time() < $deadline) {
        $frame = edgeTtsWsReadFrame($socket);
        if ($frame === null) {
            break;
        }

        if ($frame['opcode'] === 0x8) {
            break;
        }

        if ($frame['opcode'] === 0x1) {
            if (strpos($frame['payload'], 'Path:turn.end') !== false) {
                break;
            }
            continue;
        }

        if ($frame['opcode'] === 0x2 && $frame['payload'] !== '') {
            $payload = $frame['payload'];
            if (strlen($payload) < 2) {
                continue;
            }
            $headerLen = unpack('n', substr($payload, 0, 2))[1];
            if ($headerLen > 0 && strlen($payload) > $headerLen + 2) {
                $headerBlock = substr($payload, 2, $headerLen);
                $audioChunk = substr($payload, 2 + $headerLen);
                if (stripos($headerBlock, 'Path:audio') !== false && $audioChunk !== '') {
                    $audio .= $audioChunk;
                }
            }
        }
    }

    fclose($socket);

    return strlen($audio) >= 128 ? $audio : null;
}

function edgeTtsWsSendText($socket, string $payload): void
{
    edgeTtsWsSendFrame($socket, 0x1, $payload);
}

function edgeTtsWsSendFrame($socket, int $opcode, string $payload): void
{
    $finOpcode = 0x80 | ($opcode & 0x0F);
    $len = strlen($payload);
    $frame = chr($finOpcode);

    $maskKey = random_bytes(4);

    if ($len < 126) {
        $frame .= chr(0x80 | $len);
    } elseif ($len < 65536) {
        $frame .= chr(0x80 | 126) . pack('n', $len);
    } else {
        $frame .= chr(0x80 | 127) . pack('J', $len);
    }

    $frame .= $maskKey;
    for ($i = 0; $i < $len; $i++) {
        $frame .= $payload[$i] ^ $maskKey[$i % 4];
    }

    fwrite($socket, $frame);
}

function edgeTtsWsReadFrame($socket): ?array
{
    $header = fread($socket, 2);
    if ($header === false || strlen($header) < 2) {
        return null;
    }

    $b1 = ord($header[0]);
    $b2 = ord($header[1]);
    $opcode = $b1 & 0x0F;
    $masked = (bool)($b2 & 0x80);
    $len = $b2 & 0x7F;

    if ($len === 126) {
        $ext = fread($socket, 2);
        if ($ext === false || strlen($ext) < 2) {
            return null;
        }
        $len = unpack('n', $ext)[1];
    } elseif ($len === 127) {
        $ext = fread($socket, 8);
        if ($ext === false || strlen($ext) < 8) {
            return null;
        }
        $parts = unpack('J', $ext);
        $len = $parts[1];
    }

    $mask = '';
    if ($masked) {
        $mask = fread($socket, 4);
        if ($mask === false || strlen($mask) < 4) {
            return null;
        }
    }

    $payload = '';
    $remaining = $len;
    while ($remaining > 0) {
        $chunk = fread($socket, $remaining);
        if ($chunk === false || $chunk === '') {
            return null;
        }
        $payload .= $chunk;
        $remaining -= strlen($chunk);
    }

    if ($masked && $mask !== '') {
        $unmasked = '';
        for ($i = 0; $i < $len; $i++) {
            $unmasked .= $payload[$i] ^ $mask[$i % 4];
        }
        $payload = $unmasked;
    }

    return ['opcode' => $opcode, 'payload' => $payload];
}

function synthesizeGoogleTtsMp3(string $text): ?string
{
    if (!function_exists('curl_init')) {
        return null;
    }

    $url = 'https://translate.google.com/translate_tts?' . http_build_query([
        'ie' => 'UTF-8',
        'client' => 'tw-ob',
        'tl' => 'ru',
        'q' => $text,
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => [
            'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0.0.0 Safari/537.36',
            'Referer: https://translate.google.com/',
        ],
    ]);

    $body = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $type = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    curl_close($ch);

    if ($body === false || $code < 200 || $code >= 300 || strlen($body) < 128) {
        return null;
    }

    if ($type !== '' && stripos($type, 'audio') === false) {
        return null;
    }

    return $body;
}

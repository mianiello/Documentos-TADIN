<?php
// Script de Diagnóstico Forense Binario
function createHeader($cmd, $session_id) {
    $buf = pack('v', $cmd) . pack('v', 0) . pack('v', $session_id) . pack('v', 0);
    $chksum = 0;
    for ($i = 0; $i < strlen($buf); $i += 2) {
        $chksum += ($i == strlen($buf) - 1) ? ord($buf[$i]) : ord($buf[$i]) + (ord($buf[$i + 1]) << 8);
    }
    while ($chksum > 0xFFFF) $chksum = ($chksum & 0xFFFF) + ($chksum >> 16);
    $chksum = ~$chksum & 0xFFFF;
    return pack('v', $cmd) . pack('v', $chksum) . pack('v', $session_id) . pack('v', 0);
}

echo "<h2 style='font-family: sans-serif;'>Radiografía Binaria de la Última Fichada</h2>";

$socket = @fsockopen("udp://192.168.1.171", 4370, $errno, $errstr, 5);
if (!$socket) die("Error de conexión al reloj.");
stream_set_timeout($socket, 2);

// 1. Saludo (Handshake)
fwrite($socket, createHeader(1000, 0));
$res = fread($socket, 1024);
$session_id = unpack('vcmd/vchk/vsession', substr($res, 0, 6))['session'];

// 2. Pedir toda la memoria
fwrite($socket, createHeader(13, $session_id));
$bytes = '';
while (true) {
    $res = fread($socket, 1024);
    if (empty($res)) break;
    $hdr = unpack('vcmd', substr($res, 0, 2));
    if ($hdr['cmd'] == 1500) continue;
    if ($hdr['cmd'] == 2000) { if (strlen($res) > 8) $bytes .= substr($res, 8); break; }
    if (strlen($res) > 8) $bytes .= substr($res, 8);
}

// 3. Extraer solo los últimos 40 bytes (La fichada de Mili)
$tamano = 40; 
if (strlen($bytes) >= $tamano) {
    $ultimo_registro = substr($bytes, -$tamano); 
    echo "<div style='font-family: monospace; font-size: 18px; background: #000; color: #0f0; padding: 20px; border-radius: 5px; word-break: break-all;'>";
    echo bin2hex($ultimo_registro);
    echo "</div>";
} else {
    echo "No hay suficientes datos en la memoria.";
}
?>
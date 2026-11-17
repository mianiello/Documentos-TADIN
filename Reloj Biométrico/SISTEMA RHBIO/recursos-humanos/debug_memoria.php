<?php
// Generador de cabeceras ZK a bajo nivel
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

$ip = '192.168.1.171';
$port = 4370;

echo "<div style='font-family: monospace; background: #1e1e1e; color: #00ff00; padding: 20px;'>";
echo "<h3>[ INICIANDO VOLCADO DE MEMORIA HEXADECIMAL ]</h3>";

$socket = @fsockopen("udp://$ip", $port, $errno, $errstr, 5);
if (!$socket) die("Error de socket");
stream_set_timeout($socket, 2);

// 1. Handshake
fwrite($socket, createHeader(1000, 0));
$res = fread($socket, 1024);
if (strlen($res) >= 8) {
    $hdr = unpack('vcmd/vchk/vsession', substr($res, 0, 6));
    $session_id = $hdr['session'];
    echo "Handshake OK -> Session ID: $session_id<br><br>";

    // 2. Extraer memoria (Comando 13)
    fwrite($socket, createHeader(13, $session_id));
    $datos_crudos = '';
    while ($res = fread($socket, 1024)) {
        $datos_crudos .= $res;
        $meta = stream_get_meta_data($socket);
        if ($meta['timed_out'] || strlen($res) < 1024) break;
    }

    echo "Total de bytes recibidos: " . strlen($datos_crudos) . "<br>";
    echo "=========================================<br>";
    
    // Convertir a Hexadecimal y separar cada 32 caracteres (16 bytes)
    $hex = bin2hex($datos_crudos);
    for ($i = 0; $i < strlen($hex); $i += 32) {
        echo substr($hex, $i, 32) . "<br>";
    }
} else {
    echo "Fallo el Handshake.";
}
echo "</div>";
?>
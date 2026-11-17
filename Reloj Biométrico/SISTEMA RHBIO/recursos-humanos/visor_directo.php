<?php
// Visor Directo de Memoria - Lector Forense (Bypass MySQL)
error_reporting(E_ALL);
ini_set('display_errors', 1);

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

function decodeTime($t) {
    $t = (int)$t;
    $second = $t % 60; $t = (int)($t / 60);
    $minute = $t % 60; $t = (int)($t / 60);
    $hour = $t % 24;   $t = (int)($t / 24);
    $day = $t % 31 + 1;  $t = (int)($t / 31);
    $month = $t % 12 + 1; $t = (int)($t / 12);
    $year = floor($t + 2000);
    return sprintf("%04d-%02d-%02d %02d:%02d:%02d", $year, $month, $day, $hour, $minute, $second);
}

$ip = '192.168.1.171';
$port = 4370;

echo "<div style='font-family: Segoe UI, sans-serif; padding: 30px; max-width: 800px; margin: auto;'>";
echo "<h2 style='color: #2c3e50;'>Visor Directo de Hardware ZKTeco</h2>";
echo "<p style='color: #7f8c8d;'>Leyendo la memoria flash en tiempo real...</p>";

$socket = @fsockopen("udp://$ip", $port, $errno, $errstr, 5);
if (!$socket) die("<h3 style='color:red;'>Error de red: No se pudo conectar.</h3>");
stream_set_timeout($socket, 2);

// 1. Handshake
fwrite($socket, createHeader(1000, 0));
$res = fread($socket, 1024);
if(strlen($res) < 8) die("<h3 style='color:red;'>El reloj no responde al saludo.</h3>");
$session_id = unpack('vcmd/vchk/vsession', substr($res, 0, 6))['session'];

// 2. Extraer TODA la memoria
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

// 3. Procesar e Imprimir Tabla
echo "<table style='width: 100%; border-collapse: collapse; box-shadow: 0 0 10px rgba(0,0,0,0.1);'>";
echo "<tr style='background: #2980b9; color: white; text-align: left;'>
        <th style='padding: 12px;'>ID en Reloj</th>
        <th style='padding: 12px;'>Fecha y Hora Exacta</th>
        <th style='padding: 12px;'>Acción</th>
      </tr>";

$tamano_registro = 40; // El tamaño fijo que descubrimos
$data = $bytes;
$encontrados = 0;

while (strlen($data) >= $tamano_registro) {
    $registro_raw = substr($data, 0, $tamano_registro);
    $data = substr($data, $tamano_registro);
    
    // Extracción limpia basada en la radiografía
    $id_bytes = substr($registro_raw, 2, 24);
    $id = (int)trim(str_replace("\0", '', $id_bytes));
    
    $state = ord($registro_raw[26]);
    $tipo_str = ($state == 0) ? "Entrada" : (($state == 1) ? "Salida" : "Código: $state");
    
    $timestamp_raw = unpack('V', substr($registro_raw, 28, 4))[1];
    $fecha = decodeTime($timestamp_raw);
    
    if ($id > 0) {
        echo "<tr style='border-bottom: 1px solid #ddd; background: #fff;'>
                <td style='padding: 12px; font-weight: bold; color: #2c3e50;'>$id</td>
                <td style='padding: 12px;'>$fecha</td>
                <td style='padding: 12px;'>$tipo_str</td>
              </tr>";
        $encontrados++;
    }
}

if ($encontrados == 0) {
    echo "<tr><td colspan='3' style='padding: 20px; text-align: center; color: #e74c3c; font-weight: bold;'>
          LA MEMORIA DEL RELOJ ESTÁ VACÍA (0 Bytes de registros).
          </td></tr>";
}

echo "</table>";
echo "<p style='margin-top: 20px; font-size: 18px;'>Total de fichadas extraídas del hardware: <strong>$encontrados</strong></p>";
echo "</div>";

// Desconectar
fwrite($socket, createHeader(1001, $session_id));
fclose($socket);
?>
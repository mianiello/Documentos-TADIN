<?php
// Script de diagnóstico profundo de puertos
$ip = '192.168.1.171';
$puerto = 4370;

echo "<div style='font-family: Segoe UI, sans-serif; padding: 30px; max-width: 600px; margin: auto; background: #f8f9fa; border-radius: 8px;'>";
echo "<h2 style='color: #2c3e50;'>Diagnóstico de Puertos ZKTeco</h2>";
echo "<hr style='border: 1px solid #ddd;'>";

// 1. Prueba TCP
echo "<h3>1. Test TCP Puro (El protocolo actual)</h3>";
$tcp = @fsockopen("tcp://$ip", $puerto, $errno_tcp, $errstr_tcp, 3);
if ($tcp) {
    echo "<span style='color: #27ae60; font-weight: bold;'>ÉXITO: El puerto TCP 4370 está ABIERTO.</span><br>";
    fclose($tcp);
} else {
    echo "<span style='color: #c0392b; font-weight: bold;'>ERROR TCP: No responde.</span><br>";
    echo "<i>Código de error de Windows: [$errno_tcp] $errstr_tcp</i><br>";
}

// 2. Prueba UDP
echo "<h3>2. Test UDP Puro (El estándar antiguo)</h3>";
$udp = @fsockopen("udp://$ip", $puerto, $errno_udp, $errstr_udp, 3);
if ($udp) {
    echo "<span style='color: #27ae60; font-weight: bold;'>ÉXITO: El puerto UDP 4370 está ABIERTO.</span><br>";
    fclose($udp);
} else {
    echo "<span style='color: #c0392b; font-weight: bold;'>ERROR UDP: No responde.</span><br>";
    echo "<i>Código de error de Windows: [$errno_udp] $errstr_udp</i><br>";
}
echo "</div>";
?>
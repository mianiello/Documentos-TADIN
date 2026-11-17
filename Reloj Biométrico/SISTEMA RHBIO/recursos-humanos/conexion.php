<?php
$servidor = "localhost";
$usuario = "root";       // XAMPP usa 'root' por defecto
$password = "";          // XAMPP no tiene contraseña por defecto
$base_datos = "rrhh_biometrico";

// Crear conexión
$conn = new mysqli($servidor, $usuario, $password, $base_datos);

// Verificar conexión
if ($conn->connect_error) {
    die("La conexión a la base de datos falló: " . $conn->connect_error);
}
// Si todo va bien, no muestra nada en pantalla, solo se conecta silenciosamente.
?>
<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo "<div style='font-family: monospace; background: #1e1e1e; color: #ecf0f1; padding: 20px; border-radius: 5px;'>";
echo "<h3 style='color: #3498db;'>[ LEYENDO DATOS DIRECTO DE ZKTIME.NET ]</h3>";

require_once 'conexion.php'; 

$ruta_zktime = 'C:/Program Files (x86)/ZKTimeNet/ZKTimeNet.db'; 

echo "-> 1. Conectando al archivo SQLite: $ruta_zktime...<br>";

try {
    $zk_db = new PDO('sqlite:' . $ruta_zktime);
    $zk_db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "<span style='color: #2ecc71;'>-> ÉXITO: Conexión establecida a la base de datos local.</span><br><br>";

    // =========================================================================
    // DICCIONARIO DE IDs REALES vs NÚMEROS VISIBLES
    // =========================================================================
    echo "<div style='background: #2c3e50; padding: 15px; border-left: 4px solid #f1c40f; margin-bottom: 20px;'>";
    echo "<strong style='color: #f1c40f;'>--- DICCIONARIO DE EMPLEADOS EN ZKTIME ---</strong><br>";
    echo "<span style='color: #bdc3c7; font-size: 0.9em;'>Use el 'ID Real' exacto al dar de alta o editar empleados en HR Bio.</span><br><br>";
    
    try {
        // Consultamos la tabla correcta: hr_employee
        $stmt_users = $zk_db->query("SELECT id, emp_pin, emp_firstname, emp_lastname FROM hr_employee ORDER BY id ASC");
        $usuarios_zk = $stmt_users->fetchAll(PDO::FETCH_ASSOC);
        
        echo "<table style='color: white; font-family: monospace; border-collapse: collapse; width: 100%; max-width: 550px;'>";
        echo "<tr style='border-bottom: 1px solid #7f8c8d; color: #95a5a6;'>
                <th style='text-align: left; padding: 5px;'>ID Real (HR Bio)</th>
                <th style='text-align: left; padding: 5px;'>N° Visible ZK</th>
                <th style='text-align: left; padding: 5px;'>Nombre Completo</th>
              </tr>";
        
        foreach ($usuarios_zk as $usr) {
            $id_real = $usr['id'];
            $num_visible = $usr['emp_pin'];
            $nombre_zk = trim($usr['emp_firstname'] . ' ' . $usr['emp_lastname']);
            if (empty($nombre_zk)) $nombre_zk = 'Sin Nombre';
            
            echo "<tr>
                    <td style='color: #f1c40f; font-weight: bold; padding: 5px;'>$id_real</td>
                    <td style='padding: 5px;'>$num_visible</td>
                    <td style='padding: 5px;'>$nombre_zk</td>
                  </tr>";
        }
        echo "</table>";
    } catch (Exception $e) {
        echo "<span style='color: #e74c3c;'>No se pudo leer la lista de usuarios: " . $e->getMessage() . "</span><br>";
    }
    echo "</div>";
    // =========================================================================

    echo "-> 2. Extrayendo fichadas...<br>";
    $stmt = $zk_db->query("SELECT emp_id, punch_time, workstate FROM att_punches ORDER BY punch_time ASC");
    $registros = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $cantidad = count($registros);
    echo "-> 3. Se encontraron <strong>$cantidad</strong> fichadas en ZKTime.<br>";

    if ($cantidad > 0) {
        $insertados = 0;
        $duplicados = 0;
        $errores_foraneos = 0;

        foreach ($registros as $row) {
            $id_emp = intval($row['emp_id']);
            $fecha_hora = $row['punch_time'];
            
            $tipo = (empty($row['workstate']) || $row['workstate'] == 0) ? 0 : 1; 

            $check = $conn->query("SELECT id FROM registros_asistencia WHERE id_empleado = $id_emp AND fecha_hora = '$fecha_hora'");

            if ($check->num_rows == 0) {
                $sql = "INSERT INTO registros_asistencia (id_empleado, fecha_hora, tipo) VALUES ($id_emp, '$fecha_hora', $tipo)";
                try {
                    if ($conn->query($sql) === TRUE) {
                        $insertados++;
                    }
                } catch (Exception $e) {
                    $errores_foraneos++;
                    echo "<span style='color: #e67e22;'>-> AVISO: Se ignoró una fichada del ID $id_emp porque no existe en la tabla de empleados de MySQL.</span><br>";
                }
            } else {
                $duplicados++;
            }
        }

        echo "<br><span style='color: #2ecc71; font-weight: bold;'>-> RESULTADO: Se insertaron $insertados registros nuevos en HR Bio.</span><br>";
        if ($duplicados > 0) {
            echo "<span style='color: #95a5a6;'>-> Se omitieron $duplicados registros que ya estaban guardados previamente.</span><br>";
        }
    }

} catch (PDOException $e) {
    echo "<span style='color: #e74c3c;'>-> FATAL: No se pudo leer el archivo SQLite. Detalle: " . $e->getMessage() . "</span><br>";
}

echo "<br><a href='index.php' style='color: #3498db; text-decoration: none; font-weight: bold;'>&larr; Volver al Dashboard</a>";
echo "</div>";
?>
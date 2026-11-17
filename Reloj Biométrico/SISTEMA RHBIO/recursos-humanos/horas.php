<?php
/**
 * ==========================================================================================
 * MÓDULO DE RECURSOS HUMANOS - GESTIÓN Y AUDITORÍA DE HORAS TRABAJADAS (VERSIÓN PRO V4)
 * ==========================================================================================
 * Este script procesa los registros crudos del reloj biométrico y los convierte en
 * horas netas liquidadas. Aplica reglas de negocio estrictas: turnos cortados, 
 * tolerancias de llegada, exclusión de horas extras no autorizadas y auditoría de activos en vivo.
 */

session_start();
if (!isset($_SESSION['admin_logeado']) || $_SESSION['admin_logeado'] !== true) {
    header("Location: login.php");
    exit;
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

date_default_timezone_set('America/Argentina/Buenos_Aires');
require_once 'conexion.php';

// ==========================================================================================
// 1. CARGA DINÁMICA DE CONFIGURACIÓN (Desde la base de datos)
// ==========================================================================================
$res_config = $conn->query("SELECT * FROM configuracion WHERE id = 1");
$config_db = $res_config->fetch_assoc();

define('TURNO_MANANA_INICIO', $config_db['turno1_inicio'] ?? '07:00:00');
define('TURNO_MANANA_FIN',    $config_db['turno1_fin'] ?? '12:00:00');
define('TURNO_TARDE_INICIO',  $config_db['turno2_inicio'] ?? '13:00:00');
define('TURNO_TARDE_FIN',     $config_db['turno2_fin'] ?? '16:30:00');

$tolerancia_minutos = $config_db['tolerancia_minutos'] ?? 15;
$hora_tolerancia = date('H:i:s', strtotime(TURNO_MANANA_INICIO . " + $tolerancia_minutos minutes"));
define('TOLERANCIA_LLEGADA', $hora_tolerancia);

// ==========================================================================================
// 2. EXPORTACIÓN A EXCEL PROFESIONAL
// ==========================================================================================
if (isset($_GET['export']) && $_GET['export'] == 'excel') {
    header("Content-Type: application/vnd.ms-excel; charset=utf-8");
    
    $mes_export = isset($_GET['mes']) ? str_pad(intval($_GET['mes']), 2, '0', STR_PAD_LEFT) : date('m');
    $ano_export = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');
    header("Content-Disposition: attachment; filename=Liquidacion_HRBio_{$mes_export}_{$ano_export}.xls");
    
    header("Pragma: no-cache");
    header("Expires: 0");
    echo "\xEF\xBB\xBF"; 
    
    $mes_excel = isset($_GET['mes']) ? intval($_GET['mes']) : date('n');
    $ano_excel = isset($_GET['ano']) ? intval($_GET['ano']) : date('Y');
    
    $sql_excel = "SELECT e.nombre, DATE(r.fecha_hora) as fecha, MIN(r.fecha_hora) as entrada_real, MAX(r.fecha_hora) as salida_real
                  FROM registros_asistencia r JOIN empleados e ON r.id_empleado = e.id
                  WHERE MONTH(r.fecha_hora) = $mes_excel AND YEAR(r.fecha_hora) = $ano_excel
                  GROUP BY e.id, DATE(r.fecha_hora) ORDER BY fecha DESC, e.nombre ASC";
    $res_excel = $conn->query($sql_excel);
    
    echo "<table style='font-family: \"Segoe UI\", Arial, sans-serif; border-collapse: collapse; width: 100%;'>";
    echo "<tr>
            <th colspan='6' style='background-color: #2c3e50; color: #ffffff; font-size: 16px; font-weight: bold; padding: 15px; text-align: center; border: 1px solid #1a252f;'>
                REPORTE MENSUAL DE ASISTENCIA Y LIQUIDACIÓN - HR BIO (Período: $mes_export / $ano_export)
            </th>
          </tr>";
          
    $th_style = "background-color: #3498db; color: #ffffff; font-weight: bold; text-align: center; padding: 12px; border: 1px solid #2980b9; font-size: 13px;";
    echo "<tr>
            <th style='$th_style text-align: left; width: 220px;'>EMPLEADO</th>
            <th style='$th_style width: 120px;'>FECHA</th>
            <th style='$th_style width: 100px;'>ENTRADA</th>
            <th style='$th_style width: 100px;'>SALIDA</th>
            <th style='$th_style width: 150px;'>HORAS A PAGAR</th>
            <th style='$th_style width: 180px;'>ESTADO DEL TURNO</th>
          </tr>";
    
    $total_minutos_mes = 0;
    $resumen_empleados = []; 
    
    while ($row = $res_excel->fetch_assoc()) {
        $nombre_emp = mb_convert_case(strtolower($row['nombre']), MB_CASE_TITLE, "UTF-8");
        $fecha = date('d/m/Y', strtotime($row['fecha']));
        $entrada = date('H:i', strtotime($row['entrada_real']));
        $salida = ($row['entrada_real'] == $row['salida_real']) ? null : $row['salida_real'];
        $salida_texto = $salida ? date('H:i', strtotime($salida)) : "SIN SALIDA";
        
        $minutos_computables = 0;
        
        $estado = "Incompleto";
        $bg_estado = "#f9ebea"; 
        $txt_estado = "#c0392b"; 
        $border_estado = "#fadbd8";
        
        if ($salida) {
            $t_ent = strtotime($row['entrada_real']);
            $t_sal = strtotime($salida);
            $t_0700 = strtotime($row['fecha'] . " " . TURNO_MANANA_INICIO);
            $t_1200 = strtotime($row['fecha'] . " " . TURNO_MANANA_FIN);
            $t_1300 = strtotime($row['fecha'] . " " . TURNO_TARDE_INICIO);
            $t_1630 = strtotime($row['fecha'] . " " . TURNO_TARDE_FIN);
            $t_0715 = strtotime($row['fecha'] . " " . TOLERANCIA_LLEGADA);

            $cruce_manana = min($t_sal, $t_1200) - max($t_ent, $t_0700);
            $minutos_manana = ($cruce_manana > 0) ? ($cruce_manana / 60) : 0;

            $cruce_tarde = min($t_sal, $t_1630) - max($t_ent, $t_1300);
            $minutos_tarde = ($cruce_tarde > 0) ? ($cruce_tarde / 60) : 0;
            
            $minutos_computables = floor($minutos_manana + $minutos_tarde);
            $total_minutos_mes += $minutos_computables;
            
            if ($minutos_computables == 0) {
                $estado = "Fuera de Horario";
                $bg_estado = "#f2f4f4"; 
                $txt_estado = "#7f8c8d";
                $border_estado = "#d5dbdb";
            } elseif ($t_ent > $t_0715) {
                $estado = "Llegada Tarde";
                $bg_estado = "#fef9e7"; 
                $txt_estado = "#d35400";
                $border_estado = "#fdebd0";
            } else {
                $estado = "Jornada Normal";
                $bg_estado = "#eafaf1"; 
                $txt_estado = "#27ae60";
                $border_estado = "#d5f5e3";
            }
        }
        
        if (!isset($resumen_empleados[$nombre_emp])) {
            $resumen_empleados[$nombre_emp] = ['minutos' => 0, 'tardes' => 0];
        }
        $resumen_empleados[$nombre_emp]['minutos'] += $minutos_computables;
        if ($estado == "Llegada Tarde") {
            $resumen_empleados[$nombre_emp]['tardes'] += 1;
        }
        
        $h_pagar = floor($minutos_computables / 60) . "h " . ($minutos_computables % 60) . "m";
        if($minutos_computables == 0) { $h_pagar = "0h 00m"; }

        $td_style = "padding: 10px; border: 1px solid #bdc3c7; font-size: 13px; vertical-align: middle; color: #2c3e50;";

        echo "<tr>";
        echo "<td style='$td_style font-weight: bold; text-align: left;'>" . $nombre_emp . "</td>";
        echo "<td style='$td_style text-align: center;'>" . $fecha . "</td>";
        echo "<td style='$td_style text-align: center; color: #27ae60; font-weight: bold;'>" . $entrada . "</td>";
        echo "<td style='$td_style text-align: center; color: #c0392b; font-weight: bold;'>" . $salida_texto . "</td>";
        echo "<td style='$td_style text-align: right; font-weight: bold;'>" . $h_pagar . "</td>";
        echo "<td style='$td_style text-align: center; background-color: $bg_estado; color: $txt_estado; border: 1px solid $border_estado; font-weight: bold;'>" . $estado . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    echo "<br><br>";
    echo "<table style='font-family: \"Segoe UI\", Arial, sans-serif; border-collapse: collapse; width: 70%;'>";
    echo "<tr>
            <th colspan='3' style='background-color: #34495e; color: #ffffff; font-size: 14px; font-weight: bold; padding: 12px; text-align: center; border: 1px solid #2c3e50;'>
                RESUMEN DE HORAS Y SANCIONES POR EMPLEADO
            </th>
          </tr>";
    echo "<tr>
            <th style='background-color: #7f8c8d; color: #ffffff; padding: 10px; border: 1px solid #bdc3c7;'>EMPLEADO</th>
            <th style='background-color: #7f8c8d; color: #ffffff; padding: 10px; border: 1px solid #bdc3c7;'>TOTAL HORAS A PAGAR</th>
            <th style='background-color: #7f8c8d; color: #ffffff; padding: 10px; border: 1px solid #bdc3c7;'>CANT. LLEGADAS TARDES</th>
          </tr>";
          
    foreach ($resumen_empleados as $emp => $datos) {
        $h_tot = floor($datos['minutos'] / 60) . "h " . ($datos['minutos'] % 60) . "m";
        if ($datos['minutos'] == 0) $h_tot = "0h 00m";
        
        $color_tardes = ($datos['tardes'] > 0) ? "#d35400" : "#27ae60";
        
        echo "<tr>";
        echo "<td style='padding: 10px; border: 1px solid #bdc3c7; font-weight: bold; font-size: 13px;'>" . $emp . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #bdc3c7; text-align: center; font-weight: bold; font-size: 13px; color: #2c3e50;'>" . $h_tot . "</td>";
        echo "<td style='padding: 10px; border: 1px solid #bdc3c7; text-align: center; font-weight: bold; font-size: 13px; color: $color_tardes;'>" . $datos['tardes'] . "</td>";
        echo "</tr>";
    }
    echo "</table>";
    
    exit; 
}

// ==========================================================================================
// 3. LÓGICA DE TIEMPO INTELIGENTE Y BÚSQUEDA DE ACTIVOS
// ==========================================================================================
$mes_actual = date('n');
$ano_actual = date('Y');

$mes_filtro = isset($_GET['mes']) ? intval($_GET['mes']) : $mes_actual;
$ano_filtro = isset($_GET['ano']) ? intval($_GET['ano']) : $ano_actual;

$meses_es = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$titulo_mes = strtoupper($meses_es[$mes_filtro]) . " " . $ano_filtro;

// NUEVO: Consulta detallada para traer a los empleados que están activos AHORA MISMO
$sql_lista_activos = "SELECT e.nombre, MIN(r.fecha_hora) as hora_entrada 
                      FROM registros_asistencia r 
                      JOIN empleados e ON r.id_empleado = e.id 
                      WHERE DATE(r.fecha_hora) = CURDATE() 
                      GROUP BY e.id, e.nombre 
                      HAVING MIN(r.fecha_hora) = MAX(r.fecha_hora)";
$res_lista_activos = $conn->query($sql_lista_activos);

$activos_hoy = $res_lista_activos ? $res_lista_activos->num_rows : 0;
$array_activos = [];
if ($activos_hoy > 0) {
    while ($row = $res_lista_activos->fetch_assoc()) {
        $array_activos[] = $row;
    }
}

// ==========================================================================================
// 4. EXTRACCIÓN Y CÁLCULO DEL MOTOR DE HORAS PARA LA WEB
// ==========================================================================================
$sql_datos = "SELECT 
                e.nombre, 
                DATE(r.fecha_hora) as fecha, 
                MIN(r.fecha_hora) as entrada_real, 
                MAX(r.fecha_hora) as salida_real
              FROM registros_asistencia r 
              JOIN empleados e ON r.id_empleado = e.id
              WHERE MONTH(r.fecha_hora) = $mes_filtro AND YEAR(r.fecha_hora) = $ano_filtro
              GROUP BY e.id, DATE(r.fecha_hora) 
              ORDER BY fecha DESC, e.nombre ASC";
$res_datos = $conn->query($sql_datos);

$totales_mensuales = [];
$desglose_diario = [];

while ($row = $res_datos->fetch_assoc()) {
    $nombre = $row['nombre'];
    $fecha = $row['fecha'];
    $entrada = $row['entrada_real'];
    $salida = ($row['entrada_real'] == $row['salida_real']) ? null : $row['salida_real'];

    $minutos_manana = 0;
    $minutos_tarde = 0;
    $minutos_computables = 0;
    
    $estado_texto = "Incompleto";
    $estado_clase = "badge-incompleto";

    if ($salida) {
        $t_ent = strtotime($entrada);
        $t_sal = strtotime($salida);
        
        $t_0700 = strtotime("$fecha " . TURNO_MANANA_INICIO);
        $t_1200 = strtotime("$fecha " . TURNO_MANANA_FIN);
        $t_1300 = strtotime("$fecha " . TURNO_TARDE_INICIO);
        $t_1630 = strtotime("$fecha " . TURNO_TARDE_FIN);
        $t_0715 = strtotime("$fecha " . TOLERANCIA_LLEGADA);

        $cruce_manana = min($t_sal, $t_1200) - max($t_ent, $t_0700);
        $minutos_manana = ($cruce_manana > 0) ? ($cruce_manana / 60) : 0;

        $cruce_tarde = min($t_sal, $t_1630) - max($t_ent, $t_1300);
        $minutos_tarde = ($cruce_tarde > 0) ? ($cruce_tarde / 60) : 0;
        
        $minutos_computables = floor($minutos_manana + $minutos_tarde);

        if ($minutos_computables == 0) {
            $estado_texto = "Fuera de Horario";
            $estado_clase = "badge-fuera"; 
        } elseif ($t_ent > $t_0715) {
            $estado_texto = "Llegada Tarde";
            $estado_clase = "badge-tarde";
        } else {
            $estado_texto = "Jornada Normal";
            $estado_clase = "badge-normal";
        }
    }

    if (!isset($totales_mensuales[$nombre])) {
        $totales_mensuales[$nombre] = 0;
    }
    $totales_mensuales[$nombre] += $minutos_computables;

    $desglose_diario[] = [
        'nombre'        => $nombre,
        'fecha'         => $fecha,
        'entrada'       => $entrada,
        'salida'        => $salida,
        'min_manana'    => $minutos_manana,
        'min_tarde'     => $minutos_tarde,
        'minutos_netos' => $minutos_computables,
        'estado'        => $estado_texto,
        'clase'         => $estado_clase
    ];
}

arsort($totales_mensuales);

// ==========================================================================================
// 5. FUNCIONES DE INTERFAZ DE USUARIO (UI)
// ==========================================================================================
function formatoHoras($minutos) {
    if ($minutos <= 0) {
        return "<span class='text-muted' style='opacity: 0.5;'>0h 00m</span>";
    }
    $h = floor($minutos / 60);
    $m = $minutos % 60;
    return "<span class='fw-bold text-dark' style='font-size: 1.1rem;'>{$h}h</span> <span class='text-muted fw-bold'>".str_pad($m, 2, "0", STR_PAD_LEFT)."m</span>";
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Bio - Control y Liquidación de Horas</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { 
            --primary-color: #2c3e50; 
            --accent-color: #3498db; 
            --bg-color: #f4f7f6; 
            --text-color: #2c3e50; 
            --border-color: #ecf0f1; 
        }
        body { font-family: 'Nunito', sans-serif; background-color: var(--bg-color); overflow-x: hidden; color: var(--text-color); }
        
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb:hover { background: #a8a8a8; }

        .sidebar { background-color: var(--primary-color); min-height: 100vh; color: white; position: fixed; width: 250px; top: 0; left: 0; z-index: 1000; box-shadow: 2px 0 15px rgba(0,0,0,0.1);}
        .sidebar-brand { padding: 25px 20px; font-size: 1.4rem; font-weight: 800; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 15px; }
        .sidebar-menu a { color: #aab7c4; text-decoration: none; display: block; padding: 15px 25px; font-size: 1rem; font-weight: 600; transition: all 0.3s ease; }
        .sidebar-menu a i { width: 25px; font-size: 1.1rem; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: #34495e; color: #ffffff; border-left: 4px solid var(--accent-color); }
        
        .main-content { margin-left: 250px; padding: 30px 40px; }
        .page-header-container { background: #ffffff; border-radius: 12px; padding: 25px 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;}
        .page-header-container h2 { font-weight: 800; margin: 0 0 5px 0; font-size: 1.8rem; color: var(--primary-color);}
        
        .header-actions { display: flex; gap: 12px; align-items: center; flex-wrap: wrap;}
        
        .month-selector { 
            border: 1px solid #dfe6e9; 
            border-radius: 8px; 
            padding: 8px 12px; 
            font-weight: 700; 
            color: var(--primary-color); 
            background-color: #ffffff;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            outline: none;
            transition: 0.3s;
        }
        .month-selector:focus, .month-selector:hover { border-color: var(--accent-color); }
        
        /* CSS del nuevo Botón Dinámico de Activos */
        .badge-live-btn { 
            background-color: #1abc9c; 
            color: white; 
            padding: 8px 20px; 
            border-radius: 30px; 
            font-weight: 700; 
            font-size: 0.95rem; 
            display: flex; 
            align-items: center; 
            transition: 0.3s;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 10px rgba(26, 188, 156, 0.2);
        }
        .badge-live-btn:hover {
            background-color: #16a085;
            box-shadow: 0 6px 12px rgba(26, 188, 156, 0.3);
            transform: translateY(-1px);
        }
        
        .card-pro { background: #ffffff; border-radius: 12px; border: 1px solid var(--border-color); box-shadow: 0 4px 20px rgba(0,0,0,0.02); margin-bottom: 25px; overflow: hidden; }
        .card-header-pro { background: #fcfdfd; border-bottom: 1px solid var(--border-color); padding: 20px 25px; font-weight: 800; color: #7f8c8d; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; display: flex; justify-content: space-between; align-items: center; }
        
        .search-box { position: relative; width: 250px; }
        .search-box input { border-radius: 20px; padding-left: 35px; border: 1px solid #e0e6ed; font-size: 0.85rem; box-shadow: none; }
        .search-box i { position: absolute; left: 12px; top: 10px; color: #95a5a6; font-size: 0.9rem; }

        .table-pro { margin-bottom: 0; }
        .table-pro th { color: #95a5a6; font-weight: 800; border-bottom: 2px solid var(--border-color); padding: 20px 25px; font-size: 0.75rem; text-transform: uppercase; letter-spacing: 0.5px; white-space: nowrap;}
        .table-pro td { padding: 18px 25px; vertical-align: middle; font-weight: 600; border-bottom: 1px solid #f8f9fa; }
        .table-hover tbody tr:hover { background-color: #f8fbff; }
        
        .text-null { color: #dcdde1; font-weight: 700; }
        .time-pill { background: #ffffff; border: 1px solid #e0e6ed; padding: 6px 15px; border-radius: 8px; font-family: monospace; font-size: 1rem; color: #34495e; font-weight: 700; box-shadow: 0 2px 4px rgba(0,0,0,0.01); display: inline-block;}
        .detail-text-container { display: flex; flex-direction: column; align-items: flex-end; justify-content: center; }
        .detail-text { font-size: 0.75rem; color: #95a5a6; margin-top: 5px; font-weight: 700;}
        
        .status-badge { padding: 8px 18px; border-radius: 30px; font-size: 0.85rem; font-weight: 800; white-space: nowrap; display: inline-block;}
        .badge-normal { background-color: #eafaf1; color: #27ae60; border: 1px solid #c8e6c9; }
        .badge-tarde { background-color: #fff8e1; color: #f39c12; border: 1px solid #ffeaa7; }
        .badge-incompleto { background-color: #fdfefe; color: #95a5a6; border: 1px solid #e0e6ed; }
        .badge-fuera { background-color: #f2f4f4; color: #7f8c8d; border: 1px solid #d5dbdb; } 
        
        .btn-export { font-size: 0.95rem; padding: 8px 18px; font-weight: 700; border-radius: 8px; transition: 0.3s; display: flex; align-items: center; background: white;}
        .btn-export:hover { background-color: #27ae60; color: white; border-color: #27ae60; }
        .flex-cell { display: flex; align-items: center; gap: 10px; white-space: nowrap;}
    </style>
</head>
<body>

    <div class="sidebar">
        <div class="sidebar-brand text-center">
            <i class="fa-solid fa-fingerprint me-2 text-info"></i> HR Bio
        </div>
        <div class="sidebar-menu">
            <a href="index.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a href="empleados.php"><i class="fa-solid fa-users"></i> Empleados</a>
            <a href="registros.php"><i class="fa-solid fa-history"></i> Registros Crudos</a>
            <a href="horas.php" class="active"><i class="fa-solid fa-calendar-check"></i> Horas Trabajadas</a>
            <a href="configuracion.php"><i class="fa-solid fa-cog"></i> Configuración</a>
            <a href="asistencia.php"><i class="fa-solid fa-border-all"></i> Matriz Mensual</a>
            <hr style="border-color: rgba(255,255,255,0.1); margin: 20px;">
            <a href="login.php?logout=1" class="text-danger fw-bold"><i class="fa-solid fa-power-off"></i> Cerrar Sesión</a>
        </div>
    </div>

    <div class="main-content">
        
        <div class="page-header-container">
            <div>
                <h2>Control de Asistencia y Liquidación</h2>
                <div class="text-muted mt-2" style="font-size: 0.95rem;">
                    <i class="fa-regular fa-clock me-1"></i> Turnos Oficiales: <b class="text-dark"><?php echo date('H:i', strtotime(TURNO_MANANA_INICIO)) . "-" . date('H:i', strtotime(TURNO_MANANA_FIN)); ?></b> y <b class="text-dark"><?php echo date('H:i', strtotime(TURNO_TARDE_INICIO)) . "-" . date('H:i', strtotime(TURNO_TARDE_FIN)); ?></b> 
                    <span class="mx-2">|</span> 
                    <i class="fa-solid fa-stopwatch me-1"></i> Tolerancia: <b class="text-dark"><?php echo $tolerancia_minutos; ?> min</b>
                </div>
            </div>
            
            <div class="header-actions">
                <form method="GET" action="horas.php" class="m-0 p-0">
                    <div class="d-flex gap-2 align-items-center">
                        <i class="fa-solid fa-filter text-muted me-1"></i>
                        <select name="mes" class="month-selector" onchange="this.form.submit()">
                            <?php 
                            for($i = 1; $i <= 12; $i++){
                                $sel = ($i == $mes_filtro) ? "selected" : "";
                                echo "<option value='$i' $sel>{$meses_es[$i]}</option>";
                            }
                            ?>
                        </select>
                        <select name="ano" class="month-selector" onchange="this.form.submit()">
                            <?php 
                            for($i = $ano_actual - 1; $i <= $ano_actual + 1; $i++){
                                $sel = ($i == $ano_filtro) ? "selected" : "";
                                echo "<option value='$i' $sel>$i</option>";
                            }
                            ?>
                        </select>
                    </div>
                </form>

                <a href="horas.php?export=excel&mes=<?php echo $mes_filtro; ?>&ano=<?php echo $ano_filtro; ?>" class="btn btn-outline-success btn-export shadow-sm">
                    <i class="fa-solid fa-file-excel me-2"></i> Excel
                </a>
                
                <!-- BOTÓN INTERACTIVO DE ACTIVOS -->
                <button type="button" class="badge-live-btn" data-bs-toggle="modal" data-bs-target="#modalActivos" title="Ver empleados en la empresa">
                    <i class="fa-solid fa-users-viewfinder me-2 fs-5"></i> 
                    <span><?php echo $activos_hoy; ?> Activos</span>
                    <i class="fa-solid fa-hand-pointer ms-2 opacity-50 fs-6"></i>
                </button>
            </div>
        </div>

        <div class="row">
            
            <div class="col-lg-4">
                <div class="card-pro h-100">
                    <div class="card-header-pro">
                        <div class="flex-cell">
                            <i class="fa-solid fa-vault text-success fs-5"></i> 
                            <span>ACUMULADO MENSUAL (<?php echo $titulo_mes; ?>)</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-pro table-hover">
                                <thead>
                                    <tr>
                                        <th>Empleado</th>
                                        <th class="text-end">Horas a Pagar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (!empty($totales_mensuales)) {
                                        foreach($totales_mensuales as $emp => $mins) {
                                            $nombre_formateado = mb_convert_case(strtolower($emp), MB_CASE_TITLE, "UTF-8");
                                            echo "<tr>";
                                            echo "<td>
                                                    <div class='flex-cell fw-bold'>
                                                        <i class='fa-solid fa-user-tie text-muted'></i>
                                                        <span>{$nombre_formateado}</span>
                                                    </div>
                                                  </td>";
                                            echo "<td class='text-end'>" . formatoHoras($mins) . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr>
                                                <td colspan='2' class='text-center py-5 text-muted'>
                                                    <i class='fa-regular fa-folder-open mb-3 d-block' style='font-size: 3rem; color: #bdc3c7;'></i>
                                                    <h5 class='fw-bold'>Sin registros</h5>
                                                    <p class='mb-0'>No hay horas liquidadas en este mes.</p>
                                                </td>
                                              </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-8">
                <div class="card-pro h-100">
                    <div class="card-header-pro">
                        <div class="flex-cell">
                            <i class="fa-regular fa-calendar-check text-primary fs-5"></i> 
                            <span>DESGLOSE DIARIO (AUDITORÍA DE TURNOS)</span>
                        </div>
                        <div class="search-box">
                            <i class="fa-solid fa-search"></i>
                            <input type="text" id="searchInput" class="form-control form-control-sm" placeholder="Buscar empleado...">
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 65vh; overflow-y: auto;">
                            <table class="table table-pro table-hover" id="tablaAuditoria">
                                <thead style="position: sticky; top: 0; background: #ffffff; z-index: 10; box-shadow: 0 2px 5px rgba(0,0,0,0.04);">
                                    <tr>
                                        <th>Empleado</th>
                                        <th>Fecha</th>
                                        <th class="text-center">Primer Fichaje</th>
                                        <th class="text-center">Último Fichaje</th>
                                        <th class="text-end" style="padding-right: 40px;">Liquidación Neta</th>
                                        <th class="text-center">Estado</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (!empty($desglose_diario)) {
                                        foreach($desglose_diario as $row) {
                                            $nombre_formateado = mb_convert_case(strtolower($row['nombre']), MB_CASE_TITLE, "UTF-8");
                                            $fecha = date('d/m/Y', strtotime($row['fecha']));
                                            
                                            $entrada = !empty($row['entrada']) ? "<span class='time-pill text-success'>" . date('H:i', strtotime($row['entrada'])) . "</span>" : "<span class='text-null'>--:--</span>";
                                            $salida = !empty($row['salida']) ? "<span class='time-pill text-danger'>" . date('H:i', strtotime($row['salida'])) . "</span>" : "<span class='text-null'>--:--</span>";

                                            $detalle_turnos = "";
                                            if ($row['minutos_netos'] > 0) {
                                                $hrs_m = number_format($row['min_manana'] / 60, 1);
                                                $hrs_t = number_format($row['min_tarde'] / 60, 1);
                                                $detalle_turnos = "<span class='detail-text'>(Mañana: {$hrs_m}h &nbsp;|&nbsp; Tarde: {$hrs_t}h)</span>";
                                            }

                                            echo "<tr>";
                                            echo "<td class='fw-bold text-dark fs-6'>{$nombre_formateado}</td>";
                                            echo "<td>
                                                    <div class='flex-cell text-muted fw-bold'>
                                                        <i class='fa-regular fa-calendar-days'></i>
                                                        <span>{$fecha}</span>
                                                    </div>
                                                  </td>";
                                            echo "<td class='text-center'>{$entrada}</td>";
                                            echo "<td class='text-center'>{$salida}</td>";
                                            echo "<td>
                                                    <div class='detail-text-container' style='padding-right: 15px;'>
                                                        <div>" . formatoHoras($row['minutos_netos']) . "</div>
                                                        {$detalle_turnos}
                                                    </div>
                                                  </td>";
                                            echo "<td class='text-center'>
                                                    <span class='status-badge {$row['clase']}'>{$row['estado']}</span>
                                                  </td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr>
                                                <td colspan='6' class='text-center py-5 text-muted'>
                                                    <i class='fa-solid fa-inbox mb-3 d-block' style='font-size: 3rem; color: #bdc3c7;'></i>
                                                    <h5 class='fw-bold'>Sin movimientos</h5>
                                                    <p class='mb-0'>No hay registros diarios para procesar en este período.</p>
                                                </td>
                                              </tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ==============================================================================
         MODAL: LISTADO DE EMPLEADOS ACTIVOS
         ============================================================================== -->
    <div class="modal fade" id="modalActivos" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header text-white border-0" style="background-color: #1abc9c;">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-users-viewfinder me-2"></i> Empleados en Planta (Activos)</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body p-0">
                    <?php if (empty($array_activos)): ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fa-solid fa-user-slash mb-3 d-block" style="font-size: 3rem; color: #bdc3c7;"></i>
                            <h5 class="fw-bold">No hay empleados activos</h5>
                            <p class="mb-0">Todos han registrado su salida o nadie ha ingresado hoy.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="py-3 px-4 text-secondary" style="font-size: 0.85rem;">EMPLEADO</th>
                                        <th class="py-3 px-4 text-secondary text-end" style="font-size: 0.85rem;">HORA DE ENTRADA</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($array_activos as $activo): ?>
                                        <tr>
                                            <td class="py-3 px-4 fw-bold text-dark align-middle">
                                                <div class="d-flex align-items-center">
                                                    <div class="bg-light rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 35px; height: 35px;">
                                                        <i class="fa-regular fa-user text-secondary"></i>
                                                    </div>
                                                    <?php echo htmlspecialchars(mb_convert_case(strtolower($activo['nombre']), MB_CASE_TITLE, "UTF-8")); ?>
                                                </div>
                                            </td>
                                            <td class="py-3 px-4 text-end align-middle">
                                                <span class="badge bg-success bg-opacity-10 text-success border border-success px-3 py-2 rounded-pill fs-6">
                                                    <i class="fa-regular fa-clock me-1"></i> <?php echo date('H:i', strtotime($activo['hora_entrada'])); ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer border-0 bg-light">
                    <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Cerrar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.getElementById('searchInput').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#tablaAuditoria tbody tr');
            
            rows.forEach(row => {
                let text = row.cells[0].textContent.toLowerCase();
                if(text.includes(filter)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });
        });
    </script>
</body>
</html>
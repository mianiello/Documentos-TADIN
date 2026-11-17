<?php
// ==========================================================================================
// MÓDULO DE RECURSOS HUMANOS - MATRIZ DE ASISTENCIA (MAPA DE CALOR) V3
// ==========================================================================================
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
// 1. CARGA DINÁMICA DE CONFIGURACIÓN
// ==========================================================================================
$res_config = $conn->query("SELECT * FROM configuracion WHERE id = 1");
$config_db = $res_config->fetch_assoc();

$turno1_inicio = $config_db['turno1_inicio'] ?? '07:00:00';
$turno1_fin = $config_db['turno1_fin'] ?? '12:00:00';
$turno2_inicio = $config_db['turno2_inicio'] ?? '13:00:00';
$turno2_fin = $config_db['turno2_fin'] ?? '16:30:00';
$tolerancia_minutos = $config_db['tolerancia_minutos'] ?? 15;

// ==========================================================================================
// 2. LÓGICA DE TIEMPO INTELIGENTE (Filtro Global de Meses)
// ==========================================================================================
$mes_actual = date('n');
$ano_actual = date('Y');
$dia_hoy = date('j');

$mes_filtro = isset($_GET['mes']) ? intval($_GET['mes']) : $mes_actual;
$ano_filtro = isset($_GET['ano']) ? intval($_GET['ano']) : $ano_actual;

$meses_es = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$titulo_mes = strtoupper($meses_es[$mes_filtro]) . " " . $ano_filtro;

$dias_del_mes = cal_days_in_month(CAL_GREGORIAN, $mes_filtro, $ano_filtro);
$nombres_dias_cortos = [1 => 'Lu', 2 => 'Ma', 3 => 'Mi', 4 => 'Ju', 5 => 'Vi', 6 => 'Sá', 7 => 'Do'];

// ==========================================================================================
// 3. EXTRACCIÓN DE DATOS
// ==========================================================================================
$sql_empleados = "SELECT id, nombre FROM empleados ORDER BY nombre ASC";
$res_empleados = $conn->query($sql_empleados);
$empleados = [];
if ($res_empleados) {
    while ($emp = $res_empleados->fetch_assoc()) {
        $empleados[$emp['id']] = mb_convert_case(strtolower($emp['nombre']), MB_CASE_TITLE, "UTF-8");
    }
}

$sql_asistencia = "SELECT id_empleado, DAY(fecha_hora) as dia, MIN(fecha_hora) as entrada, MAX(fecha_hora) as salida 
                   FROM registros_asistencia 
                   WHERE MONTH(fecha_hora) = $mes_filtro AND YEAR(fecha_hora) = $ano_filtro 
                   GROUP BY id_empleado, DATE(fecha_hora)";
$res_asistencia = $conn->query($sql_asistencia);

$matriz = [];
if ($res_asistencia) {
    while ($row = $res_asistencia->fetch_assoc()) {
        $matriz[$row['id_empleado']][$row['dia']] = [
            'entrada' => $row['entrada'],
            'salida' => $row['salida']
        ];
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Bio - Matriz de Asistencia</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { 
            --primary-color: #2c3e50; 
            --accent-color: #3498db; 
            --bg-color: #f4f7f6; 
        }
        body { font-family: 'Nunito', sans-serif; background-color: var(--bg-color); overflow-x: hidden; color: #2c3e50; }
        
        ::-webkit-scrollbar { width: 8px; height: 8px; }
        ::-webkit-scrollbar-track { background: #f1f1f1; border-radius: 4px; }
        ::-webkit-scrollbar-thumb { background: #c1c1c1; border-radius: 4px; }
        
        .sidebar { background-color: var(--primary-color); min-height: 100vh; color: white; position: fixed; width: 250px; top: 0; left: 0; z-index: 1000; box-shadow: 2px 0 15px rgba(0,0,0,0.1);}
        .sidebar-brand { padding: 25px 20px; font-size: 1.4rem; font-weight: 800; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 15px; }
        .sidebar-menu a { color: #aab7c4; text-decoration: none; display: block; padding: 15px 25px; font-size: 1rem; font-weight: 600; transition: all 0.3s ease; }
        .sidebar-menu a i { width: 25px; font-size: 1.1rem; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: #34495e; color: #ffffff; border-left: 4px solid var(--accent-color); }
        
        .main-content { margin-left: 250px; padding: 30px 40px; }
        .page-header-container { background: #ffffff; border-radius: 12px; padding: 25px 30px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px;}
        .page-header-container h2 { font-weight: 800; margin: 0 0 5px 0; font-size: 1.8rem; color: var(--primary-color);}
        
        .month-selector { border: 1px solid #dfe6e9; border-radius: 8px; padding: 8px 12px; font-weight: 700; color: var(--primary-color); background-color: #ffffff; outline: none; transition: 0.3s; }
        .month-selector:focus { border-color: var(--accent-color); }
        
        .card-pro { background: #ffffff; border-radius: 12px; border: 1px solid #ecf0f1; box-shadow: 0 4px 20px rgba(0,0,0,0.02); overflow: hidden; }
        .card-header-pro { background: #fcfdfd; border-bottom: 1px solid #ecf0f1; padding: 20px 25px; font-weight: 800; color: #7f8c8d; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
        
        .grid-wrapper { overflow-x: auto; padding-bottom: 10px; }
        .table-grid { margin-bottom: 0; width: 100%; border-collapse: separate; border-spacing: 0; }
        .table-grid th { background-color: #f8f9fa; color: #7f8c8d; font-weight: 800; text-align: center; padding: 10px 5px; font-size: 0.85rem; border-bottom: 2px solid #ecf0f1; border-right: 1px solid #ecf0f1; min-width: 40px;}
        .table-grid th.emp-name-col { text-align: left; padding-left: 20px; position: sticky; left: 0; background-color: #f8f9fa; z-index: 2; border-right: 2px solid #ecf0f1; min-width: 150px; vertical-align: middle; }
        
        .table-grid td { text-align: center; vertical-align: middle; border-bottom: 1px solid #ecf0f1; border-right: 1px solid #ecf0f1; height: 45px; transition: 0.2s; cursor: pointer; }
        .table-grid td.emp-name-col { font-weight: 700; text-align: left; padding-left: 20px; position: sticky; left: 0; background-color: #ffffff; z-index: 1; border-right: 2px solid #ecf0f1; cursor: default;}
        .table-grid tbody tr:hover td { background-color: #f1f5f8; }
        .table-grid tbody tr:hover td.emp-name-col { background-color: #f1f5f8; }
        
        /* COLORES DE LAS CELDAS */
        .cell-present { color: #27ae60; }
        .cell-late { color: #f39c12; }
        .cell-absent { color: #e74c3c; }
        .cell-incomplete { color: #3498db; }
        .cell-weekend { background-color: #f4f6f7 !important; color: #bdc3c7; font-size: 0.8rem; }
        .cell-future { background-color: #ffffff; }

        .legend-box { display: flex; flex-wrap: wrap; gap: 20px; font-size: 0.85rem; font-weight: 700; color: #7f8c8d; margin-top: 10px; }
        .legend-item { display: flex; align-items: center; gap: 8px; }
        
        /* Ajuste estético para tooltips */
        .tooltip-inner { font-weight: bold; font-family: 'Nunito', sans-serif; padding: 8px 12px; }
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
            <a href="horas.php"><i class="fa-solid fa-calendar-check"></i> Horas Trabajadas</a>
            <a href="asistencia.php" class="active"><i class="fa-solid fa-border-all"></i> Matriz Mensual</a>
            <a href="configuracion.php"><i class="fa-solid fa-cog"></i> Configuración</a>
            
            <hr style="border-color: rgba(255,255,255,0.1); margin: 20px;">
            <a href="login.php?logout=1" class="text-danger fw-bold"><i class="fa-solid fa-power-off"></i> Cerrar Sesión</a>
        </div>
    </div>

    <div class="main-content">
        
        <div class="page-header-container">
            <div>
                <h2>Matriz de Asistencia Mensual</h2>
                <div class="legend-box">
                    <div class="legend-item"><i class="fa-solid fa-check-circle cell-present fs-6"></i> Presente</div>
                    <div class="legend-item"><i class="fa-solid fa-clock cell-late fs-6"></i> Llegada Tarde</div>
                    <div class="legend-item"><i class="fa-solid fa-triangle-exclamation cell-incomplete fs-6"></i> Incompleto</div>
                    <div class="legend-item"><i class="fa-solid fa-xmark-circle cell-absent fs-6"></i> Ausente</div>
                    <div class="legend-item"><i class="fa-solid fa-moon text-secondary opacity-50 fs-6"></i> Fuera de Horario</div>
                    <div class="legend-item"><span class="cell-weekend" style="padding: 2px 6px; border-radius: 4px;"><i class="fa-solid fa-bed"></i></span> FDS</div>
                </div>
            </div>
            
            <div class="header-actions">
                <form method="GET" action="asistencia.php" class="m-0 p-0">
                    <div class="d-flex gap-2 align-items-center">
                        <i class="fa-solid fa-filter text-muted me-1"></i>
                        <select name="mes" class="month-selector shadow-sm" onchange="this.form.submit()">
                            <?php 
                            for($i = 1; $i <= 12; $i++){
                                $sel = ($i == $mes_filtro) ? "selected" : "";
                                echo "<option value='$i' $sel>{$meses_es[$i]}</option>";
                            }
                            ?>
                        </select>
                        <select name="ano" class="month-selector shadow-sm" onchange="this.form.submit()">
                            <?php 
                            for($i = $ano_actual - 1; $i <= $ano_actual + 1; $i++){
                                $sel = ($i == $ano_filtro) ? "selected" : "";
                                echo "<option value='$i' $sel>$i</option>";
                            }
                            ?>
                        </select>
                    </div>
                </form>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card-pro">
                    <div class="card-header-pro">
                        <i class="fa-solid fa-table-cells text-primary me-2"></i> MAPA DE CALOR - <?php echo $titulo_mes; ?>
                    </div>
                    <div class="card-body p-0">
                        <div class="grid-wrapper">
                            <table class="table-grid">
                                <thead>
                                    <tr>
                                        <th class="emp-name-col">EMPLEADO</th>
                                        <?php 
                                        for ($d = 1; $d <= $dias_del_mes; $d++) {
                                            $fecha_evaluada = "$ano_filtro-$mes_filtro-".str_pad($d, 2, "0", STR_PAD_LEFT);
                                            $dia_semana = date('N', strtotime($fecha_evaluada));
                                            $nombre_dia = $nombres_dias_cortos[$dia_semana];
                                            
                                            $clase_th = ($dia_semana >= 6) ? "style='background-color: #f4f6f7;'" : "";
                                            echo "<th $clase_th>
                                                    <div class='text-muted' style='font-size: 0.65rem; font-weight: 700; margin-bottom: 3px;'>$nombre_dia</div>
                                                    <div>$d</div>
                                                  </th>";
                                        }
                                        ?>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if (!empty($empleados)) {
                                        foreach ($empleados as $id_emp => $nombre) {
                                            echo "<tr>";
                                            echo "<td class='emp-name-col'>{$nombre}</td>";
                                            
                                            for ($d = 1; $d <= $dias_del_mes; $d++) {
                                                
                                                $fecha_evaluada = "$ano_filtro-$mes_filtro-".str_pad($d, 2, "0", STR_PAD_LEFT);
                                                $dia_semana = date('N', strtotime($fecha_evaluada));
                                                
                                                $es_futuro = false;
                                                if ($ano_filtro > $ano_actual) $es_futuro = true;
                                                elseif ($ano_filtro == $ano_actual && $mes_filtro > $mes_actual) $es_futuro = true;
                                                elseif ($ano_filtro == $ano_actual && $mes_filtro == $mes_actual && $d > $dia_hoy) $es_futuro = true;
                                                
                                                // 1. Fin de semana
                                                if ($dia_semana >= 6) {
                                                    echo "<td class='cell-weekend' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='Fin de Semana'>-</td>";
                                                } 
                                                // 2. Futuro
                                                elseif ($es_futuro) {
                                                    echo "<td class='cell-future'></td>";
                                                } 
                                                // 3. Fichada encontrada
                                                elseif (isset($matriz[$id_emp][$d])) {
                                                    
                                                    // Variables de tiempo para cálculo matemático
                                                    $t_ent = strtotime($matriz[$id_emp][$d]['entrada']);
                                                    $t_sal = strtotime($matriz[$id_emp][$d]['salida']);
                                                    
                                                    $hora_entrada = date('H:i', $t_ent);
                                                    $hora_salida = date('H:i', $t_sal);
                                                    
                                                    $t_0700 = strtotime("$fecha_evaluada " . $turno1_inicio);
                                                    $t_1200 = strtotime("$fecha_evaluada " . $turno1_fin);
                                                    $t_1300 = strtotime("$fecha_evaluada " . $turno2_inicio);
                                                    $t_1630 = strtotime("$fecha_evaluada " . $turno2_fin);
                                                    $t_0715 = strtotime("$fecha_evaluada " . $turno1_inicio . " + $tolerancia_minutos minutes");

                                                    // Cálculo de horas trabajadas reales
                                                    $cruce_manana = min($t_sal, $t_1200) - max($t_ent, $t_0700);
                                                    $minutos_manana = ($cruce_manana > 0) ? ($cruce_manana / 60) : 0;

                                                    $cruce_tarde = min($t_sal, $t_1630) - max($t_ent, $t_1300);
                                                    $minutos_tarde = ($cruce_tarde > 0) ? ($cruce_tarde / 60) : 0;
                                                    
                                                    $minutos_computables = floor($minutos_manana + $minutos_tarde);
                                                    
                                                    // Lógica Condicional del ícono
                                                    if ($matriz[$id_emp][$d]['entrada'] == $matriz[$id_emp][$d]['salida']) {
                                                        // Tiene 1 sola fichada (Entrada sin salida)
                                                        if ($t_ent > $t_1630 || $t_ent < strtotime("$fecha_evaluada 06:00:00")) {
                                                            // Fichó a las 20:00 hs
                                                            echo "<td data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='Fuera de Horario | Ingreso: {$hora_entrada}'><i class='fa-solid fa-moon text-secondary opacity-50 fs-5'></i></td>";
                                                        } else {
                                                            // Entró dentro del horario pero se olvidó de salir
                                                            echo "<td class='cell-incomplete' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='Turno Abierto | Entrada: {$hora_entrada} - Falta Salida'><i class='fa-solid fa-triangle-exclamation fs-5'></i></td>";
                                                        }
                                                    } else {
                                                        // Tiene entrada y salida
                                                        if ($minutos_computables == 0) {
                                                            // Entró y salió totalmente fuera de horario (Ej: 20:00 a 20:05)
                                                            echo "<td data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='Fuera de Horario | Ingreso: {$hora_entrada} - Salida: {$hora_salida}'><i class='fa-solid fa-moon text-secondary opacity-50 fs-5'></i></td>";
                                                        } elseif ($t_ent > $t_0715) {
                                                            // Llegó tarde (Ej: 08:00)
                                                            echo "<td class='cell-late' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='Llegada Tarde | Ingreso: {$hora_entrada} - Salida: {$hora_salida}'><i class='fa-solid fa-clock fs-5'></i></td>";
                                                        } else {
                                                            // Presente Perfecto
                                                            echo "<td class='cell-present' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='Presente | Ingreso: {$hora_entrada} - Salida: {$hora_salida}'><i class='fa-solid fa-check-circle fs-5'></i></td>";
                                                        }
                                                    }
                                                } 
                                                // 4. Ausente
                                                else {
                                                    echo "<td class='cell-absent' data-bs-toggle='tooltip' data-bs-placement='top' data-bs-title='Ausente / Sin Fichadas'><i class='fa-solid fa-xmark-circle fs-5'></i></td>";
                                                }
                                            }
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='".($dias_del_mes + 1)."' class='text-center py-4 text-muted fw-bold'>No hay empleados registrados.</td></tr>";
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

    <!-- Script de Inicialización de Tooltips de Bootstrap -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
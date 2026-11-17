<?php
session_start();
if (!isset($_SESSION['admin_logeado']) || $_SESSION['admin_logeado'] !== true) {
    header("Location: login.php");
    exit;
}
// Reporte de errores para evitar pantallas blancas
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// ==========================================
// CONFIGURACIÓN DE ZONA HORARIA (ARGENTINA)
// ==========================================
date_default_timezone_set('America/Argentina/Buenos_Aires');

require_once 'conexion.php';

// ==========================================
// 1. PING TCP AL RELOJ (Ultrarrápido: 1 seg)
// ==========================================
$ip_reloj = '192.168.1.171';
$puerto = 4370;

// Variables por defecto (Desconectado)
$estado_clase = "badge-danger-soft";
$estado_icono = "fa-times-circle";
$estado_texto = "Desconectado / Sin Señal";

// Intento de conexión TCP
$conexion_ping = @fsockopen("tcp://$ip_reloj", $puerto, $errno, $errstr, 1);
if ($conexion_ping) {
    $estado_clase = "badge-success-soft";
    $estado_icono = "fa-check-circle";
    $estado_texto = "Conectado / En Línea";
    fclose($conexion_ping);
}

// ==========================================
// 2. CONSULTAS A LA BASE DE DATOS
// ==========================================
// Total de empleados
$res_empleados = $conn->query("SELECT COUNT(*) as total FROM empleados");
$total_empleados = $res_empleados ? $res_empleados->fetch_assoc()['total'] : 0;

// Fichadas del día de hoy
$res_fichadas = $conn->query("SELECT COUNT(*) as total FROM registros_asistencia WHERE DATE(fecha_hora) = CURDATE()");
$total_hoy = $res_fichadas ? $res_fichadas->fetch_assoc()['total'] : 0;

// Últimos 5 movimientos (Uniendo la tabla de registros con la de empleados)
$sql_movimientos = "SELECT e.nombre, r.fecha_hora, r.tipo 
                    FROM registros_asistencia r 
                    JOIN empleados e ON r.id_empleado = e.id 
                    ORDER BY r.fecha_hora DESC LIMIT 5";
$res_movimientos = $conn->query($sql_movimientos);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Bio - Dashboard</title>
    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome para Íconos -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f4f7f6;
            overflow-x: hidden;
        }
        /* -- SIDEBAR -- */
        .sidebar {
            background-color: #2c3e50;
            min-height: 100vh;
            color: white;
            position: fixed;
            width: 250px;
            top: 0;
            left: 0;
        }
        .sidebar-brand {
            padding: 25px 20px;
            font-size: 1.4rem;
            font-weight: 800;
            letter-spacing: 1px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            margin-bottom: 15px;
        }
        .sidebar-menu a {
            color: #aab7c4;
            text-decoration: none;
            display: block;
            padding: 14px 25px;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .sidebar-menu a i {
            width: 25px;
            font-size: 1.1rem;
        }
        .sidebar-menu a:hover, .sidebar-menu a.active {
            background-color: #34495e;
            color: #ffffff;
            border-left: 4px solid #3498db;
        }
        /* -- MAIN CONTENT -- */
        .main-content {
            margin-left: 250px;
            padding: 30px 40px;
        }
        .page-title {
            font-weight: 800;
            color: #2c3e50;
            margin: 0;
        }
        .date-pill {
            background-color: #ffffff;
            color: #7f8c8d;
            padding: 8px 18px;
            border-radius: 30px;
            font-weight: 600;
            box-shadow: 0 2px 4px rgba(0,0,0,0.02);
            border: 1px solid #e0e6ed;
        }
        /* -- CARDS -- */
        .card-custom {
            background: #ffffff;
            border-radius: 12px;
            border: none;
            box-shadow: 0 4px 15px rgba(0,0,0,0.03);
            margin-bottom: 25px;
        }
        .card-header-custom {
            background: transparent;
            border-bottom: 1px solid #f0f2f5;
            padding: 20px 25px;
            font-weight: 700;
            color: #95a5a6;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.5px;
        }
        /* -- TERMINAL INFO -- */
        .badge-danger-soft {
            background-color: #fdeaea;
            color: #e74c3c;
            border: 1px solid #fad4d4;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.9rem;
            display: inline-block;
        }
        .badge-success-soft {
            background-color: #eafaf1;
            color: #27ae60;
            border: 1px solid #d1f2db;
            padding: 8px 20px;
            border-radius: 30px;
            font-weight: 700;
            font-size: 0.9rem;
            display: inline-block;
        }
        .info-box {
            background-color: #f8f9fa;
            border-radius: 8px;
            padding: 15px;
            height: 100%;
        }
        .info-box span {
            display: block;
            font-size: 0.8rem;
            color: #7f8c8d;
            margin-bottom: 5px;
        }
        .info-box strong {
            color: #2c3e50;
            font-size: 1.1rem;
        }
        /* -- RESUMEN ICONS -- */
        .icon-square {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.8rem;
            color: white;
            margin-right: 20px;
        }
        .bg-blue { background-color: #3498db; }
        .bg-purple { background-color: #9b59b6; }
        /* -- TABLE -- */
        .table-custom th {
            color: #7f8c8d;
            font-weight: 600;
            border-bottom-width: 1px;
            padding: 15px 25px;
            font-size: 0.9rem;
        }
        .table-custom td {
            padding: 15px 25px;
            vertical-align: middle;
            color: #2c3e50;
            font-weight: 600;
        }
        .badge-entrada {
            background-color: #eafaf1;
            color: #2ecc71;
            border: 1px solid #2ecc71;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
        .badge-salida {
            background-color: #fdeaea;
            color: #e74c3c;
            border: 1px solid #e74c3c;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
        }
    </style>
</head>
<body>

    <!-- SIDEBAR -->
    <div class="sidebar">
        <div class="sidebar-brand text-center">
            <i class="fa-solid fa-fingerprint me-2"></i> HR Bio
        </div>
        <div class="sidebar-menu">
            <a href="index.php" class="active"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a href="empleados.php"><i class="fa-solid fa-users"></i> Empleados</a>
            <a href="registros.php"><i class="fa-solid fa-history"></i> Registros Crudos</a>
            <a href="horas.php"><i class="fa-solid fa-calendar-check"></i> Horas Trabajadas</a>
            <a href="#"><i class="fa-solid fa-cog"></i> Configuración</a>
            <a href="login.php?logout=1" class="text-danger mt-4"><i class="fa-solid fa-power-off"></i> Cerrar Sesión</a>
            <a href="asistencia.php"><i class="fa-solid fa-border-all"></i> Matriz Mensual</a>
        </div>
    </div>

    <!-- CONTENIDO PRINCIPAL -->
    <div class="main-content">
        <!-- HEADER -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">Panel Principal</h2>
            <div class="date-pill">
                <i class="fa-regular fa-calendar me-2"></i> <?php echo date('d / m / Y'); ?>
            </div>
        </div>

        <div class="row">
            <!-- TARJETA: ZKTECO TERMINAL -->
            <div class="col-lg-7">
                <div class="card-custom h-100">
                    <div class="card-header-custom">
                        <i class="fa-solid fa-link me-2"></i> ZKTECO TERMINAL
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-4">
                            <div class="<?php echo $estado_clase; ?>">
                                <i class="fa-solid <?php echo $estado_icono; ?> me-1"></i> <?php echo $estado_texto; ?>
                            </div>
                        </div>
                        
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="info-box">
                                    <span>Dirección IP</span>
                                    <strong><?php echo htmlspecialchars($ip_reloj); ?></strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <span>Puerto TCP/UDP</span>
                                    <strong><?php echo htmlspecialchars($puerto); ?></strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <span>Modelo</span>
                                    <strong>ZEM800_TFT</strong>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="info-box">
                                    <span>Algoritmo</span>
                                    <strong>V10.0</strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- TARJETA: RESUMEN DE DATOS -->
            <div class="col-lg-5">
                <div class="card-custom h-100">
                    <div class="card-header-custom">
                        <i class="fa-solid fa-chart-pie me-2"></i> RESUMEN DE DATOS
                    </div>
                    <div class="card-body p-4 d-flex flex-column justify-content-center">
                        <div class="d-flex align-items-center mb-4">
                            <div class="icon-square bg-blue">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <div>
                                <h3 class="m-0 fw-bold text-dark"><?php echo $total_empleados; ?></h3>
                                <span class="text-muted" style="font-size: 0.95rem;">Empleados en Base de Datos</span>
                            </div>
                        </div>
                        
                        <div class="d-flex align-items-center">
                            <div class="icon-square bg-purple">
                                <i class="fa-solid fa-sign-in-alt"></i>
                            </div>
                            <div>
                                <h3 class="m-0 fw-bold text-dark"><?php echo $total_hoy; ?></h3>
                                <span class="text-muted" style="font-size: 0.95rem;">Fichadas detectadas hoy</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TARJETA: ÚLTIMOS MOVIMIENTOS -->
        <div class="row mt-4">
            <div class="col-12">
                <div class="card-custom">
                    <div class="card-header-custom">
                        <i class="fa-solid fa-list-ul me-2"></i> ÚLTIMOS MOVIMIENTOS
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-custom mb-0 table-hover">
                                <thead>
                                    <tr>
                                        <th>Empleado</th>
                                        <th>Fecha y Hora</th>
                                        <th class="text-end">Tipo de Registro</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($res_movimientos && $res_movimientos->num_rows > 0) {
                                        while($row = $res_movimientos->fetch_assoc()) {
                                            // 0 = Entrada, 1 = Salida
                                            if ($row['tipo'] == 0) {
                                                $badge = "<span class='badge-entrada'>Entrada</span>";
                                            } else {
                                                $badge = "<span class='badge-salida'>Salida</span>";
                                            }

                                            $fecha_formateada = date('d/m/Y H:i', strtotime($row['fecha_hora']));
                                            $nombre_limpio = htmlspecialchars($row['nombre']);
                                            
                                            echo "<tr>";
                                            echo "<td>" . strtolower($nombre_limpio) . "</td>";
                                            echo "<td><i class='fa-regular fa-clock text-muted me-2'></i>" . $fecha_formateada . "</td>";
                                            echo "<td class='text-end'>" . $badge . "</td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='3' class='text-center py-5 text-muted'>No hay registros recientes.</td></tr>";
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

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
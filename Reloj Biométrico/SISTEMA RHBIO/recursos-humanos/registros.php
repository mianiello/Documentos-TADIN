<?php
// ==========================================================================================
// MÓDULO DE RECURSOS HUMANOS - REGISTROS CRUDOS Y CARGA MANUAL (CON SEGURIDAD ACTIVA)
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

$mensaje_sistema = '';

// ==========================================================================================
// 1. PROCESADOR DE ACCIONES CRÍTICAS (Requieren Contraseña)
// ==========================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    $pass_ingresada = $_POST['admin_pass'] ?? '';
    
    if ($pass_ingresada !== 'tadin2026rh') {
        $mensaje_sistema = "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'>
                                <i class='fa-solid fa-lock me-2'></i> <strong>Acceso Denegado:</strong> La contraseña de autorización es incorrecta.
                                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                            </div>";
    } else {
        if ($_POST['action'] == 'insert_manual') {
            $id_empleado = intval($_POST['id_empleado']);
            $fecha = $conn->real_escape_string($_POST['fecha']);
            $hora = $conn->real_escape_string($_POST['hora']);
            $tipo = intval($_POST['tipo']);

            $fecha_hora_completa = $fecha . ' ' . $hora . ':00';

            $check = $conn->query("SELECT id FROM registros_asistencia WHERE id_empleado = $id_empleado AND fecha_hora = '$fecha_hora_completa'");
            
            if ($check->num_rows > 0) {
                $mensaje_sistema = "<div class='alert alert-warning alert-dismissible fade show shadow-sm' role='alert'>
                                        <i class='fa-solid fa-triangle-exclamation me-2'></i> Ya existe un registro exacto para ese empleado en esa fecha y hora.
                                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                                    </div>";
            } else {
                $sql_insert = "INSERT INTO registros_asistencia (id_empleado, fecha_hora, tipo) VALUES ($id_empleado, '$fecha_hora_completa', $tipo)";
                if ($conn->query($sql_insert) === TRUE) {
                    $mensaje_sistema = "<div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'>
                                            <i class='fa-solid fa-circle-check me-2'></i> Fichada manual autorizada e insertada correctamente.
                                            <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                                        </div>";
                }
            }
        }
        
        elseif ($_POST['action'] == 'delete_record') {
            $id_borrar = intval($_POST['id_registro']);
            
            $sql_delete = "DELETE FROM registros_asistencia WHERE id = $id_borrar";
            if ($conn->query($sql_delete) === TRUE) {
                $mensaje_sistema = "<div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'>
                                        <i class='fa-solid fa-trash-can me-2'></i> Registro eliminado del sistema mediante autorización.
                                        <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                                    </div>";
            }
        }
    }
}

// ==========================================================================================
// 2. LÓGICA DE FILTRADO POR MES (Igual que en Horas Trabajadas)
// ==========================================================================================
$mes_actual = date('n');
$ano_actual = date('Y');

$mes_filtro = isset($_GET['mes']) ? intval($_GET['mes']) : $mes_actual;
$ano_filtro = isset($_GET['ano']) ? intval($_GET['ano']) : $ano_actual;

$meses_es = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
$titulo_mes = strtoupper($meses_es[$mes_filtro]) . " " . $ano_filtro;

// ==========================================================================================
// 3. OBTENER DATOS PARA LA VISTA
// ==========================================================================================
$sql_empleados = "SELECT id, nombre FROM empleados ORDER BY nombre ASC";
$empleados_res = $conn->query($sql_empleados);

// La consulta ahora solo trae las fichadas del mes seleccionado
$sql = "SELECT r.id as id_registro, e.nombre, r.fecha_hora, r.tipo 
        FROM registros_asistencia r 
        JOIN empleados e ON r.id_empleado = e.id 
        WHERE MONTH(r.fecha_hora) = $mes_filtro AND YEAR(r.fecha_hora) = $ano_filtro
        ORDER BY r.fecha_hora DESC";
$resultado = $conn->query($sql);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Bio - Registros Crudos</title>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body { font-family: 'Nunito', sans-serif; background-color: #f4f7f6; overflow-x: hidden; }
        .sidebar { background-color: #2c3e50; min-height: 100vh; color: white; position: fixed; width: 250px; top: 0; left: 0; z-index: 1000; }
        .sidebar-brand { padding: 25px 20px; font-size: 1.4rem; font-weight: 800; letter-spacing: 1px; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 15px; }
        .sidebar-menu a { color: #aab7c4; text-decoration: none; display: block; padding: 14px 25px; font-size: 1rem; font-weight: 600; transition: all 0.3s ease; }
        .sidebar-menu a i { width: 25px; font-size: 1.1rem; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: #34495e; color: #ffffff; border-left: 4px solid #3498db; }
        
        .main-content { margin-left: 250px; padding: 30px 40px; }
        .page-title { font-weight: 800; color: #2c3e50; margin: 0; }
        
        .card-custom { background: #ffffff; border-radius: 12px; border: none; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 25px; }
        .card-header-custom { background: transparent; border-bottom: 1px solid #f0f2f5; padding: 20px 25px; font-weight: 700; color: #95a5a6; text-transform: uppercase; font-size: 0.85rem; letter-spacing: 0.5px; }
        
        .table-custom th { color: #7f8c8d; font-weight: 600; border-bottom-width: 1px; padding: 15px 25px; font-size: 0.9rem; }
        .table-custom td { padding: 15px 25px; vertical-align: middle; color: #2c3e50; font-weight: 600; }
        
        .badge-entrada { background-color: #eafaf1; color: #2ecc71; border: 1px solid #2ecc71; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: 800; }
        .badge-salida { background-color: #fdeaea; color: #e74c3c; border: 1px solid #e74c3c; padding: 5px 15px; border-radius: 20px; font-size: 0.8rem; font-weight: 800; }
        
        .btn-manual { background-color: #9b59b6; color: white; font-weight: bold; border: none; transition: 0.3s; }
        .btn-manual:hover { background-color: #8e44ad; color: white; box-shadow: 0 4px 10px rgba(155, 89, 182, 0.3); }
        .btn-delete-row { color: #e74c3c; background: transparent; border: 1px solid #e74c3c; border-radius: 6px; padding: 4px 10px; transition: 0.3s; }
        .btn-delete-row:hover { background: #e74c3c; color: white; }
        
        /* Estilos nuevos para los selectores de mes y el buscador */
        .month-selector { border: 1px solid #dfe6e9; border-radius: 8px; padding: 8px 12px; font-weight: 700; color: #2c3e50; background-color: #ffffff; outline: none; transition: 0.3s; }
        .month-selector:focus { border-color: #3498db; }
        .search-box { position: relative; width: 280px; }
        .search-box input { border-radius: 20px; padding-left: 35px; border: 1px solid #e0e6ed; font-size: 0.85rem; box-shadow: none; }
        .search-box i { position: absolute; left: 12px; top: 10px; color: #95a5a6; font-size: 0.9rem; }
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
            <a href="registros.php" class="active"><i class="fa-solid fa-history"></i> Registros Crudos</a>
            <a href="horas.php"><i class="fa-solid fa-calendar-check"></i> Horas Trabajadas</a>
            <a href="configuracion.php"><i class="fa-solid fa-cog"></i> Configuración</a>
            <a href="asistencia.php"><i class="fa-solid fa-border-all"></i> Matriz Mensual</a>
            <hr style="border-color: rgba(255,255,255,0.1); margin: 20px;">
            <a href="login.php?logout=1" class="text-danger fw-bold"><i class="fa-solid fa-power-off"></i> Cerrar Sesión</a>
        </div>
    </div>

    <div class="main-content">
        
        <?php echo $mensaje_sistema; ?>

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <h2 class="page-title">Registros Crudos</h2>
            
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <!-- SELECTOR DE MESES (Misma lógica que horas.php) -->
                <form method="GET" action="registros.php" class="m-0 p-0 me-2">
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

                <button type="button" class="btn btn-manual px-3 shadow-sm" data-bs-toggle="modal" data-bs-target="#modalCargaManual">
                    <i class="fa-solid fa-plus-circle me-2"></i> Carga Manual / Justificar
                </button>
                <button class="btn btn-outline-primary fw-bold px-3 bg-white" onclick="location.reload();">
                    <i class="fa-solid fa-rotate-right me-2"></i> Actualizar
                </button>
            </div>
        </div>

        <div class="row">
            <div class="col-12">
                <div class="card-custom">
                    <div class="card-header-custom d-flex justify-content-between align-items-center flex-wrap gap-3">
                        <span><i class="fa-solid fa-database me-2"></i> HISTORIAL DE FICHADAS (<?php echo $titulo_mes; ?>)</span>
                        
                        <!-- NUEVO: Buscador Inteligente -->
                        <div class="d-flex align-items-center gap-3">
                            <div class="search-box">
                                <i class="fa-solid fa-search"></i>
                                <input type="text" id="buscadorFichadas" class="form-control form-control-sm" placeholder="Buscar empleado o fecha...">
                            </div>
                            <span class="badge bg-secondary" id="contadorRegistros"><?php echo $resultado ? $resultado->num_rows : 0; ?> Registros</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive" style="max-height: 70vh; overflow-y: auto;">
                            <table class="table table-custom mb-0 table-hover" id="tablaFichadas">
                                <thead style="position: sticky; top: 0; background: white; z-index: 1; box-shadow: 0 1px 3px rgba(0,0,0,0.05);">
                                    <tr>
                                        <th>Empleado</th>
                                        <th>Fecha Exacta</th>
                                        <th>Hora Exacta</th>
                                        <th class="text-center">Tipo de Registro</th>
                                        <th class="text-end" style="padding-right: 35px;">Acciones</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    if ($resultado && $resultado->num_rows > 0) {
                                        while($row = $resultado->fetch_assoc()) {
                                            if ($row['tipo'] == 0) {
                                                $badge = "<span class='badge-entrada'>Entrada</span>";
                                            } else {
                                                $badge = "<span class='badge-salida'>Salida</span>";
                                            }

                                            $fecha = date('d/m/Y', strtotime($row['fecha_hora']));
                                            $hora = date('H:i:s', strtotime($row['fecha_hora']));
                                            $nombre_limpio = htmlspecialchars(mb_convert_case(strtolower($row['nombre']), MB_CASE_TITLE, "UTF-8"));
                                            
                                            echo "<tr>";
                                            echo "<td class='fw-bold'>" . $nombre_limpio . "</td>";
                                            echo "<td><i class='fa-regular fa-calendar text-muted me-2'></i>" . $fecha . "</td>";
                                            echo "<td><i class='fa-regular fa-clock text-muted me-2'></i>" . $hora . "</td>";
                                            echo "<td class='text-center'>" . $badge . "</td>";
                                            echo "<td class='text-end' style='padding-right: 25px;'>
                                                    <button class='btn-delete-row' onclick='prepararBorrado({$row['id_registro']}, \"{$nombre_limpio}\", \"{$fecha}\", \"{$hora}\")' title='Eliminar registro'>
                                                        <i class='fa-solid fa-trash-can'></i>
                                                    </button>
                                                  </td>";
                                            echo "</tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='5' class='text-center py-5 text-muted'>No hay registros para el mes seleccionado.</td></tr>";
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

    <!-- MODAL 1: CARGA MANUAL DE FICHADA -->
    <div class="modal fade" id="modalCargaManual" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header text-white border-0" style="background-color: #8e44ad;">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-pen-to-square me-2"></i> Carga Manual de Registro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <form method="POST" action="registros.php">
                    <input type="hidden" name="action" value="insert_manual">
                    
                    <div class="modal-body p-4">
                        <div class="mb-3">
                            <label class="form-label fw-bold text-dark">Empleado</label>
                            <select class="form-select form-select-lg" name="id_empleado" required>
                                <option value="" disabled selected>Seleccione un empleado...</option>
                                <?php 
                                if($empleados_res && $empleados_res->num_rows > 0) {
                                    while($emp = $empleados_res->fetch_assoc()) {
                                        $emp_nombre = htmlspecialchars(mb_convert_case(strtolower($emp['nombre']), MB_CASE_TITLE, "UTF-8"));
                                        echo "<option value='".$emp['id']."'>".$emp_nombre."</option>";
                                    }
                                }
                                ?>
                            </select>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-dark">Fecha</label>
                                <input type="date" class="form-control" name="fecha" required value="<?php echo date('Y-m-d'); ?>" max="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label fw-bold text-dark">Hora</label>
                                <input type="time" class="form-control" name="hora" required>
                            </div>
                        </div>

                        <div class="mb-2">
                            <label class="form-label fw-bold text-dark">Tipo de Movimiento</label>
                            <div class="d-flex gap-3 mt-1">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipo" id="tipoEntrada" value="0" checked>
                                    <label class="form-check-label text-success fw-bold" for="tipoEntrada">Entrada</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="tipo" id="tipoSalida" value="1">
                                    <label class="form-check-label text-danger fw-bold" for="tipoSalida">Salida</label>
                                </div>
                            </div>
                        </div>

                        <!-- REQUISITO DE CONTRASEÑA -->
                        <div class="mt-4 p-3 bg-light border rounded border-start border-4 border-danger">
                            <label class="form-label fw-bold text-danger"><i class="fa-solid fa-lock me-1"></i> Autorización Requerida</label>
                            <input type="password" class="form-control border-danger border-opacity-50" name="admin_pass" required placeholder="Contraseña de Administrador">
                        </div>
                    </div>
                    
                    <div class="modal-footer border-0 bg-light d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-manual fw-bold px-4">Autorizar e Insertar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL 2: ELIMINAR FICHADA -->
    <div class="modal fade" id="modalBorrar" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header text-white border-0 bg-danger">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i> Eliminar Registro</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                
                <form method="POST" action="registros.php">
                    <input type="hidden" name="action" value="delete_record">
                    <input type="hidden" name="id_registro" id="idBorrar">
                    
                    <div class="modal-body p-4 text-center">
                        <p class="fs-5 mb-1 text-dark">¿Eliminar la fichada de <strong id="nombreBorrar" class="text-danger"></strong>?</p>
                        <p class="text-muted fw-bold mb-4">Fecha: <span id="fechaBorrar"></span> | Hora: <span id="horaBorrar"></span></p>
                        
                        <!-- REQUISITO DE CONTRASEÑA -->
                        <div class="text-start p-3 bg-light border rounded border-start border-4 border-danger">
                            <label class="form-label fw-bold text-danger"><i class="fa-solid fa-lock me-1"></i> Autorización Requerida</label>
                            <input type="password" class="form-control border-danger border-opacity-50" name="admin_pass" required placeholder="Contraseña de Administrador">
                        </div>
                    </div>
                    
                    <div class="modal-footer border-0 bg-light d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-danger fw-bold px-4">Autorizar y Eliminar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Función para inyectar los datos en el modal de borrado
        function prepararBorrado(id, nombre, fecha, hora) {
            document.getElementById('idBorrar').value = id;
            document.getElementById('nombreBorrar').innerText = nombre;
            document.getElementById('fechaBorrar').innerText = fecha;
            document.getElementById('horaBorrar').innerText = hora;
            
            var modal = new bootstrap.Modal(document.getElementById('modalBorrar'));
            modal.show();
        }

        // Script de filtrado en vivo para la barra de búsqueda
        document.getElementById('buscadorFichadas').addEventListener('keyup', function() {
            let filter = this.value.toLowerCase();
            let rows = document.querySelectorAll('#tablaFichadas tbody tr');
            let registrosVisibles = 0;
            
            rows.forEach(row => {
                // Si la tabla muestra el mensaje de "No hay registros", lo ignoramos
                if (row.cells.length === 1) return; 

                // Buscamos coincidencia en Nombre (celda 0) o Fecha (celda 1)
                let nombre = row.cells[0].textContent.toLowerCase();
                let fecha = row.cells[1].textContent.toLowerCase();
                
                if(nombre.includes(filter) || fecha.includes(filter)) {
                    row.style.display = '';
                    registrosVisibles++;
                } else {
                    row.style.display = 'none';
                }
            });
            
            // Actualizar el badge del contador
            document.getElementById('contadorRegistros').innerText = registrosVisibles + " Registros";
        });
    </script>
</body>
</html>
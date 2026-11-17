<?php
session_start();
if (!isset($_SESSION['admin_logeado']) || $_SESSION['admin_logeado'] !== true) {
    header("Location: login.php");
    exit;
}
// ==========================================================================================
// MÓDULO DE GESTIÓN DE EMPLEADOS - HR BIO
// ==========================================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'conexion.php';

$mensaje_sistema = '';

// ==========================================================================================
// 1. LÓGICA DE AGREGAR EMPLEADO (NUEVO)
// ==========================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'add_employee') {
    $nuevo_id = intval($_POST['id_reloj']);
    $nuevo_nombre = $conn->real_escape_string(trim($_POST['nombre']));

    // Verificamos si ese ID ya está ocupado en la base de datos
    $check_id = $conn->query("SELECT id FROM empleados WHERE id = $nuevo_id");
    
    if ($check_id->num_rows > 0) {
        $mensaje_sistema = "<div class='alert alert-warning alert-dismissible fade show shadow-sm' role='alert'>
                                <i class='fa-solid fa-triangle-exclamation me-2'></i> <strong>Error:</strong> El ID $nuevo_id ya está asignado a otro empleado.
                                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                            </div>";
    } else {
        // Si está libre, lo insertamos
        $sql_insert = "INSERT INTO empleados (id, nombre) VALUES ($nuevo_id, '$nuevo_nombre')";
        if ($conn->query($sql_insert) === TRUE) {
            $mensaje_sistema = "<div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'>
                                    <i class='fa-solid fa-circle-check me-2'></i> Empleado <strong>$nuevo_nombre</strong> registrado exitosamente.
                                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                                </div>";
        } else {
            $mensaje_sistema = "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'>
                                    <i class='fa-solid fa-circle-xmark me-2'></i> Error al guardar: " . $conn->error . "
                                    <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                                </div>";
        }
    }
}

// ==========================================================================================
// 2. LÓGICA DE BORRADO SEGURO (CON CASCADA MANUAL)
// ==========================================================================================
if (isset($_GET['delete_id'])) {
    $id_a_borrar = intval($_GET['delete_id']);
    
    // Primero borramos el historial para evitar errores de clave foránea
    $sql_borrar_historial = "DELETE FROM registros_asistencia WHERE id_empleado = $id_a_borrar";
    $conn->query($sql_borrar_historial);
    
    // Ahora sí, borramos al empleado
    $sql_borrar_empleado = "DELETE FROM empleados WHERE id = $id_a_borrar";
    if ($conn->query($sql_borrar_empleado) === TRUE) {
        $mensaje_sistema = "<div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'>
                                <i class='fa-solid fa-circle-check me-2'></i> Empleado y su historial eliminados correctamente.
                                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                            </div>";
    }
}

// ==========================================================================================
// 3. OBTENER LA NÓMINA DE EMPLEADOS
// ==========================================================================================
$sql_empleados = "SELECT id, nombre FROM empleados ORDER BY id ASC";
$resultado = $conn->query($sql_empleados);
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Bio - Gestión de Empleados</title>
    
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root { 
            --primary-color: #2c3e50; 
            --secondary-color: #34495e;
            --accent-color: #3498db; 
            --danger-color: #e74c3c;
            --bg-body: #f4f7f6; 
        }
        
        body { font-family: 'Nunito', sans-serif; background-color: var(--bg-body); color: #2c3e50; overflow-x: hidden; }
        
        /* SIDEBAR */
        .sidebar { background-color: var(--primary-color); min-height: 100vh; color: #ffffff; position: fixed; width: 250px; top: 0; left: 0; box-shadow: 2px 0 15px rgba(0,0,0,0.1); z-index: 1000;}
        .sidebar-brand { padding: 30px 20px; font-size: 1.5rem; font-weight: 900; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 15px; }
        .sidebar-menu a { color: #b8c7ce; text-decoration: none; display: flex; align-items: center; padding: 15px 25px; font-weight: 600; transition: all 0.3s ease; }
        .sidebar-menu a i { width: 30px; font-size: 1.15rem; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: var(--secondary-color); color: #ffffff; border-left: 4px solid var(--accent-color); }
        
        /* CONTENIDO PRINCIPAL */
        .main-content { margin-left: 250px; padding: 35px 45px; }
        
        /* CABECERA */
        .page-header-card { background: #ffffff; border-radius: 12px; padding: 25px 35px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .page-header-card h2 { font-weight: 800; margin: 0; color: var(--primary-color); }
        
        /* TARJETA DE LA TABLA */
        .card-panel { background: #ffffff; border-radius: 12px; border: 1px solid #ecf0f1; box-shadow: 0 5px 20px rgba(0,0,0,0.02); overflow: hidden; }
        .card-header-panel { background: #fcfdfd; border-bottom: 1px solid #ecf0f1; padding: 20px 30px; display: flex; justify-content: space-between; align-items: center; }
        
        /* ESTILOS DE TABLA PREMIUM */
        .table-panel th { color: #95a5a6; font-weight: 800; border-bottom: 2px solid #ecf0f1; padding: 20px 30px; font-size: 0.8rem; text-transform: uppercase; }
        .table-panel td { padding: 18px 30px; vertical-align: middle; font-weight: 600; border-bottom: 1px solid #f8f9fa; }
        .table-panel tbody tr:hover { background-color: #f8fbff; }
        
        /* BADGE Y BOTONES */
        .id-badge { background: #eef2f5; color: var(--secondary-color); padding: 5px 12px; border-radius: 6px; font-family: monospace; font-size: 0.95rem; font-weight: bold; }
        .status-active { background-color: #eafaf1; color: #27ae60; padding: 6px 15px; border-radius: 20px; font-size: 0.85rem; font-weight: 800; }
        .btn-delete { color: var(--danger-color); background: rgba(231, 76, 60, 0.1); border: none; padding: 8px 15px; border-radius: 8px; font-weight: bold; transition: all 0.2s; }
        .btn-delete:hover { background: var(--danger-color); color: white; }
        .btn-add { background: var(--accent-color); color: white; font-weight: bold; border-radius: 8px; padding: 10px 20px; border: none; transition: all 0.3s; }
        .btn-add:hover { background: #2980b9; color: white; box-shadow: 0 4px 10px rgba(52, 152, 219, 0.3); }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-brand text-center">
            <i class="fa-solid fa-fingerprint me-2 text-info"></i> HR Bio
        </div>
        <nav class="sidebar-menu">
            <a href="index.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a href="empleados.php" class="active"><i class="fa-solid fa-users"></i> Empleados</a>
            <a href="registros.php"><i class="fa-solid fa-history"></i> Registros Crudos</a>
            <a href="horas.php"><i class="fa-solid fa-calendar-check"></i> Horas Trabajadas</a>
            <a href="configuracion.php"><i class="fa-solid fa-gear"></i> Configuración</a>
            <a href="login.php?logout=1" class="text-danger mt-4"><i class="fa-solid fa-power-off"></i> Cerrar Sesión</a>
            <a href="asistencia.php"><i class="fa-solid fa-border-all"></i> Matriz Mensual</a>
        </nav>
    </aside>

    <main class="main-content">
        
        <?php echo $mensaje_sistema; ?>

        <header class="page-header-card">
            <div>
                <h2>Nómina de Empleados</h2>
                <span class="text-muted fw-bold">Administración de personal y vinculación biométrica</span>
            </div>
            <!-- Botón que ahora abre el formulario Modal -->
            <button type="button" class="btn-add" data-bs-toggle="modal" data-bs-target="#modalAgregarEmpleado">
                <i class="fa-solid fa-user-plus me-2"></i> Nuevo Empleado
            </button>
        </header>

        <section class="card-panel">
            <header class="card-header-panel">
                <h5 class="m-0 fw-bold text-secondary"><i class="fa-solid fa-address-book me-2"></i> Empleados Activos</h5>
                <span class="badge bg-primary rounded-pill fs-6"><?php echo $resultado->num_rows; ?> Registros</span>
            </header>
            
            <div class="table-responsive">
                <table class="table table-panel mb-0">
                    <thead>
                        <tr>
                            <th style="width: 15%;">ID Reloj</th>
                            <th style="width: 40%;">Nombre Completo</th>
                            <th style="width: 25%;">Estado en Sistema</th>
                            <th style="width: 20%; text-align: right;">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($resultado && $resultado->num_rows > 0): ?>
                            <?php while($fila = $resultado->fetch_assoc()): ?>
                                <tr>
                                    <td>
                                        <span class="id-badge">ID: <?php echo $fila['id']; ?></span>
                                    </td>
                                    <td>
                                        <div class="d-flex align-items-center fw-bold fs-6 text-dark">
                                            <div class="bg-light rounded-circle d-flex justify-content-center align-items-center me-3" style="width: 40px; height: 40px;">
                                                <i class="fa-regular fa-user text-secondary"></i>
                                            </div>
                                            <?php echo htmlspecialchars(mb_convert_case(strtolower($fila['nombre']), MB_CASE_TITLE, "UTF-8")); ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="status-active"><i class="fa-solid fa-check-circle me-1"></i> Vinculado</span>
                                    </td>
                                    <td class="text-end">
                                        <button class="btn-delete" onclick="prepararBorrado(<?php echo $fila['id']; ?>, '<?php echo htmlspecialchars($fila['nombre']); ?>')">
                                            <i class="fa-solid fa-trash-can"></i> Eliminar
                                        </button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-5 text-muted fw-bold">
                                    <i class="fa-solid fa-users-slash fs-1 mb-3 text-light"></i><br>
                                    No hay empleados registrados en la base de datos.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <!-- ==============================================================================
         MODAL: AGREGAR NUEVO EMPLEADO
         ============================================================================== -->
    <div class="modal fade" id="modalAgregarEmpleado" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow-lg">
                <div class="modal-header bg-primary text-white border-0">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-user-plus me-2"></i> Registrar Empleado</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="empleados.php">
                    <input type="hidden" name="action" value="add_employee">
                    <div class="modal-body p-4">
                        <div class="mb-4">
                            <label for="id_reloj" class="form-label fw-bold text-dark">ID del Reloj (Enroll ID)</label>
                            <input type="number" min="1" class="form-control form-control-lg" id="id_reloj" name="id_reloj" required placeholder="Ej: 5">
                            <div class="form-text text-muted mt-2">
                                <i class="fa-solid fa-circle-info text-info me-1"></i>
                                Este número debe ser <strong>exactamente el mismo</strong> que tiene asignado el empleado en el aparato biométrico físico.
                            </div>
                        </div>
                        <div class="mb-2">
                            <label for="nombre" class="form-label fw-bold text-dark">Nombre Completo</label>
                            <input type="text" class="form-control form-control-lg" id="nombre" name="nombre" required placeholder="Ej: Juan Pérez">
                        </div>
                    </div>
                    <div class="modal-footer border-0 bg-light d-flex justify-content-end">
                        <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary fw-bold px-4" style="background-color: var(--accent-color); border: none;">Guardar Empleado</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- ==============================================================================
         MODAL: CONFIRMACIÓN DE BORRADO
         ============================================================================== -->
    <div class="modal fade" id="modalConfirmacion" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header bg-danger text-white border-0">
                    <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i> Confirmar Eliminación</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body p-4 text-center">
                    <p class="fs-5 mb-1 text-dark">¿Estás seguro que deseas eliminar a <strong id="nombreModal" class="text-danger"></strong>?</p>
                    <p class="text-muted fw-bold text-sm">Esta acción también borrará todo su historial de horas y no se puede deshacer.</p>
                </div>
                <div class="modal-footer border-0 d-flex justify-content-center bg-light">
                    <button type="button" class="btn btn-secondary fw-bold px-4" data-bs-dismiss="modal">Cancelar</button>
                    <a href="#" id="btnEjecutarBorrado" class="btn btn-danger fw-bold px-4">Sí, Eliminar Permanentemente</a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function prepararBorrado(idEmpleado, nombreEmpleado) {
            document.getElementById('nombreModal').innerText = nombreEmpleado;
            document.getElementById('btnEjecutarBorrado').href = 'empleados.php?delete_id=' + idEmpleado;
            var modal = new bootstrap.Modal(document.getElementById('modalConfirmacion'));
            modal.show();
        }
    </script>
</body>
</html>
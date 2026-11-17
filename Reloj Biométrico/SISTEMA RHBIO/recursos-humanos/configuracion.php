<?php
session_start();
if (!isset($_SESSION['admin_logeado']) || $_SESSION['admin_logeado'] !== true) {
    header("Location: login.php");
    exit;
}
// ==========================================================================================
// MÓDULO DE CONFIGURACIÓN - HR BIO
// ==========================================================================================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'conexion.php';

$mensaje_sistema = '';

// ==========================================================================================
// 1. AUTO-INSTALADOR DE LA TABLA (Se ejecuta solo si no existe)
// ==========================================================================================
$check_table = $conn->query("SHOW TABLES LIKE 'configuracion'");
if ($check_table->num_rows == 0) {
    // Creamos la tabla
    $sql_create = "CREATE TABLE configuracion (
        id INT(1) PRIMARY KEY,
        ip_reloj VARCHAR(20) NOT NULL,
        puerto_reloj INT(5) NOT NULL,
        turno1_inicio TIME NOT NULL,
        turno1_fin TIME NOT NULL,
        turno2_inicio TIME NOT NULL,
        turno2_fin TIME NOT NULL,
        tolerancia_minutos INT(3) NOT NULL
    )";
    $conn->query($sql_create);
    
    // Insertamos los valores por defecto (Los que tenías hardcodeados)
    $sql_insert = "INSERT INTO configuracion (id, ip_reloj, puerto_reloj, turno1_inicio, turno1_fin, turno2_inicio, turno2_fin, tolerancia_minutos) 
                   VALUES (1, '192.168.1.171', 4370, '07:00:00', '12:00:00', '13:00:00', '16:30:00', 15)";
    $conn->query($sql_insert);
}

// ==========================================================================================
// 2. LÓGICA PARA GUARDAR CAMBIOS
// ==========================================================================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action']) && $_POST['action'] == 'update_config') {
    $ip = $conn->real_escape_string($_POST['ip_reloj']);
    $puerto = intval($_POST['puerto_reloj']);
    $t1_ini = $conn->real_escape_string($_POST['turno1_inicio']);
    $t1_fin = $conn->real_escape_string($_POST['turno1_fin']);
    $t2_ini = $conn->real_escape_string($_POST['turno2_inicio']);
    $t2_fin = $conn->real_escape_string($_POST['turno2_fin']);
    $tolerancia = intval($_POST['tolerancia_minutos']);

    $sql_update = "UPDATE configuracion SET 
                    ip_reloj = '$ip', 
                    puerto_reloj = $puerto, 
                    turno1_inicio = '$t1_ini', 
                    turno1_fin = '$t1_fin', 
                    turno2_inicio = '$t2_ini', 
                    turno2_fin = '$t2_fin', 
                    tolerancia_minutos = $tolerancia 
                   WHERE id = 1";

    if ($conn->query($sql_update) === TRUE) {
        $mensaje_sistema = "<div class='alert alert-success alert-dismissible fade show shadow-sm' role='alert'>
                                <i class='fa-solid fa-circle-check me-2'></i> Configuración actualizada correctamente.
                                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                            </div>";
    } else {
        $mensaje_sistema = "<div class='alert alert-danger alert-dismissible fade show shadow-sm' role='alert'>
                                <i class='fa-solid fa-triangle-exclamation me-2'></i> Error al guardar: " . $conn->error . "
                                <button type='button' class='btn-close' data-bs-dismiss='alert' aria-label='Close'></button>
                            </div>";
    }
}

// ==========================================================================================
// 3. OBTENER VALORES ACTUALES PARA MOSTRAR EN EL FORMULARIO
// ==========================================================================================
$resultado = $conn->query("SELECT * FROM configuracion WHERE id = 1");
$config = $resultado->fetch_assoc();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Bio - Configuración</title>
    
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
            --bg-body: #f4f7f6; 
        }
        
        body { font-family: 'Nunito', sans-serif; background-color: var(--bg-body); color: #2c3e50; overflow-x: hidden; }
        
        /* SIDEBAR (Idéntica al resto) */
        .sidebar { background-color: var(--primary-color); min-height: 100vh; color: #ffffff; position: fixed; width: 250px; top: 0; left: 0; box-shadow: 2px 0 15px rgba(0,0,0,0.1); z-index: 1000;}
        .sidebar-brand { padding: 30px 20px; font-size: 1.5rem; font-weight: 900; border-bottom: 1px solid rgba(255,255,255,0.05); margin-bottom: 15px; }
        .sidebar-menu a { color: #b8c7ce; text-decoration: none; display: flex; align-items: center; padding: 15px 25px; font-weight: 600; transition: all 0.3s ease; }
        .sidebar-menu a i { width: 30px; font-size: 1.15rem; }
        .sidebar-menu a:hover, .sidebar-menu a.active { background-color: var(--secondary-color); color: #ffffff; border-left: 4px solid var(--accent-color); }
        
        .main-content { margin-left: 250px; padding: 35px 45px; }
        
        .page-header-card { background: #ffffff; border-radius: 12px; padding: 25px 35px; box-shadow: 0 4px 15px rgba(0,0,0,0.03); margin-bottom: 30px; display: flex; justify-content: space-between; align-items: center; }
        .page-header-card h2 { font-weight: 800; margin: 0; color: var(--primary-color); }
        
        .card-panel { background: #ffffff; border-radius: 12px; border: 1px solid #ecf0f1; box-shadow: 0 5px 20px rgba(0,0,0,0.02); overflow: hidden; margin-bottom: 25px; padding: 30px; }
        
        .section-title { font-size: 1.1rem; font-weight: 800; color: #7f8c8d; text-transform: uppercase; margin-bottom: 20px; border-bottom: 2px solid #ecf0f1; padding-bottom: 10px; }
        
        .form-label { font-weight: 700; color: var(--primary-color); }
        .form-control { background-color: #f8f9fa; border: 1px solid #dfe6e9; border-radius: 8px; padding: 10px 15px; }
        .form-control:focus { border-color: var(--accent-color); box-shadow: 0 0 0 0.25rem rgba(52, 152, 219, 0.25); }
        
        .btn-save { background: var(--accent-color); color: white; font-weight: bold; border-radius: 8px; padding: 12px 30px; border: none; transition: all 0.3s; font-size: 1.1rem; }
        .btn-save:hover { background: #2980b9; color: white; box-shadow: 0 4px 15px rgba(52, 152, 219, 0.4); }
    </style>
</head>
<body>

    <aside class="sidebar">
        <div class="sidebar-brand text-center">
            <i class="fa-solid fa-fingerprint me-2 text-info"></i> HR Bio
        </div>
        <nav class="sidebar-menu">
            <a href="index.php"><i class="fa-solid fa-chart-line"></i> Dashboard</a>
            <a href="empleados.php"><i class="fa-solid fa-users"></i> Empleados</a>
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
                <h2>Configuración del Sistema</h2>
                <span class="text-muted fw-bold">Parámetros del reloj biométrico y reglas de liquidación</span>
            </div>
        </header>

        <form method="POST" action="configuracion.php">
            <input type="hidden" name="action" value="update_config">
            
            <div class="row">
                <!-- COLUMNA IZQUIERDA: RED -->
                <div class="col-md-5">
                    <div class="card-panel h-100">
                        <div class="section-title"><i class="fa-solid fa-network-wired me-2"></i> Conexión ZKTeco</div>
                        
                        <div class="mb-4">
                            <label class="form-label">Dirección IP del Reloj</label>
                            <input type="text" class="form-control" name="ip_reloj" value="<?php echo htmlspecialchars($config['ip_reloj']); ?>" required>
                            <small class="text-muted"><i class="fa-solid fa-info-circle"></i> Debe coincidir con la IP configurada en el hardware.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Puerto TCP/UDP</label>
                            <input type="number" class="form-control" name="puerto_reloj" value="<?php echo htmlspecialchars($config['puerto_reloj']); ?>" required>
                            <small class="text-muted"><i class="fa-solid fa-info-circle"></i> Por defecto en ZKTeco es 4370.</small>
                        </div>
                    </div>
                </div>

                <!-- COLUMNA DERECHA: TURNOS -->
                <div class="col-md-7">
                    <div class="card-panel h-100">
                        <div class="section-title"><i class="fa-regular fa-clock me-2"></i> Reglas de Asistencia (Turnos)</div>
                        
                        <div class="row mb-4">
                            <div class="col-6">
                                <label class="form-label text-success">Turno Mañana (Entrada)</label>
                                <input type="time" class="form-control" name="turno1_inicio" value="<?php echo $config['turno1_inicio']; ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-danger">Turno Mañana (Salida)</label>
                                <input type="time" class="form-control" name="turno1_fin" value="<?php echo $config['turno1_fin']; ?>" required>
                            </div>
                        </div>

                        <div class="row mb-4">
                            <div class="col-6">
                                <label class="form-label text-success">Turno Tarde (Entrada)</label>
                                <input type="time" class="form-control" name="turno2_inicio" value="<?php echo $config['turno2_inicio']; ?>" required>
                            </div>
                            <div class="col-6">
                                <label class="form-label text-danger">Turno Tarde (Salida)</label>
                                <input type="time" class="form-control" name="turno2_fin" value="<?php echo $config['turno2_fin']; ?>" required>
                            </div>
                        </div>

                        <hr class="text-muted">

                        <div class="row">
                            <div class="col-12">
                                <label class="form-label">Tolerancia de Llegada Tarde (Minutos)</label>
                                <div class="input-group">
                                    <input type="number" class="form-control" name="tolerancia_minutos" value="<?php echo $config['tolerancia_minutos']; ?>" required>
                                    <span class="input-group-text bg-light text-muted">minutos</span>
                                </div>
                                <small class="text-muted"><i class="fa-solid fa-info-circle"></i> Minutos de gracia antes de descontar horas.</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- BOTÓN GUARDAR -->
            <div class="text-end mt-4 mb-5">
                <button type="submit" class="btn btn-save shadow">
                    <i class="fa-solid fa-floppy-disk me-2"></i> Guardar Cambios Generales
                </button>
            </div>
        </form>

    </main>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
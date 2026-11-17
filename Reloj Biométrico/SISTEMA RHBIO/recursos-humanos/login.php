<?php
session_start();
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: login.php");
    exit;
}

// Si el usuario ya está logueado, lo mandamos directo al panel
if (isset($_SESSION['admin_logeado']) && $_SESSION['admin_logeado'] === true) {
    header("Location: index.php");
    exit;
}

$error = '';

// Procesamiento del formulario de Login
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $usuario = $_POST['usuario'];
    $password = $_POST['password'];

    // Credenciales maestras
    if ($usuario === 'admin' && $password === 'tadin2026rh') {
        $_SESSION['admin_logeado'] = true;
        header("Location: index.php");
        exit;
    } else {
        $error = 'Usuario o contraseña incorrectos.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Bio - Iniciar Sesión</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        body {
            font-family: 'Nunito', sans-serif;
            background-color: #f4f7f6;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-card {
            background: #ffffff;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.05);
            width: 100%;
            max-width: 400px;
            padding: 40px;
            text-align: center;
        }
        .login-logo {
            font-size: 3rem;
            color: #3498db;
            margin-bottom: 15px;
        }
        .login-title {
            font-weight: 800;
            color: #2c3e50;
            margin-bottom: 5px;
        }
        .login-subtitle {
            color: #7f8c8d;
            font-weight: 600;
            font-size: 0.9rem;
            margin-bottom: 30px;
        }
        .form-control {
            background-color: #f8f9fa;
            border: 1px solid #e0e6ed;
            padding: 12px 15px;
            border-radius: 8px;
            font-weight: 600;
        }
        .form-control:focus {
            border-color: #3498db;
            box-shadow: 0 0 0 0.2rem rgba(52, 152, 219, 0.25);
        }
        .btn-login {
            background-color: #2c3e50;
            color: white;
            font-weight: 800;
            padding: 12px;
            border-radius: 8px;
            width: 100%;
            border: none;
            transition: 0.3s;
            margin-top: 10px;
        }
        .btn-login:hover {
            background-color: #1a252f;
            color: white;
            box-shadow: 0 4px 10px rgba(44, 62, 80, 0.3);
        }
    </style>
</head>
<body>

    <div class="login-card">
        <div class="login-logo">
            <i class="fa-solid fa-fingerprint"></i>
        </div>
        <h3 class="login-title">HR Bio</h3>
        <p class="login-subtitle">Panel de Administración</p>

        <?php if($error): ?>
            <div class="alert alert-danger py-2 fw-bold" style="font-size: 0.85rem;">
                <i class="fa-solid fa-triangle-exclamation me-1"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">
            <div class="mb-3 text-start">
                <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">Usuario</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-user text-muted"></i></span>
                    <input type="text" name="usuario" class="form-control border-start-0 ps-0" placeholder="Ej: admin" required autocomplete="off">
                </div>
            </div>
            
            <div class="mb-4 text-start">
                <label class="form-label fw-bold text-secondary" style="font-size: 0.85rem;">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-lock text-muted"></i></span>
                    <input type="password" name="password" class="form-control border-start-0 ps-0" placeholder="••••••••" required>
                </div>
            </div>

            <button type="submit" class="btn btn-login shadow-sm">
                Iniciar Sesión <i class="fa-solid fa-arrow-right ms-2"></i>
            </button>
        </form>
    </div>

</body>
</html>
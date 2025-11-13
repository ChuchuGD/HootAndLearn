<?php
session_start();

// Evitar warnings por variables no definidas
$error = '';
$email = '';

// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hootlearn";

// Crear la conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Configurar charset UTF-8
$conn->set_charset("utf8mb4");

// Proceso de inicio de sesión de profesor
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['login_email']);
    $password = $_POST['login_password'];

    // Columnas correctas según tu tabla profesorregistro
    $stmt = $conn->prepare("SELECT ProfID, ProfNombre, ProfCorreo, Profpassword FROM profesorregistro WHERE ProfCorreo = ?");
    
    if ($stmt === false) {
        die("Error en la preparación de la consulta LOGIN: " . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        $passwordMatch = false;
        // Si la contraseña en BD está hasheada
        if (password_verify($password, $user['Profpassword'])) {
            $passwordMatch = true;
        } elseif ($password === $user['Profpassword']) {
            // retrocompatibilidad si no hay hash
            $passwordMatch = true;
        }
        
        if ($passwordMatch) {
            // Crear sesión y redirigir con maestro_id en la URL
            $_SESSION['maestro_id'] = (int)$user['ProfID'];
            $_SESSION['maestro_name'] = $user['ProfNombre'];
            $_SESSION['maestro_email'] = $user['ProfCorreo'];
            $_SESSION['login_time'] = date('Y-m-d H:i:s');

            $id = (int)$user['ProfID'];
            header("Location: ../dashboard-profesores.php?maestro_id={$id}");
            exit();
        } else {
            $error = 'Contraseña incorrecta. Por favor intenta nuevamente.';
        }
    } else {
        $error = 'No existe una cuenta con este correo electrónico.';
    }
    $stmt->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login de Profesor - Hoot & Learn</title>
    <style>
        body {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f7fafc;
            color: #2d3748;
            min-height: 100%;
        }

        html {
            height: 100%;
        }

        /* === FONDO === */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #1a202c 0%, #2d3748 50%, #4a5568 100%);
            z-index: -1;
        }

        .animated-bg::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(34,197,94,0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(102,126,234,0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(118,75,162,0.05) 0%, transparent 50%);
            animation: float 25s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            33% { transform: translateY(-15px) rotate(1deg); }
            66% { transform: translateY(-5px) rotate(-1deg); }
        }

        /* === CONTENEDOR === */
        .login-container {
            min-height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .login-card {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 24px;
            padding: 3rem;
            width: 100%;
            max-width: 500px;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
        }

        .login-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #22c55e 0%, #16a34a 50%, #667eea 100%);
        }

        /* === HEADER === */
        .login-header {
            text-align: center;
            margin-bottom: 2.5rem;
        }

        .logo {
            font-size: 2rem;
            font-weight: 800;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin-bottom: 0.5rem;
        }

        .login-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .login-subtitle {
            color: #4a5568;
            font-size: 1rem;
            font-weight: 500;
        }

        /* === FORMULARIO === */
        .login-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .form-label {
            font-weight: 600;
            color: #2d3748;
            font-size: 0.9rem;
        }

        .form-input {
            padding: 1rem 1.25rem;
            border: 2px solid rgba(160,174,192,0.3);
            border-radius: 12px;
            background: rgba(255,255,255,0.8);
            color: #2d3748;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-weight: 500;
        }

        .form-input:focus {
            outline: none;
            border-color: #22c55e;
            box-shadow: 0 0 0 4px rgba(34,197,94,0.1);
        }

        /* === CONTENEDOR DE CONTRASEÑA === */
        .password-input-container {
            position: relative;
            display: flex;
            align-items: center;
        }

        .password-input-container .form-input {
            padding-right: 2.8rem;
            width: 100%;
            box-sizing: border-box;
        }

        .password-toggle {
            position: absolute;
            right: 0.9rem;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: #718096;
            font-size: 1.2rem;
            padding: 0;
            width: 1.8rem;
            height: 1.8rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s ease;
        }

        .password-toggle:hover {
            color: #4a5568;
            background: rgba(160,174,192,0.1);
        }

        /* === BOTONES === */
        .login-btn, .nav-btn {
            display: block;
            width: 100%;
            border: none;
            border-radius: 12px;
            padding: 1rem;
            font-weight: 700;
            cursor: pointer;
            font-size: 1rem;
            transition: all 0.3s ease;
            margin-top: 0.5rem;
        }

        .login-btn {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
        }

        .login-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(34,197,94,0.4);
        }

        .nav-btn {
            background: rgba(102,126,234,0.1);
            color: #374151;
            border: 1px solid rgba(102,126,234,0.3);
            text-decoration: none;
            text-align: center;
        }

        .nav-btn:hover {
            background: rgba(102,126,234,0.2);
            transform: translateY(-2px);
        }

        .error-box {
            background: rgba(239,68,68,0.08);
            border: 1px solid rgba(239,68,68,0.3);
            color: #b91c1c;
            padding: .8rem 1rem;
            border-radius: 10px;
            font-weight: 600;
            margin-bottom: .5rem;
        }
    </style>
</head>
<body>
    <div class="animated-bg"></div>

    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="logo">🦉 Hoot & Learn</div>
                <h2 class="login-title">Acceso de Profesor</h2>
                <p class="login-subtitle">Inicia sesión con tu correo institucional</p>
            </div>

            <?php if ($error): ?>
                <div class="error-box"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>

            <form class="login-form" method="post" autocomplete="on" novalidate>
                <div class="form-group">
                    <label class="form-label" for="email">Correo electrónico</label>
                    <input id="email" name="login_email" type="email" class="form-input" placeholder="ejemplo@instituto.edu.mx" required value="<?php echo htmlspecialchars($email); ?>">
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Contraseña</label>
                    <div class="password-input-container">
                        <input id="password" name="login_password" type="password" class="form-input" placeholder="Ingresa tu contraseña" required>
                        <button type="button" class="password-toggle" onclick="togglePassword(this)">👁️</button>
                    </div>
                </div>

                <button type="submit" name="login" class="login-btn">Iniciar Sesión</button>

                <a href="profesor-portal.php" class="nav-btn">🔙 Regresar a las opciones</a>
                <a href="../../index.html" class="nav-btn">🏠 Regresar al inicio</a>
            </form>
        </div>
    </div>

    <script>
        function togglePassword(btn) {
            const input = btn.previousElementSibling;
            input.type = input.type === "password" ? "text" : "password";
        }
    </script>
</body>
</html>

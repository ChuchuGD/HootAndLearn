<?php
session_start();

// Configuración de la base de datos
$servername = "127.0.0.1";
$username = "root";
$password = "2435";   
$dbname = "HootLearn";

// Crear la conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar la conexión
if ($conn->connect_error) {
    die("Conexión fallida: " . $conn->connect_error);
}

// Configurar charset UTF-8
$conn->set_charset("utf8mb4");

// Proceso de registro de estudiante
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register'])) {
    $nombre = trim($_POST['nombre']);
    $correo = trim($_POST['correo']);
    $contraseña = $_POST['contraseña'];
    
    // Verificar si el correo ya existe
    $check_stmt = $conn->prepare("SELECT IDEst FROM EstudianteRegistro WHERE EstCorreo = ?");
    
    if ($check_stmt === false) {
        die("Error en la preparación de la consulta: " . $conn->error);
    }
    
    $check_stmt->bind_param("s", $correo);
    $check_stmt->execute();
    $check_result = $check_stmt->get_result();
    
    if ($check_result->num_rows > 0) {
        echo "<script>alert('❌ Este correo ya está registrado. Por favor inicia sesión.');</script>";
        $check_stmt->close();
    } else {
        // Hash de la contraseña (SEGURIDAD)
        $hashed_password = password_hash($contraseña, PASSWORD_DEFAULT);
        
        // Insertar nuevo estudiante
        $stmt = $conn->prepare("INSERT INTO EstudianteRegistro (EstNombre, EstCorreo, EstPassword) VALUES (?, ?, ?)");
        
        if ($stmt === false) {
            die("Error en la preparación de la consulta INSERT: " . $conn->error);
        }
        
        $stmt->bind_param("sss", $nombre, $correo, $hashed_password);
        
        if ($stmt->execute()) {
            echo "<script>
                alert('¡Registro exitoso! 🎉\\n\\nTu cuenta ha sido creada correctamente.\\nAhora serás redirigido para iniciar sesión.');
                setTimeout(function() {
                    window.location.href = 'student-portal.php?action=login';
                }, 1000);
            </script>";
        } else {
            echo "<script>alert('❌ Error en el registro: " . addslashes($stmt->error) . "');</script>";
        }
        $stmt->close();
    }
}

// Proceso de inicio de sesión de estudiante
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['login'])) {
    $email = trim($_POST['login_email']);
    $password = $_POST['login_password'];

    $stmt = $conn->prepare("SELECT IDEst, EstNombre, EstCorreo, EstPassword FROM EstudianteRegistro WHERE EstCorreo = ?");
    
    if ($stmt === false) {
        die("Error en la preparación de la consulta LOGIN: " . $conn->error);
    }
    
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $user = $result->fetch_assoc();
        
        // Verificar contraseña (compatible con hash y sin hash para testing)
        $passwordMatch = false;
        
        // Primero intentar con hash
        if (password_verify($password, $user['EstPassword'])) {
            $passwordMatch = true;
        } 
        // Si falla, verificar si la contraseña está sin hash (para retrocompatibilidad)
        elseif ($password === $user['EstPassword']) {
            $passwordMatch = true;
            
            // Actualizar a contraseña hasheada
            $hashed = password_hash($password, PASSWORD_DEFAULT);
            $update_pass = $conn->prepare("UPDATE EstudianteRegistro SET EstPassword = ? WHERE IDEst = ?");
            $update_pass->bind_param("si", $hashed, $user['IDEst']);
            $update_pass->execute();
            $update_pass->close();
        }
        
        if ($passwordMatch) {
            // Crear sesión
            $_SESSION['student_logged_in'] = true;
            $_SESSION['student_id'] = $user['IDEst'];
            $_SESSION['student_name'] = $user['EstNombre'];
            $_SESSION['student_email'] = $user['EstCorreo'];
            $_SESSION['login_time'] = date('Y-m-d H:i:s');
            
            // Redirigir al dashboard
            header("Location: student-dashboard.php");
            exit();
        } else {
            echo "<script>alert('❌ Contraseña incorrecta. Por favor intenta nuevamente.');</script>";
        }
        header("Location: student-dashboard.php");
    } else {
        echo "<script>alert('❌ No existe una cuenta con este correo electrónico.');</script>";
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
    <title>Portal de Estudiantes - Hoot & Learn</title>
    
    <style>
        body {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f7fafc;
            color: #2d3748;
            overflow-x: hidden;
            min-height: 100%;
        }

        html {
            height: 100%;
        }

        /* === FONDO ANIMADO === */
        .animated-bg {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: linear-gradient(135deg, #e2e8f0 0%, #cbd5e0 25%, #a0aec0 50%, #718096 75%, #4a5568 100%);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
            z-index: -1;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        /* === NAVEGACIÓN === */
        .navbar {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(20px);
            padding: 1rem 2rem;
            z-index: 1000;
        }

        .nav-content {
            max-width: 1200px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 1.5rem;
            font-weight: 800;
            background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* === PÁGINAS === */
        .page {
            display: none;
            min-height: 100vh;
            padding: 8rem 2rem 2rem 2rem;
            text-align: center;
            align-items: center;
            justify-content: center;
        }

        .page.active {
            display: flex;
            flex-direction: column;
        }

        .page-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 4rem;
            background: linear-gradient(135deg, #2d3748 0%, #4a5568 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* === OPCIONES === */
        .options-container {
            display: flex;
            justify-content: center;
            gap: 4rem;
            flex-wrap: wrap;
            max-width: 700px;
            margin: 0 auto;
        }

        .option-card {
            background: rgba(255, 255, 255, 0.8);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 3rem 2rem;
            width: 220px;
            cursor: pointer;
            transition: all 0.4s ease;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 8px 32px rgba(45,55,72,0.1);
        }

        .option-card:hover {
            transform: translateY(-15px) scale(1.05);
            box-shadow: 0 30px 80px rgba(45,55,72,0.2);
            background: rgba(255,255,255,0.95);
        }

        .option-icon {
            font-size: 5rem;
            margin-bottom: 1.5rem;
        }

        .option-text {
            color: #2d3748;
            font-size: 1.6rem;
            font-weight: 600;
            margin: 0;
        }

        /* === FORMULARIOS === */
        .form-container {
            max-width: 450px;
            margin: 0 auto;
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 3rem;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 8px 32px rgba(45,55,72,0.1);
        }

        .auth-form {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }

        .auth-form input {
            padding: 1.2rem 1.5rem;
            border: 1px solid rgba(45,55,72,0.2);
            border-radius: 15px;
            background: rgba(255,255,255,0.9);
            font-size: 1rem;
            color: #2d3748;
            transition: all 0.3s ease;
        }

        .auth-form input:focus {
            outline: none;
            border-color: #5a67d8;
            box-shadow: 0 0 0 3px rgba(90, 103, 216, 0.1);
            background: rgba(255,255,255,1);
        }

        .cta-button {
            background: linear-gradient(135deg, #4a5568 0%, #5a67d8 50%, #667eea 100%);
            color: white;
            border: none;
            padding: 1.2rem 2rem;
            font-size: 1.1rem;
            font-weight: 600;
            border-radius: 15px;
            cursor: pointer;
            transition: all 0.4s ease;
            box-shadow: 0 4px 20px rgba(74, 85, 104, 0.2);
        }

        .cta-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 30px rgba(74, 85, 104, 0.3);
        }

        /* === BOTÓN REGRESAR === */
        .back-btn {
            position: absolute;
            top: 2rem;
            left: 2rem;
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            color: #2d3748;
            border: 1px solid rgba(255,255,255,0.9);
            padding: 1rem 1.8rem;
            border-radius: 30px;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: all 0.4s ease;
            box-shadow: 0 4px 16px rgba(45,55,72,0.1);
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.95);
            transform: translateX(-3px);
        }

        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .page-title {
                font-size: 2rem;
            }
            
            .options-container {
                flex-direction: column;
                align-items: center;
                gap: 2rem;
            }
            
            .form-container {
                margin: 0 1rem;
                padding: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- === FONDO ANIMADO === -->
    <div class="animated-bg"></div>

    <!-- === NAVEGACIÓN === -->
    <nav class="navbar">
        <div class="nav-content">
            <div class="logo">Hoot & Learn - Estudiantes</div>
        </div>
    </nav>

    <!-- === PÁGINA PRINCIPAL DE ESTUDIANTES === -->
    <div id="studentHomePage" class="page active">
        <h1 class="page-title">Portal de Estudiantes</h1>
        
        <div class="options-container">
            <div class="option-card" onclick="showLoginPage()">
                <div class="option-icon">🔑</div>
                <p class="option-text">Iniciar Sesión</p>
            </div>
            
            <div class="option-card" onclick="showRegisterPage()">
                <div class="option-icon">📝</div>
                <p class="option-text">Registrarse</p>
            </div>
        </div>
    </div>

    <!-- === PÁGINA DE REGISTRO === -->
    <div id="registerPage" class="page">
        <button class="back-btn" onclick="showStudentHome()">← Regresar</button>
        
        <h1 class="page-title">Crear Cuenta de Estudiante</h1>
        
        <div class="form-container">
            <form class="auth-form" method="POST" action="<?php echo $_SERVER['PHP_SELF']; ?>">
                <input type="text" placeholder="Nombre completo" name="nombre" required>
                <input type="email" placeholder="Correo electrónico" name="correo" required>
                <input type="password" placeholder="Contraseña" name="contraseña" required>
                <button type="submit" name="register" class="cta-button">Crear Cuenta</button> 
            </form>
        </div>
    </div>

 

    <!-- === PÁGINA DE LOGIN === -->
    <div id="loginPage" class="page">
        <button class="back-btn" onclick="showStudentHome()">← Regresar</button>
        
        <h1 class="page-title">Iniciar Sesión</h1>
        
        <div class="form-container">
            <form class="auth-form" onsubmit="processLogin(event)">
                <input type="email" placeholder="Correo electrónico" required>
                <input type="password" placeholder="Contraseña" required>
                <button type="submit" class="cta-button">Iniciar Sesión</button>
            </form>
            
            <p style="margin-top: 2rem; color: #4a5568;">
                ¿No tienes cuenta? 
                <a href="#" onclick="showRegisterPage()" style="color: #5a67d8; text-decoration: none; font-weight: 600;">Regístrate aquí</a>
            </p>
        </div>
    </div>

    <script>
        // === NAVEGACIÓN ENTRE PÁGINAS ===
        
        function showStudentHome() {
            hideAllPages();
            document.getElementById('studentHomePage').classList.add('active');
        }
        
        function showRegisterPage() {
            hideAllPages();
            document.getElementById('registerPage').classList.add('active');
        }
        
        function showLoginPage() {
            hideAllPages();
            document.getElementById('loginPage').classList.add('active');
        }
        
        function hideAllPages() {
            const pages = document.querySelectorAll('.page');
            pages.forEach(page => page.classList.remove('active'));
        }

        // === PROCESAMIENTO DE FORMULARIOS ===
        
        function processRegister(event) {
            event.preventDefault();
            
            // Simular registro exitoso
            alert('¡Registro exitoso! 🎉\n\nTu cuenta ha sido creada correctamente.\nAhora serás redirigido para iniciar sesión.');
            
            // Redirigir automáticamente a login
            setTimeout(() => {
                showLoginPage();
            }, 1000);
        }
        
      function processLogin(event) {
    event.preventDefault();
    
    // Obtener datos del formulario
    const email = event.target.querySelector('input[type="email"]').value;
    const password = event.target.querySelector('input[type="password"]').value;
    
    // Validar que los campos no estén vacíos
    if (!email || !password) {
        alert('❌ Por favor completa todos los campos');
        return;
    }
    
    // Extraer nombre del email (parte antes del @)
    const nombre = email.split('@')[0];
    
    // Guardar datos del usuario en localStorage
    localStorage.setItem('studentName', nombre);
    localStorage.setItem('studentEmail', email);
    localStorage.setItem('studentId', 'EST-' + Math.random().toString(36).substr(2, 6).toUpperCase());
    
    // Mostrar mensaje de éxito
    alert('✅ ¡Inicio de sesión exitoso!\n\n¡Bienvenido ' + nombre + '!\nSerás redirigido a tu dashboard.');
    
    // Redirigir al dashboard
    window.location.href = 'student-dashboard.php';
}
    </script>
<script>(function(){function c(){var b=a.contentDocument||a.contentWindow.document;if(b){var d=b.createElement('script');d.innerHTML="window.__CF$cv$params={r:'98bb30c3a2bb6b95',t:'MTc1OTk4NDcyNy4wMDAwMDA='};var a=document.createElement('script');a.nonce='';a.src='/cdn-cgi/challenge-platform/scripts/jsd/main.js';document.getElementsByTagName('head')[0].appendChild(a);";b.getElementsByTagName('head')[0].appendChild(d)}}if(document.body){var a=document.createElement('iframe');a.height=1;a.width=1;a.style.position='absolute';a.style.top=0;a.style.left=0;a.style.border='none';a.style.visibility='hidden';document.body.appendChild(a);if('loading'!==document.readyState)c();else if(window.addEventListener)document.addEventListener('DOMContentLoaded',c);else{var e=document.onreadystatechange||function(){};document.onreadystatechange=function(b){e(b);'loading'!==document.readyState&&(document.onreadystatechange=e,c())}}}})();</script></body>
</html>

<?php
session_start();

// Verificar si el usuario está autenticado
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header("Location: student-portal.php");
    exit();
}

// Obtener datos del estudiante
$studentName = $_SESSION['student_name'];
$studentEmail = $_SESSION['student_email'];
$studentId = 'EST' . str_pad($_SESSION['student_id'], 3, '0', STR_PAD_LEFT);
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Hoot & Learn</title>
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

        /* === HEADER === */
        .header {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(20px);
            padding: 1.5rem 2rem;
            border-bottom: 1px solid rgba(255,255,255,0.3);
        }

        .header-content {
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

        .user-info {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .welcome-text {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
        }

        .credential-btn {
            background: linear-gradient(135deg, #5a67d8 0%, #667eea 100%);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 20px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(90, 103, 216, 0.2);
        }

        .credential-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(90, 103, 216, 0.3);
        }

        /* === MAIN CONTENT === */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 3rem 2rem;
        }

        .welcome-section {
            text-align: center;
            margin-bottom: 4rem;
        }

        .welcome-title {
            font-size: 3rem;
            font-weight: 800;
            margin-bottom: 1rem;
            background: linear-gradient(135deg, #2d3748 0%, #4a5568 50%, #5a67d8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .welcome-subtitle {
            font-size: 1.2rem;
            color: #4a5568;
            margin-bottom: 2rem;
        }

        /* === DASHBOARD OPTIONS === */
        .dashboard-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            margin-top: 3rem;
        }

        .option-card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(20px);
            border-radius: 25px;
            padding: 3rem 2rem;
            text-align: center;
            border: 1px solid rgba(255,255,255,0.9);
            cursor: pointer;
            transition: all 0.4s ease;
            box-shadow: 0 8px 32px rgba(45,55,72,0.1);
            position: relative;
            overflow: hidden;
        }

        .option-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #5a67d8, #667eea);
            transform: scaleX(0);
            transition: transform 0.4s ease;
        }

        .option-card:hover::before {
            transform: scaleX(1);
        }

        .option-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(45,55,72,0.2);
            background: rgba(255,255,255,0.95);
        }

        .option-icon {
            font-size: 4rem;
            margin-bottom: 1.5rem;
            transition: all 0.4s ease;
        }

        .option-card:hover .option-icon {
            transform: scale(1.1) rotate(5deg);
        }

        .option-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 1rem;
            color: #2d3748;
        }

        .option-description {
            color: #4a5568;
            line-height: 1.6;
        }

        /* === LOGOUT BUTTON === */
        .logout-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: rgba(239, 68, 68, 0.9);
            color: white;
            border: none;
            padding: 1rem 1.5rem;
            border-radius: 50px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(239, 68, 68, 0.2);
        }

        .logout-btn:hover {
            background: rgba(239, 68, 68, 1);
            transform: translateY(-2px);
        }

        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .welcome-title {
                font-size: 2rem;
            }
            
            .dashboard-options {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    
    <div class="animated-bg"></div>

    <header class="header">
        <div class="header-content">
            <div class="logo">Hoot & Learn</div>
            <div class="user-info">
                <span class="welcome-text">Hola, <span id="studentName"><?php echo htmlspecialchars($studentName); ?></span>!</span>
                <button class="credential-btn" onclick="generateCredential()">
                    📄 Mi Credencial
                </button>
            </div>
        </div>
    </header>

    <main class="main-content">
        <section class="welcome-section">
            <h1 class="welcome-title">¡Bienvenido a tu Dashboard!</h1>
            <p class="welcome-subtitle">
                Aquí puedes gestionar todos tus cursos, certificaciones y más.
            </p>
        </section>

        <section class="dashboard-options">
            <div class="option-card" onclick="goToEnrollCourses()">
                <div class="option-icon">📚</div>
                <h3 class="option-title">Inscribirse a Cursos</h3>
                <p class="option-description">
                    Explora y únete a nuevos cursos disponibles.
                </p>
            </div>

            <div class="option-card" onclick="goToMyCourses()">
                <div class="option-icon">🎯</div>
                <h3 class="option-title">Ver Mis Cursos</h3>
                <p class="option-description">
                    Accede a tus cursos actuales y revisa tu progreso.
                </p>
            </div>

            <div class="option-card" onclick="goToCertifications()">
                <div class="option-icon">🏆</div>
                <h3 class="option-title">Certificaciones</h3>
                <p class="option-description">
                    Consulta tus certificados obtenidos.
                </p>
            </div>
        </section>
    </main>

    <button class="logout-btn" onclick="logout()">
        🚪 Cerrar Sesión
    </button>

    <script>
        // Datos del estudiante desde PHP
        const studentData = {
            name: <?php echo json_encode($studentName); ?>,
            email: <?php echo json_encode($studentEmail); ?>,
            id: <?php echo json_encode($studentId); ?>
        };

        function generateCredential() {
            const credentialContent = `
HOOT & LEARN
CREDENCIAL DE ESTUDIANTE

═══════════════════════════════════════

👤 INFORMACIÓN DEL ESTUDIANTE

Nombre: ${studentData.name}
Email: ${studentData.email}
ID de Estudiante: ${studentData.id}
Fecha de Emisión: ${new Date().toLocaleDateString('es-ES')}

═══════════════════════════════════════

🎓 ESTADO: ACTIVO
📅 Válida hasta: ${new Date(Date.now() + 365*24*60*60*1000).toLocaleDateString('es-ES')}

Esta credencial certifica que el portador es un 
estudiante registrado en la plataforma Hoot & Learn.

═══════════════════════════════════════
            `;

            const blob = new Blob([credentialContent], { type: 'text/plain' });
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `Credencial_${studentData.name}_${studentData.id}.txt`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            window.URL.revokeObjectURL(url);

            alert('📄 ¡Credencial descargada!\\n\\nTu credencial con ID: ' + studentData.id + ' ha sido descargada exitosamente.');
        }

        function goToEnrollCourses() {
            window.location.href = 'enroll-courses.php';
        }

        function goToMyCourses() {
            window.location.href = 'my-courses.php';
        }

        function goToCertifications() {
            window.location.href = 'certifications.php';
        }

        function logout() {
            if (confirm('¿Estás seguro de que quieres cerrar sesión?')) {
                window.location.href = 'logout.php';
            }
        }
    </script>
</body>
</html>
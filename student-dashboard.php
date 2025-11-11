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
        /* COPIA TODO EL CSS DE student-dashboard.html AQUÍ */
        body {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: #f7fafc;
            color: #2d3748;
            min-height: 100%;
        }
        /* ... resto del CSS ... */
    </style>
</head>
<body>
    <!-- COPIA TODO EL HTML DE student-dashboard.html AQUÍ -->
    
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
            window.location.href = 'mis-cursos-alumno.php';
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
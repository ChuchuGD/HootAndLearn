<?php
session_start();

// Verificar autenticación
if (!isset($_SESSION['student_logged_in']) || $_SESSION['student_logged_in'] !== true) {
    header("Location: student-portal.php");
    exit();
}

// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hootlearn";

// Crear conexión
$conn = new mysqli($servername, $username, $password, $dbname);

// Verificar conexión
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

// Obtener datos del estudiante
$studentName = $_SESSION['student_name'];
$studentEmail = $_SESSION['student_email'];
$studentId = $_SESSION['student_id'];

// Variables para mensajes
$mensaje = "";
$tipo_mensaje = "";

// ============================================
// PROCESAR INSCRIPCIÓN A CURSO
// ============================================
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['enroll_course'])) {
    $cursoId = intval($_POST['curso_id']);
    $estId = $studentId;
    
    // Verificar si el estudiante ya está inscrito
    $check_stmt = $conn->prepare("SELECT InscripcionID FROM inscripciones WHERE EstID = ? AND CursoID = ?");
    $check_stmt->bind_param("ii", $estId, $cursoId);
    $check_stmt->execute();
    $result = $check_stmt->get_result();
    
    if ($result->num_rows > 0) {
        $mensaje = "Ya estás inscrito en este curso";
        $tipo_mensaje = "warning";
    } else {
        // Inscribir al estudiante
        $inscripcion_stmt = $conn->prepare("INSERT INTO inscripciones (EstID, CursoID, FechaInscripcion, Progreso, Estado) VALUES (?, ?, NOW(), 0, 'activo')");
        $inscripcion_stmt->bind_param("ii", $estId, $cursoId);
        
        if ($inscripcion_stmt->execute()) {
            $mensaje = "¡Inscripción exitosa! Ya puedes acceder al curso.";
            $tipo_mensaje = "success";
            
            // Crear notificación
            $notif_titulo = "Inscripción exitosa";
            $notif_mensaje = "Te has inscrito exitosamente al curso";
            $tipo_usuario = "estudiante";
            
            $notif_stmt = $conn->prepare("INSERT INTO notificaciones (UsuarioID, TipoUsuario, Titulo, Mensaje, Tipo) VALUES (?, ?, ?, ?, 'success')");
            $notif_stmt->bind_param("isss", $estId, $tipo_usuario, $notif_titulo, $notif_mensaje);
            $notif_stmt->execute();
            $notif_stmt->close();
        } else {
            $mensaje = "Error al inscribirse: " . $inscripcion_stmt->error;
            $tipo_mensaje = "error";
        }
        $inscripcion_stmt->close();
    }
    $check_stmt->close();
}

// ============================================
// OBTENER CURSOS DISPONIBLES
// ============================================
$filtro_categoria = isset($_GET['categoria']) ? $_GET['categoria'] : '';
$filtro_precio = isset($_GET['precio']) ? floatval($_GET['precio']) : 0;
$filtro_busqueda = isset($_GET['busqueda']) ? trim($_GET['busqueda']) : '';

// Construir query con filtros
$query = "SELECT 
            c.CursoID,
            c.NombreCurso,
            c.Descripcion,
            c.Icono,
            c.Precio,
            c.Duracion,
            c.TotalLecciones,
            CONCAT(p.ProfNombre, ' ', p.ProfApellido) AS Instructor,
            COUNT(DISTINCT i.EstID) AS TotalEstudiantes,
            CASE 
                WHEN ie.InscripcionID IS NOT NULL THEN 1 
                ELSE 0 
            END AS YaInscrito
          FROM cursos c
          LEFT JOIN profesorregistro p ON c.ProfID = p.ProfID
          LEFT JOIN inscripciones i ON c.CursoID = i.CursoID
          LEFT JOIN inscripciones ie ON c.CursoID = ie.CursoID AND ie.EstID = ?
          WHERE c.Activo = TRUE";

$params = array($studentId);
$types = "i";

if (!empty($filtro_busqueda)) {
    $query .= " AND (c.NombreCurso LIKE ? OR c.Descripcion LIKE ?)";
    $busqueda_param = "%{$filtro_busqueda}%";
    $params[] = $busqueda_param;
    $params[] = $busqueda_param;
    $types .= "ss";
}

if (!empty($filtro_categoria)) {
    $query .= " AND c.Categoria = ?";
    $params[] = $filtro_categoria;
    $types .= "s";
}

if ($filtro_precio > 0) {
    $query .= " AND c.Precio <= ?";
    $params[] = $filtro_precio;
    $types .= "d";
}

$query .= " GROUP BY c.CursoID ORDER BY c.FechaCreacion DESC";

$stmt = $conn->prepare($query);
if ($stmt === false) {
    die("Error preparando consulta: " . $conn->error);
}

// Bind dinámico de parámetros
$bind_params = array($types);
for ($i = 0; $i < count($params); $i++) {
    $bind_params[] = &$params[$i];
}
call_user_func_array(array($stmt, 'bind_param'), $bind_params);

$stmt->execute();
$cursos_result = $stmt->get_result();
$cursos_disponibles = array();

while ($row = $cursos_result->fetch_assoc()) {
    $cursos_disponibles[] = $row;
}
$stmt->close();

// ============================================
// OBTENER CATEGORÍAS DISPONIBLES
// ============================================
// ============================================
// OBTENER CATEGORÍAS DISPONIBLES
// ============================================
$categorias = array();
$categorias_query = "SELECT DISTINCT Categoria FROM cursos WHERE Activo = TRUE AND Categoria IS NOT NULL AND Categoria != '' ORDER BY Categoria";
$categorias_result = $conn->query($categorias_query);

if ($categorias_result === false) {
    // Si falla, usar categorías por defecto
    error_log("Error en query de categorías: " . $conn->error);
    $categorias = array('Programación', 'Diseño', 'Marketing', 'Idiomas', 'Negocios');
} else {
    while ($cat = $categorias_result->fetch_assoc()) {
        if (!empty($cat['Categoria'])) {
            $categorias[] = $cat['Categoria'];
        }
    }
    
    // Si no hay categorías en la BD, usar por defecto
    if (empty($categorias)) {
        $categorias = array('Programación', 'Diseño', 'Marketing', 'Idiomas', 'Negocios');
    }
}

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inscribirse a Cursos - Hoot & Learn</title>
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

        .welcome-text {
            font-size: 1.2rem;
            font-weight: 600;
            color: #2d3748;
        }

        .back-btn {
            position: fixed;
            bottom: 2rem;
            left: 2rem;
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(10px);
            color: #2d3748;
            border: 1px solid rgba(255,255,255,0.9);
            padding: 1rem;
            border-radius: 50%;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 50px;
            height: 50px;
            z-index: 1000;
            font-size: 1.2rem;
        }

        .back-btn:hover {
            background: rgba(255,255,255,0.95);
            transform: translateY(-3px);
            box-shadow: 0 5px 15px rgba(45,55,72,0.2);
        }

        /* === MAIN CONTENT === */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        .page-title {
            font-size: 2.5rem;
            font-weight: 800;
            text-align: center;
            margin-bottom: 3rem;
            background: linear-gradient(135deg, #2d3748 0%, #4a5568 50%, #5a67d8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        /* === MENSAJES === */
        .message {
            max-width: 1200px;
            margin: 0 auto 2rem auto;
            padding: 1rem 2rem;
            border-radius: 10px;
            font-weight: 600;
            display: none;
        }

        .message.show {
            display: block;
        }

        .message.success {
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.3);
            color: #16a34a;
        }

        .message.error {
            background: rgba(239,68,68,0.1);
            border: 1px solid rgba(239,68,68,0.3);
            color: #dc2626;
        }

        .message.warning {
            background: rgba(245,158,11,0.1);
            border: 1px solid rgba(245,158,11,0.3);
            color: #d97706;
        }

        /* === FILTROS === */
        .filters-section {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 3rem;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 8px 32px rgba(45,55,72,0.1);
        }

        .filters-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 1.5rem;
            color: #2d3748;
        }

        .filters-form {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr auto;
            gap: 1rem;
            align-items: end;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
        }

        .filter-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #4a5568;
        }

        .filter-input, .filter-select {
            padding: 0.8rem 1rem;
            border: 1px solid rgba(45,55,72,0.2);
            border-radius: 10px;
            background: rgba(255,255,255,0.9);
            font-size: 1rem;
            color: #2d3748;
            transition: all 0.3s ease;
        }

        .filter-input:focus, .filter-select:focus {
            outline: none;
            border-color: #5a67d8;
            box-shadow: 0 0 0 3px rgba(90, 103, 216, 0.1);
        }

        .filter-btn {
            background: linear-gradient(135deg, #5a67d8 0%, #667eea 100%);
            color: white;
            border: none;
            padding: 0.8rem 1.5rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .filter-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(90,103,216,0.3);
        }

        /* === CURSOS === */
        .courses-section {
            margin-bottom: 3rem;
        }

        .section-title {
            font-size: 1.8rem;
            font-weight: 700;
            margin-bottom: 2rem;
            color: #2d3748;
        }

        .courses-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
        }

        .course-card {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.9);
            transition: all 0.4s ease;
            box-shadow: 0 8px 32px rgba(45,55,72,0.1);
            position: relative;
            overflow: hidden;
        }

        .course-card::before {
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

        .course-card:hover::before {
            transform: scaleX(1);
        }

        .course-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 20px 60px rgba(45,55,72,0.2);
            background: rgba(255,255,255,0.95);
        }

        .course-card.enrolled {
            border: 2px solid rgba(34,197,94,0.3);
            background: rgba(34,197,94,0.05);
        }

        .course-card.enrolled::before {
            background: linear-gradient(90deg, #22c55e, #16a34a);
            transform: scaleX(1);
        }

        .enrolled-badge {
            position: absolute;
            top: 1rem;
            right: 1rem;
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
        }

        .course-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .course-title {
            font-size: 1.3rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #2d3748;
        }

        .course-instructor {
            font-size: 0.9rem;
            color: #5a67d8;
            margin-bottom: 1rem;
            font-weight: 600;
        }

        .course-description {
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 1.5rem;
        }

        .course-info {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
        }

        .course-price {
            font-size: 1.5rem;
            font-weight: 700;
            color: #5a67d8;
        }

        .course-meta {
            display: flex;
            flex-direction: column;
            gap: 0.3rem;
            font-size: 0.85rem;
            color: #718096;
        }

        .course-duration, .course-students {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .enroll-btn {
            width: 100%;
            background: linear-gradient(135deg, #5a67d8 0%, #667eea 100%);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(90, 103, 216, 0.2);
        }

        .enroll-btn:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(90, 103, 216, 0.3);
        }

        .enroll-btn:disabled {
            background: rgba(160,174,192,0.5);
            cursor: not-allowed;
            transform: none;
        }

        .enroll-btn.enrolled {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        }

        /* === NO RESULTS === */
        .no-results {
            text-align: center;
            padding: 3rem;
            color: #4a5568;
            font-size: 1.1rem;
            background: rgba(255,255,255,0.8);
            border-radius: 20px;
        }

        .no-results-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .filters-form {
                grid-template-columns: 1fr;
            }
            
            .header-content {
                flex-direction: column;
                gap: 1rem;
            }
            
            .page-title {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body>
    <!-- === FONDO ANIMADO === -->
    <div class="animated-bg"></div>

    <!-- === HEADER === -->
    <header class="header">
        <div class="header-content">
            <div class="logo">Hoot & Learn</div>
            <div class="welcome-text">Hola, <span><?php echo htmlspecialchars($studentName); ?></span>!</div>
        </div>
    </header>

    <!-- === MAIN CONTENT === -->
    <main class="main-content">
        <h1 class="page-title">Inscribirse a Cursos</h1>

        <!-- === MENSAJE === -->
        <?php if (!empty($mensaje)): ?>
        <div class="message <?php echo $tipo_mensaje; ?> show">
            <?php 
            $icono = $tipo_mensaje === 'success' ? '✅' : ($tipo_mensaje === 'error' ? '❌' : '⚠️');
            echo $icono . ' ' . htmlspecialchars($mensaje); 
            ?>
        </div>
        <?php endif; ?>

        <!-- === FILTROS === -->
        <section class="filters-section">
            <h2 class="filters-title">🔍 Buscar y Filtrar Cursos</h2>
            <form method="GET" action="" class="filters-form">
                <div class="filter-group">
                    <label class="filter-label">Buscar por nombre:</label>
                    <input type="text" name="busqueda" class="filter-input" placeholder="Escribe el nombre del curso..." value="<?php echo htmlspecialchars($filtro_busqueda); ?>">
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Categoría:</label>
                    <select name="categoria" class="filter-select">
                        <option value="">Todas las categorías</option>
                        <?php foreach ($categorias as $cat): ?>
                            <option value="<?php echo htmlspecialchars($cat); ?>" <?php echo ($filtro_categoria === $cat) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cat); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="filter-group">
                    <label class="filter-label">Precio máximo:</label>
                    <select name="precio" class="filter-select">
                        <option value="">Cualquier precio</option>
                        <option value="50" <?php echo ($filtro_precio == 50) ? 'selected' : ''; ?>>Hasta $50</option>
                        <option value="100" <?php echo ($filtro_precio == 100) ? 'selected' : ''; ?>>Hasta $100</option>
                        <option value="200" <?php echo ($filtro_precio == 200) ? 'selected' : ''; ?>>Hasta $200</option>
                        <option value="500" <?php echo ($filtro_precio == 500) ? 'selected' : ''; ?>>Hasta $500</option>
                    </select>
                </div>

                <button type="submit" class="filter-btn">🔍 Buscar</button>
            </form>
        </section>

        <!-- === CURSOS DISPONIBLES === -->
        <section class="courses-section">
            <h2 class="section-title">📚 Cursos Disponibles</h2>
            
            <?php if (count($cursos_disponibles) > 0): ?>
            <div class="courses-grid">
                <?php foreach ($cursos_disponibles as $curso): ?>
                <div class="course-card <?php echo $curso['YaInscrito'] ? 'enrolled' : ''; ?>">
                    <?php if ($curso['YaInscrito']): ?>
                    <div class="enrolled-badge">✓ Inscrito</div>
                    <?php endif; ?>
                    
                    <div class="course-icon"><?php echo htmlspecialchars($curso['Icono']); ?></div>
                    <h3 class="course-title"><?php echo htmlspecialchars($curso['NombreCurso']); ?></h3>
                    <div class="course-instructor">👨‍🏫 <?php echo htmlspecialchars($curso['Instructor']); ?></div>
                    <p class="course-description"><?php echo htmlspecialchars($curso['Descripcion']); ?></p>
                    
                    <div class="course-info">
                        <div class="course-price">$<?php echo number_format($curso['Precio'], 2); ?></div>
                        <div class="course-meta">
                            <div class="course-duration">⏱️ <?php echo htmlspecialchars($curso['Duracion']); ?></div>
                            <div class="course-students">👥 <?php echo $curso['TotalEstudiantes']; ?> estudiantes</div>
                        </div>
                    </div>
                    
                    <?php if ($curso['YaInscrito']): ?>
                    <button class="enroll-btn enrolled" disabled>
                        ✓ Ya estás inscrito
                    </button>
                    <?php else: ?>
                    <form method="POST" action="" style="margin: 0;">
                        <input type="hidden" name="curso_id" value="<?php echo $curso['CursoID']; ?>">
                        <button type="submit" name="enroll_course" class="enroll-btn">
                            📝 Inscribirse Ahora
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-results">
                <div class="no-results-icon">😕</div>
                <h3>No se encontraron cursos</h3>
                <p>No hay cursos que coincidan con tu búsqueda. Intenta con otros filtros.</p>
            </div>
            <?php endif; ?>
        </section>
    </main>

    <!-- === BOTÓN REGRESAR FLOTANTE === -->
    <a href="student-dashboard.php" class="back-btn">←</a>

    <script>
        // Auto-ocultar mensajes después de 5 segundos
        setTimeout(function() {
            const messages = document.querySelectorAll('.message.show');
            messages.forEach(function(msg) {
                msg.style.opacity = '0';
                msg.style.transition = 'opacity 0.5s';
                setTimeout(function() {
                    msg.remove();
                }, 500);
            });
        }, 5000);

        // Confirmación antes de inscribirse
        const enrollForms = document.querySelectorAll('form[method="POST"]');
        enrollForms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                const courseTitle = this.closest('.course-card').querySelector('.course-title').textContent;
                const coursePrice = this.closest('.course-card').querySelector('.course-price').textContent;
                
                if (!confirm(`¿Deseas inscribirte al curso "${courseTitle}"?\n\nPrecio: ${coursePrice}`)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
<?php
$conn->close();
?>
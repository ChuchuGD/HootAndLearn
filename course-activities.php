<?php
session_start();

// Suponiendo que el ID del estudiante se almacena en la sesión
$studentId = $_SESSION['student_id'] ?? 1; // Usar ID 1 para demo si no hay sesión

// ============================================
// LÓGICA DE CONEXIÓN Y CONSULTA
// ============================================

// Configuración de la base de datos
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hootlearn";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Error de conexión a la base de datos: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Obtener ID del curso de la URL
$courseId = $_GET['courseId'] ?? null;

if (!$courseId) {
    header("Location: my-courses.html");
    exit();
}

// 1. OBTENER DATOS DEL CURSO
$course_stmt = $conn->prepare("
    SELECT 
        c.NombreCurso, 
        c.Icono, 
        CONCAT(p.ProfNombre, ' ', p.ProfApellido) AS Instructor,
        i.Progreso,
        c.TotalLecciones
    FROM cursos c
    INNER JOIN profesorregistro p ON c.ProfID = p.ProfID
    INNER JOIN inscripciones i ON c.CursoID = i.CursoID AND i.EstID = ?
    WHERE c.CursoID = ?
");
$course_stmt->bind_param("ii", $studentId, $courseId);
$course_stmt->execute();
$course_result = $course_stmt->get_result();
$currentCourseData = $course_result->fetch_assoc();
$course_stmt->close();

if (!$currentCourseData) {
    echo "<script>alert('Curso no encontrado o no estás inscrito.'); window.location.href='my-courses.html';</script>";
    exit();
}

// 2. OBTENER ACTIVIDADES Y SU ESTADO PARA EL ESTUDIANTE (Usando la vista_actividades_pendientes)
// Modificación de la consulta SQL en course-activities.php (alrededor de la línea 56)

$activities_stmt = $conn->prepare("
    SELECT 
        a.ActividadID,
        a.Titulo,
        a.Tipo,
        a.Descripcion,
        a.Puntos,
        a.FechaVencimiento AS dueDate,
        a.Requisitos,
        a.ArchivoRequerido,
        e.Estado AS status_entrega, -- Renombrar para evitar conflicto
        e.Calificacion AS grade,
        e.FechaEntrega AS submittedDate,
        e.Retroalimentacion AS feedback,
        e.Comentarios AS submissionComments,
        e.EntregaTardia -- Nuevo campo
    FROM actividades a
    INNER JOIN inscripciones i ON a.CursoID = i.CursoID
    LEFT JOIN entregas e ON a.ActividadID = e.ActividadID AND i.EstID = e.EstID
    WHERE i.CursoID = ? AND i.EstID = ? AND a.Activo = TRUE
    ORDER BY a.FechaVencimiento ASC
");
$activities_stmt->bind_param("ii", $courseId, $studentId);
$activities_stmt->execute();
$activities_result = $activities_stmt->get_result();
$activities = [];

while ($row = $activities_result->fetch_assoc()) {
    
    // --- NUEVAS VARIABLES DE ESTADO BASADAS EN LA CONSULTA ---
    $statusEntregaBD = $row['status_entrega']; // Estado de la tabla 'entregas' ('pendiente', 'calificando', 'calificado')
    $isSubmitted = !empty($row['submittedDate']);
    $isGraded = $row['grade'] !== null;
    $EntregaTardia = $row['EntregaTardia'];

    // 1. Determinar el estado final para JavaScript ('status')
    $status = 'pending'; // Estado por defecto
    
    if ($isSubmitted) {
        // Hay una entrega registrada, determinar si está calificada
        if ($isGraded) {
            $status = 'completed'; // Calificada
        } else {
            $status = 'grading'; // Entregada, pero no calificada aún
        }
    }

    // 2. Verificar si está vencida (solo si NO ha sido entregada)
    $dueDate = new DateTime($row['dueDate']);
    $today = new DateTime();

    if (!$isSubmitted && $dueDate < $today) {
        $status = 'overdue'; // No entregada y fuera de fecha
    }

    // 3. Crear el array de actividad para JS
    $activities[] = [
        'ActividadID' => $row['ActividadID'],
        'Titulo' => $row['Titulo'],
        'Tipo' => $row['Tipo'],
        'Descripcion' => $row['Descripcion'],
        'Puntos' => $row['Puntos'],
        'dueDate' => $row['dueDate'],
        'ArchivoRequerido' => (int)($row['ArchivoRequerido'] ?? 0),
        'status' => $status, // Estado final para JS
        'isOverdue' => ($status === 'overdue'),
        'grade' => $row['grade'],
        'submittedDate' => $row['submittedDate'],
        'feedback' => $row['feedback'],
        'submissionComments' => $row['submissionComments'],
        'EntregaTardia' => $EntregaTardia,
        
        // Convertir requisitos de texto a un array de JS (separado por saltos de línea)
        'requirements' => $row['Requisitos'] ? explode("\n", $row['Requisitos']) : [],
        
        // Simulación de datos de archivo entregado
        'submittedFile' => $row['submittedDate'] ? [
            'name' => 'entrega-' . $row['ActividadID'] . '.pdf',
            'size' => '3.5 MB',
            'type' => 'PDF',
            'ruta' => 'uploads/entrega_ejemplo.pdf'
        ] : null,
    ];
}
$activities_stmt->close();

// 3. OBTENER ESTADÍSTICAS DEL CURSO PARA EL HEADER
$totalActivities = count($activities);
$completedActivities = 0;
$pendingActivities = 0;
$overdueActivities = 0;
$gradingActivities = 0;
$totalPoints = 0;

foreach ($activities as $activity) {
    $totalPoints += $activity['Puntos'];
    if ($activity['status'] === 'completed') {
        $completedActivities++;
    } elseif ($activity['status'] === 'grading') {
        $gradingActivities++;
    } elseif ($activity['status'] === 'overdue') {
        $overdueActivities++;
    } elseif ($activity['status'] === 'pending') {
        $pendingActivities++;
    }
}

$courseStats = [
    'Instructor' => $currentCourseData['Instructor'],
    'TotalActivities' => $totalActivities,
    'CompletedActivities' => $completedActivities,
    'GradingActivities' => $gradingActivities,
    'PendingActivities' => $pendingActivities,
    'OverdueActivities' => $overdueActivities,
    'TotalPoints' => $totalPoints,
    'Progress' => $currentCourseData['Progreso'] // Desde la tabla inscripciones
];

// Codificar datos PHP para ser usados en JavaScript
$currentCourseJSON = json_encode($currentCourseData);
$activitiesJSON = json_encode($activities);
$courseStatsJSON = json_encode($courseStats);

$conn->close();

?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Actividades del Curso - Hoot & Learn</title>
    <style>
        /* === (Mantener todo el CSS original de course-activities.html aquí) === */
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

        .nav-buttons {
            display: flex;
            gap: 1rem;
        }

        .nav-btn {
            background: rgba(255,255,255,0.2);
            color: #2d3748;
            border: 1px solid rgba(255,255,255,0.3);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
        }

        .nav-btn:hover {
            background: rgba(255,255,255,0.4);
            transform: translateY(-2px);
        }

        /* === MAIN CONTENT === */
        .main-content {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
        }

        /* === COURSE HEADER === */
        .course-header {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            margin-bottom: 2rem;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 8px 32px rgba(45,55,72,0.1);
        }

        .course-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #2d3748 0%, #5a67d8 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .course-stats {
            display: flex;
            gap: 2rem;
            margin-top: 1rem;
        }

        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #4a5568;
            font-weight: 600;
        }

        /* === ACTIVITIES SECTION === */
        .activities-section {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 8px 32px rgba(45,55,72,0.1);
        }

        .section-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .activities-filter {
            display: flex;
            gap: 1rem;
            margin-bottom: 2rem;
            flex-wrap: wrap;
        }

        .filter-btn {
            background: rgba(90,103,216,0.1);
            color: #5a67d8;
            border: 1px solid rgba(90,103,216,0.2);
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .filter-btn.active {
            background: linear-gradient(135deg, #5a67d8 0%, #667eea 100%);
            color: white;
            border-color: transparent;
        }

        .filter-btn:hover {
            background: rgba(90,103,216,0.2);
        }

        .filter-btn.active:hover {
            transform: translateY(-2px);
        }

        .activities-grid {
            display: grid;
            gap: 1.5rem;
        }

        .activity-card {
            background: rgba(255,255,255,0.6);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255,255,255,0.8);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .activity-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #5a67d8, #667eea);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .activity-card:hover::before {
            transform: scaleX(1);
        }

        .activity-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(45,55,72,0.15);
            background: rgba(255,255,255,0.8);
        }

        .activity-card.completed {
            border-color: rgba(34,197,94,0.3);
            background: rgba(34,197,94,0.05);
        }

        .activity-card.completed::before {
            background: linear-gradient(90deg, #22c55e, #16a34a);
            transform: scaleX(1);
        }

        .activity-card.overdue {
            border-color: rgba(239,68,68,0.3);
            background: rgba(239,68,68,0.05);
        }

        .activity-card.overdue::before {
            background: linear-gradient(90deg, #ef4444, #dc2626);
            transform: scaleX(1);
        }

        .activity-card.grading {
            border-color: rgba(139,92,246,0.3);
            background: rgba(139,92,246,0.05);
        }

        .activity-card.grading::before {
            background: linear-gradient(90deg, #8b5cf6, #7c3aed);
            transform: scaleX(1);
        }

        .late-submission-badge {
            background: rgba(239,68,68,0.1);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,0.3);
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            font-size: 0.7rem;
            font-weight: 600;
            text-transform: uppercase;
            margin-left: 0.5rem;
        }

        .activity-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .activity-info {
            flex: 1;
        }

        .activity-title {
            font-size: 1.1rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .activity-type {
            font-size: 0.8rem;
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .activity-type.assignment {
            background: rgba(90,103,216,0.1);
            color: #5a67d8;
        }

        .activity-type.project {
            background: rgba(139,92,246,0.1);
            color: #8b5cf6;
        }

        .activity-type.quiz {
            background: rgba(245,158,11,0.1);
            color: #f59e0b;
        }

        .activity-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .status-completed {
            color: #22c55e;
        }

        .status-pending {
            color: #f59e0b;
        }

        .status-overdue {
            color: #ef4444;
        }

        .status-grading {
            color: #8b5cf6;
        }

        .activity-description {
            color: #4a5568;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .activity-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .activity-meta {
            display: flex;
            gap: 1rem;
            font-size: 0.9rem;
            color: #4a5568;
        }

        .meta-item {
            display: flex;
            align-items: center;
            gap: 0.3rem;
        }

        .activity-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            background: linear-gradient(135deg, #5a67d8 0%, #667eea 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.3s ease;
        }

        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(90,103,216,0.3);
        }

        .action-btn.secondary {
            background: rgba(255,255,255,0.8);
            color: #4a5568;
            border: 1px solid rgba(160,174,192,0.3);
        }

        .action-btn.secondary:hover {
            background: rgba(255,255,255,1);
            box-shadow: 0 5px 15px rgba(45,55,72,0.1);
        }

        .action-btn.completed {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
        }

        /* === MODAL DE ENTREGA === */
        .submission-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .submission-modal.active {
            display: flex;
        }

        .modal-content {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            max-width: 600px;
            width: 90%;
            max-height: 80%;
            overflow-y: auto;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 20px 60px rgba(45,55,72,0.3);
        }

        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
        }

        .modal-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
        }

        .close-btn {
            background: rgba(239,68,68,0.1);
            color: #ef4444;
            border: 1px solid rgba(239,68,68,0.2);
            padding: 0.5rem;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1.2rem;
            transition: all 0.3s ease;
        }

        .close-btn:hover {
            background: rgba(239,68,68,0.2);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            display: block;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .file-upload-area {
            border: 2px dashed rgba(90,103,216,0.3);
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            background: rgba(90,103,216,0.05);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: rgba(90,103,216,0.5);
            background: rgba(90,103,216,0.1);
        }

        .file-upload-area.dragover {
            border-color: #5a67d8;
            background: rgba(90,103,216,0.15);
        }

        .upload-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }

        .upload-text {
            color: #4a5568;
            margin-bottom: 0.5rem;
        }

        .file-input {
            display: none;
        }

        .selected-file {
            background: rgba(34,197,94,0.1);
            border: 1px solid rgba(34,197,94,0.3);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            display: none;
        }

        .selected-file.show {
            display: block;
        }

        .file-info {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #22c55e;
            font-weight: 600;
        }

        .form-textarea {
            width: 100%;
            min-height: 100px;
            padding: 1rem;
            border: 1px solid rgba(160,174,192,0.3);
            border-radius: 8px;
            background: rgba(255,255,255,0.8);
            color: #2d3748;
            font-family: inherit;
            resize: vertical;
        }

        .form-textarea:focus {
            outline: none;
            border-color: #5a67d8;
            box-shadow: 0 0 0 3px rgba(90,103,216,0.1);
        }

        .submit-btn {
            width: 100%;
            background: linear-gradient(135deg, #5a67d8 0%, #667eea 100%);
            color: white;
            border: none;
            padding: 1rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(90,103,216,0.3);
        }

        .submit-btn:disabled {
            background: rgba(160,174,192,0.5);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .progress-container {
            display: none;
            margin-top: 1rem;
        }

        .progress-container.show {
            display: block;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
            color: #4a5568;
            font-weight: 600;
        }

        .progress-bar {
            width: 100%;
            height: 10px;
            background: rgba(160,174,192,0.3);
            border-radius: 5px;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #5a67d8, #667eea);
            border-radius: 5px;
            transition: width 0.3s ease;
            width: 0%;
        }

        .success-message {
            display: none;
            text-align: center;
            padding: 2rem;
        }

        .success-message.show {
            display: block;
        }

        .success-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .success-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #22c55e;
            margin-bottom: 0.5rem;
        }

        .success-text {
            color: #4a5568;
            margin-bottom: 2rem;
        }

        /* === ESTILOS PARA VER ENTREGA === */
        .submission-details {
            max-height: 70vh;
            overflow-y: auto;
        }

        .submitted-file-info {
            background: rgba(34,197,94,0.05);
            border: 1px solid rgba(34,197,94,0.2);
            border-radius: 10px;
            padding: 1.5rem;
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .file-icon {
            font-size: 2rem;
            color: #22c55e;
        }

        .file-details {
            flex: 1;
        }

        .file-name {
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.3rem;
        }

        .file-meta {
            font-size: 0.9rem;
            color: #4a5568;
        }

        .download-btn {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            border: none;
            padding: 0.5rem 1rem;
            border-radius: 8px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .download-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(34,197,94,0.3);
        }

        .submission-comments {
            background: rgba(255,255,255,0.8);
            border: 1px solid rgba(160,174,192,0.2);
            border-radius: 10px;
            padding: 1.5rem;
            line-height: 1.6;
            color: #4a5568;
            font-style: italic;
        }

        .no-comments {
            color: #9ca3af;
            text-align: center;
        }

        .grade-info {
            background: rgba(90,103,216,0.05);
            border: 1px solid rgba(90,103,216,0.2);
            border-radius: 10px;
            padding: 1.5rem;
        }

        .grade-score {
            display: flex;
            align-items: center;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .grade-number {
            font-size: 2rem;
            font-weight: 800;
            color: #22c55e;
        }

        .grade-total {
            font-size: 1.2rem;
            color: #4a5568;
        }

        .grade-status {
            background: rgba(34,197,94,0.1);
            color: #22c55e;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .grade-feedback {
            background: rgba(255,255,255,0.8);
            border: 1px solid rgba(160,174,192,0.2);
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            line-height: 1.5;
            color: #4a5568;
        }

        .pending-grade {
            background: rgba(139,92,246,0.1);
            color: #8b5cf6;
            padding: 1rem;
            border-radius: 10px;
            text-align: center;
            font-weight: 600;
        }

        /* === MODAL DE DETALLES === */
        .details-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }

        .details-modal.active {
            display: flex;
        }

        .details-content {
            background: rgba(255,255,255,0.95);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            max-width: 700px;
            width: 90%;
            max-height: 80%;
            overflow-y: auto;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 20px 60px rgba(45,55,72,0.3);
        }

        .details-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(160,174,192,0.3);
        }

        .details-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .details-subtitle {
            color: #4a5568;
            font-size: 0.9rem;
        }

        .details-section {
            margin-bottom: 2rem;
        }

        .details-section-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .details-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 1.5rem;
        }

        .details-info-item {
            background: rgba(90,103,216,0.05);
            border: 1px solid rgba(90,103,216,0.1);
            border-radius: 10px;
            padding: 1rem;
        }

        .details-info-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #4a5568;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .details-info-value {
            font-size: 1rem;
            font-weight: 600;
            color: #2d3748;
        }

        .details-description {
            background: rgba(255,255,255,0.8);
            border: 1px solid rgba(160,174,192,0.2);
            border-radius: 10px;
            padding: 1.5rem;
            line-height: 1.6;
            color: #4a5568;
        }

        .details-requirements {
            background: rgba(245,158,11,0.05);
            border: 1px solid rgba(245,158,11,0.2);
            border-radius: 10px;
            padding: 1.5rem;
        }

        .requirements-list {
            list-style: none;
            padding: 0;
            margin: 0;
        }

        .requirements-list li {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            color: #4a5568;
        }

        .requirements-list li:before {
            content: "✓";
            color: #22c55e;
            font-weight: bold;
            flex-shrink: 0;
        }

        /* === EMPTY STATE === */
        .empty-state {
            text-align: center;
            padding: 3rem;
            color: #4a5568;
        }

        .empty-state-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .course-stats {
                flex-direction: column;
                gap: 1rem;
            }
            
            .activities-filter {
                justify-content: center;
            }
            
            .activity-details {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .activity-actions {
                width: 100%;
                justify-content: flex-end;
            }

            .modal-content {
                width: 95%;
                padding: 1.5rem;
            }
        }
    </style>
</head>
<body>
    <div class="animated-bg"></div>

    <header class="header">
        <div class="header-content">
            <div class="logo">Hoot & Learn</div>
            <div class="nav-buttons">
                <a href="my-courses.html" class="nav-btn">← Mis Cursos</a>
                <a href="student-dashboard.html" class="nav-btn">Dashboard</a>
                <a href="index.html" class="nav-btn">Cerrar Sesión</a>
            </div>
        </div>
    </header>

    <main class="main-content">
        <section class="course-header">
            <h1 class="course-title" id="courseTitle">
                <span id="courseIcon"><?php echo htmlspecialchars($currentCourseData['Icono'] ?? '📚'); ?></span>
                <span id="courseName"><?php echo htmlspecialchars($currentCourseData['NombreCurso'] ?? 'Cargando curso...'); ?></span>
            </h1>
            <div class="course-stats" id="courseStats">
                </div>
        </section>

        <section class="activities-section">
            <h2 class="section-title">
                <span>📝</span>
                Actividades del Curso
            </h2>
            
            <div class="activities-filter">
                <button class="filter-btn active" onclick="filterActivities('all', this)">
                    Todas
                </button>
                <button class="filter-btn" onclick="filterActivities('pending', this)">
                    Pendientes
                </button>
                <button class="filter-btn" onclick="filterActivities('completed', this)">
                    Completadas
                </button>
                <button class="filter-btn" onclick="filterActivities('overdue', this)">
                    Vencidas
                </button>
                <button class="filter-btn" onclick="filterActivities('grading', this)">
                    En Calificación
                </button>
            </div>

            <div class="activities-grid" id="activitiesGrid">
                </div>
        </section>
    </main>

    <div class="details-modal" id="detailsModal">
        <div class="details-content">
            <div class="details-header">
                <div>
                    <h3 class="details-title" id="detailsActivityTitle">Detalles de la Actividad</h3>
                    <div class="details-subtitle" id="detailsActivityType">Tipo de actividad</div>
                </div>
                <button class="close-btn" onclick="closeDetailsModal()">×</button>
            </div>
            
            <div class="details-section">
                <div class="details-section-title">
                    <span>📊</span>
                    Información General
                </div>
                <div class="details-info-grid" id="detailsInfoGrid">
                    </div>
            </div>

            <div class="details-section">
                <div class="details-section-title">
                    <span>📝</span>
                    Descripción
                </div>
                <div class="details-description" id="detailsDescription">
                    </div>
            </div>

            <div class="details-section">
                <div class="details-section-title">
                    <span>📋</span>
                    Requisitos de Entrega
                </div>
                <div class="details-requirements">
                    <ul class="requirements-list" id="detailsRequirements">
                        </ul>
                </div>
            </div>
        </div>
    </div>

    <div class="submission-modal" id="viewSubmissionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="viewSubmissionTitle">Detalles de la Entrega</h3>
                <button class="close-btn" onclick="closeViewSubmissionModal()">×</button>
            </div>
            
            <div class="submission-details">
                <div class="details-section">
                    <div class="details-section-title">
                        <span>📊</span>
                        Información de la Entrega
                    </div>
                    <div class="details-info-grid" id="submissionInfoGrid">
                        </div>
                </div>

                <div class="details-section" id="submissionFileSection">
                    <div class="details-section-title">
                        <span>📎</span>
                        Archivo Entregado
                    </div>
                    <div class="submitted-file-info" id="submittedFileInfo">
                        </div>
                </div>

                <div class="details-section" id="submissionCommentsSection">
                    <div class="details-section-title">
                        <span>💬</span>
                        Comentarios del Estudiante
                    </div>
                    <div class="submission-comments" id="submissionComments">
                        </div>
                </div>

                <div class="details-section" id="gradeSection">
                    <div class="details-section-title">
                        <span>🎯</span>
                        Calificación y Retroalimentación
                    </div>
                    <div class="grade-info" id="gradeInfo">
                        </div>
                </div>
            </div>
        </div>
    </div>

    <div class="submission-modal" id="submissionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title" id="modalActivityTitle">Entregar Actividad</h3>
                <button class="close-btn" onclick="closeSubmissionModal()">×</button>
            </div>
            
            <form id="submissionForm" class="submission-form">
                <div class="form-group" id="fileUploadSection">
                    <label class="form-label">📎 Archivo de Entrega</label>
                    <div class="file-upload-area" onclick="document.getElementById('fileInput').click()">
                        <div class="upload-icon">📁</div>
                        <div class="upload-text">Haz clic aquí o arrastra tu archivo</div>
                        <div style="font-size: 0.9rem; color: #718096;">Formatos: PDF, DOC, DOCX, ZIP (Max: 10MB)</div>
                    </div>
                    <input type="file" id="fileInput" class="file-input" accept=".pdf,.doc,.docx,.zip" onchange="handleFileSelect(event)">
                    <div class="selected-file" id="selectedFile">
                        <div class="file-info">
                            <span>✅</span>
                            <span id="fileName">archivo.pdf</span>
                            <span id="fileSize">(2.5 MB)</span>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">💬 Comentarios (Opcional)</label>
                    <textarea class="form-textarea" id="submissionCommentsText" placeholder="Agrega cualquier comentario sobre tu entrega..."></textarea>
                </div>

                <div class="progress-container" id="progressContainer">
                    <div class="progress-label">
                        <span>Subiendo archivo...</span>
                        <span id="progressPercent">0%</span>
                    </div>
                    <div class="progress-bar">
                        <div class="progress-fill" id="progressFill"></div>
                    </div>
                </div>

                <button type="submit" class="submit-btn" id="submitBtn">
                    📤 Entregar Actividad
                </button>
            </form>

            <div class="success-message" id="successMessage">
                <div class="success-icon">🎉</div>
                <div class="success-title">¡Entrega Exitosa!</div>
                <div class="success-text">Tu actividad ha sido entregada correctamente. Recibirás una calificación pronto.</div>
                <button class="submit-btn" onclick="closeSubmissionModal()">
                    Continuar
                </button>
            </div>
        </div>
    </div>

    <script>
        // === DATOS PROVENIENTES DE PHP ===
        const currentCourseData = <?php echo $currentCourseJSON; ?>;
        const activities = <?php echo $activitiesJSON; ?>;
        const courseStats = <?php echo $courseStatsJSON; ?>;
        const studentId = <?php echo $studentId; ?>;
        const courseId = <?php echo $courseId; ?>;
        
        let currentActivity = null;
        let currentFilter = 'all';

        // === INICIALIZACIÓN ===
        document.addEventListener('DOMContentLoaded', function() {
            loadCourseHeader();
            loadActivities();
            setupModalEvents();
        });

        // === CARGAR HEADER DEL CURSO ===
        function loadCourseHeader() {
            const stats = courseStats;

            document.getElementById('courseIcon').textContent = currentCourseData.Icono;
            document.getElementById('courseName').textContent = currentCourseData.NombreCurso;
            document.title = `${currentCourseData.NombreCurso} - Actividades - Hoot & Learn`;

            const gradingHtml = stats.GradingActivities > 0 ? `
                <div class="stat-item">
                    <span>🔍</span>
                    <span>${stats.GradingActivities} en calificación</span>
                </div>
            ` : '';

            const overdueHtml = stats.OverdueActivities > 0 ? `
                <div class="stat-item">
                    <span>⚠️</span>
                    <span>${stats.OverdueActivities} vencidas</span>
                </div>
            ` : '';

            document.getElementById('courseStats').innerHTML = `
                <div class="stat-item">
                    <span>👨‍🏫</span>
                    <span>${stats.Instructor}</span>
                </div>
                <div class="stat-item">
                    <span>📊</span>
                    <span>${stats.CompletedActivities}/${stats.TotalActivities} completadas</span>
                </div>
                <div class="stat-item">
                    <span>⏳</span>
                    <span>${stats.PendingActivities} pendientes</span>
                </div>
                ${gradingHtml}
                ${overdueHtml}
            `;
        }

        // === CARGAR ACTIVIDADES ===
        function loadActivities() {
            const activitiesGrid = document.getElementById('activitiesGrid');
            let activitiesToDisplay = activities;

            if (currentFilter !== 'all') {
                activitiesToDisplay = activities.filter(activity => activity.status === currentFilter);
            }

            if (activitiesToDisplay.length === 0) {
                activitiesGrid.innerHTML = `
                    <div class="empty-state">
                        <div class="empty-state-icon">📝</div>
                        <h3>No hay actividades ${currentFilter === 'all' ? '' : currentFilter === 'pending' ? 'pendientes' : currentFilter === 'completed' ? 'completadas' : currentFilter === 'grading' ? 'en calificación' : 'vencidas'}</h3>
                        <p>Las actividades aparecerán aquí cuando estén disponibles.</p>
                    </div>
                `;
                return;
            }

            activitiesGrid.innerHTML = activitiesToDisplay.map(activity => {
                const typeIcons = {
                    assignment: '📝',
                    project: '🚀',
                    quiz: '❓'
                };

                const statusIcons = {
                    completed: '✅',
                    pending: '⏳',
                    overdue: '⚠️',
                    grading: '🔍'
                };

                const statusTexts = {
                    completed: 'Completada',
                    pending: 'Pendiente',
                    overdue: 'Vencida',
                    grading: 'Pendiente de Calificación'
                };
                
                // Determinar si la actividad está realmente vencida para el estado (isOverdue es de la BD)
                const isOverdue = activity.isOverdue == 1;
                
                let cardClass = activity.status;
                if (isOverdue && activity.status === 'pending') {
                    cardClass = 'overdue';
                }

                return `
                    <div class="activity-card ${cardClass}">
                        <div class="activity-header">
                            <div class="activity-info">
                                <div class="activity-title">
                                    ${typeIcons[activity.Tipo] || '📝'} ${activity.Titulo}
                                    <span class="activity-type ${activity.Tipo}">${activity.Tipo}</span>
                                    ${activity.EntregaTardia == 1 ? '<span class="late-submission-badge">Entrega Tardía</span>' : ''}
                                </div>
                                <div class="activity-status status-${activity.status}">
                                    ${statusIcons[activity.status]}
                                    ${statusTexts[activity.status]}
                                    ${activity.grade ? ` (${Math.round(activity.grade)}/${activity.Puntos})` : ''}
                                </div>
                            </div>
                        </div>
                        
                        <div class="activity-description">
                            ${activity.Descripcion}
                        </div>
                        
                        <div class="activity-details">
                            <div class="activity-meta">
                                <div class="meta-item">
                                    <span>📅</span>
                                    <span>Vence: ${formatDate(activity.dueDate)}</span>
                                </div>
                                <div class="meta-item">
                                    <span>🎯</span>
                                    <span>${activity.Puntos} puntos</span>
                                </div>
                                ${activity.submittedDate ? `
                                <div class="meta-item">
                                    <span>✅</span>
                                    <span>Entregado: ${formatDate(activity.submittedDate)}</span>
                                </div>
                                ` : ''}
                            </div>
                            
                            <div class="activity-actions">
                                ${activity.status === 'completed' || activity.status === 'grading' ? `
                                    <button class="action-btn ${activity.status === 'completed' ? 'completed' : ''}" onclick="viewSubmission(${activity.ActividadID})">
                                        Ver Entrega
                                    </button>
                                    <button class="action-btn secondary" onclick="viewDetails(${activity.ActividadID})">
                                        Detalles
                                    </button>
                                ` : `
                                    <button class="action-btn" onclick="startActivity(${activity.ActividadID})">
                                        ${isOverdue ? 'Entregar Tarde' : 'Comenzar'}
                                    </button>
                                    <button class="action-btn secondary" onclick="viewDetails(${activity.ActividadID})">
                                        Detalles
                                    </button>
                                `}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // === FILTRAR ACTIVIDADES ===
        function filterActivities(filter, element) {
            currentFilter = filter;
            
            document.querySelectorAll('.filter-btn').forEach(btn => {
                btn.classList.remove('active');
            });
            element.classList.add('active');
            
            loadActivities();
        }

        // === ACCIONES DE ACTIVIDADES ===
        function startActivity(activityId) {
            currentActivity = activities.find(a => a.ActividadID === activityId);
            if (!currentActivity) return;
            
            document.getElementById('modalActivityTitle').textContent = `Entregar: ${currentActivity.Titulo}`;
            
            // Mostrar u ocultar el input de archivo (con protección si el elemento no existe)
            const fileUploadEl = document.getElementById('fileUploadSection');
            if (fileUploadEl) {
                fileUploadEl.style.display = currentActivity.ArchivoRequerido == 1 ? 'block' : 'none';
            }
            
            document.getElementById('submissionModal').classList.add('active');
            window.currentActivityId = activityId;
        }

        function viewDetails(activityId) {
            const activity = activities.find(a => a.ActividadID === activityId);
            if (!activity) return;
            
            // Llenar información del modal (Detalles)
            document.getElementById('detailsActivityTitle').textContent = activity.Titulo;
            document.getElementById('detailsActivityType').textContent = `${activity.Tipo.toUpperCase()} • ${activity.Puntos} puntos`;
            
            const statusTexts = {
                completed: 'Completada',
                pending: 'Pendiente',
                overdue: 'Vencida',
                grading: 'Pendiente de Calificación'
            };
            
            // Calcular días restantes
            const dueDate = new Date(activity.dueDate);
            const today = new Date();
            const timeDiff = dueDate.getTime() - today.getTime();
            const daysUntilDue = Math.ceil(timeDiff / (1000 * 60 * 60 * 24));
            
            const timeStatus = activity.status === 'pending' || activity.status === 'overdue' 
                ? (daysUntilDue > 0 ? `${daysUntilDue} días restantes` : 'Vencido') 
                : (activity.submittedDate ? `Entregado el ${formatDate(activity.submittedDate)}` : 'N/A');

            document.getElementById('detailsInfoGrid').innerHTML = `
                <div class="details-info-item">
                    <div class="details-info-label">Estado</div>
                    <div class="details-info-value">${statusTexts[activity.status]}</div>
                </div>
                <div class="details-info-item">
                    <div class="details-info-label">Fecha de Vencimiento</div>
                    <div class="details-info-value">${formatDate(activity.dueDate)}</div>
                </div>
                <div class="details-info-item">
                    <div class="details-info-label">Puntos</div>
                    <div class="details-info-value">${activity.Puntos} pts</div>
                </div>
                <div class="details-info-item">
                    <div class="details-info-label">Tiempo</div>
                    <div class="details-info-value">${timeStatus}</div>
                </div>
                ${activity.grade ? `
                <div class="details-info-item">
                    <div class="details-info-label">Calificación</div>
                    <div class="details-info-value">${Math.round(activity.grade)}/${activity.Puntos}</div>
                </div>
                ` : ''}
            `;
            
            // Descripción
            document.getElementById('detailsDescription').textContent = activity.Descripcion;
            
            // Requisitos
            if (activity.requirements && activity.requirements.length > 0) {
                document.getElementById('detailsRequirements').innerHTML = activity.requirements
                    .map(req => `<li>${req.trim()}</li>`)
                    .join('');
            } else {
                document.getElementById('detailsRequirements').innerHTML = '<li>No hay requisitos específicos definidos</li>';
            }
            
            // Mostrar modal
            document.getElementById('detailsModal').classList.add('active');
        }

        function closeDetailsModal() {
            document.getElementById('detailsModal').classList.remove('active');
        }

        function viewSubmission(activityId) {
            const activity = activities.find(a => a.ActividadID === activityId);
            if (!activity || !activity.submittedDate) return;
            
            // Llenar información del modal (Ver Entrega)
            document.getElementById('viewSubmissionTitle').textContent = `Entrega: ${activity.Titulo}`;
            
            // Información de la entrega
            const isLate = activity.EntregaTardia == 1;

            document.getElementById('submissionInfoGrid').innerHTML = `
                <div class="details-info-item">
                    <div class="details-info-label">Estado</div>
                    <div class="details-info-value">${activity.status === 'completed' ? 'Calificada' : 'En Revisión'}</div>
                </div>
                <div class="details-info-item">
                    <div class="details-info-label">Fecha de Entrega</div>
                    <div class="details-info-value">${formatDate(activity.submittedDate)}</div>
                </div>
                <div class="details-info-item">
                    <div class="details-info-label">Fecha Límite</div>
                    <div class="details-info-value">${formatDate(activity.dueDate)}</div>
                </div>
                <div class="details-info-item">
                    <div class="details-info-label">Tipo de Entrega</div>
                    <div class="details-info-value" style="color: ${isLate ? '#ef4444' : '#22c55e'};">
                        ${isLate ? 'Entrega Tardía' : 'A Tiempo'}
                    </div>
                </div>
            `;
            
            // Archivo entregado
            const submissionFileSection = document.getElementById('submissionFileSection');
            if (activity.submittedFile) {
                submissionFileSection.style.display = 'block';
                const fileTypeIcons = {
                    'PDF': '📕', 'DOC': '📘', 'DOCX': '📘', 'ZIP': '📦', 'JavaScript': '📄', 'Image': '🖼️'
                };
                
                document.getElementById('submittedFileInfo').innerHTML = `
                    <div class="file-icon">${fileTypeIcons[activity.submittedFile.type] || '📄'}</div>
                    <div class="file-details">
                        <div class="file-name">${activity.submittedFile.name}</div>
                        <div class="file-meta">Tamaño: ${activity.submittedFile.size} • Tipo: ${activity.submittedFile.type}</div>
                    </div>
                    <a href="${activity.submittedFile.ruta}" target="_blank" class="download-btn">
                        <span>⬇️</span>
                        Descargar
                    </a>
                `;
            } else {
                submissionFileSection.style.display = 'none';
            }
            
            // Comentarios del estudiante
            const submissionCommentsDiv = document.getElementById('submissionComments');
            if (activity.submissionComments && activity.submissionComments.trim()) {
                submissionCommentsDiv.innerHTML = `"${activity.submissionComments}"`;
            } else {
                submissionCommentsDiv.innerHTML = `<div class="no-comments">El estudiante no agregó comentarios adicionales.</div>`;
            }
            
            // Calificación y retroalimentación
            const gradeInfo = document.getElementById('gradeInfo');
            if (activity.status === 'completed' && activity.grade) {
                const gradeColor = activity.grade >= 90 ? '#22c55e' : activity.grade >= 70 ? '#f59e0b' : '#ef4444';
                const gradeStatus = activity.grade >= 90 ? 'Excelente' : activity.grade >= 70 ? 'Aprobado' : 'Necesita Mejora';
                
                gradeInfo.innerHTML = `
                    <div class="grade-score">
                        <div class="grade-number" style="color: ${gradeColor};">${Math.round(activity.grade)}</div>
                        <div class="grade-total">/ ${activity.Puntos} puntos</div>
                        <div class="grade-status" style="background: ${gradeColor}20; color: ${gradeColor};">
                            ${gradeStatus}
                        </div>
                    </div>
                    ${activity.feedback ? `
                        <div class="grade-feedback">
                            <strong>Retroalimentación del Profesor:</strong><br>
                            ${activity.feedback}
                        </div>
                    ` : ''}
                `;
            } else if (activity.status === 'grading') {
                gradeInfo.innerHTML = `
                    <div class="pending-grade">
                        <div style="font-size: 2rem; margin-bottom: 0.5rem;">🔍</div>
                        <div>Tu entrega está siendo revisada</div>
                        <div style="font-size: 0.9rem; margin-top: 0.5rem; opacity: 0.8;">
                            Recibirás una notificación cuando esté calificada
                        </div>
                    </div>
                `;
            } else {
                 gradeInfo.innerHTML = `<div class="no-comments">Aún no hay calificación.</div>`;
            }
            
            // Mostrar modal
            document.getElementById('viewSubmissionModal').classList.add('active');
        }

        function closeViewSubmissionModal() {
            document.getElementById('viewSubmissionModal').classList.remove('active');
        }
        
        // === FUNCIONES DEL MODAL DE ENTREGA ===

        // Subir archivo y comentarios
        async function submitActivity() {
    const activityId = window.currentActivityId;
    const comments = document.getElementById('submissionCommentsText').value.trim();
    const fileInput = document.getElementById('fileInput');
    const file = fileInput.files[0];
    
    const activity = activities.find(a => a.ActividadID === activityId);

    if (!activity) {
        alert('Error: No se pudo encontrar la actividad actual.');
        return;
    }

    // Validación de archivo requerido
    if (activity.ArchivoRequerido == 1 && !file) {
        alert('Por favor selecciona un archivo para entregar, ya que esta actividad lo requiere.');
        return;
    }
    
    // Validación de tamaño de archivo (máximo 10MB)
    if (file && file.size > 10 * 1024 * 1024) {
        alert('El archivo es demasiado grande. Máximo 10MB permitido.');
        return;
    }

    const formData = new FormData();
    formData.append('activity_id', activityId);
    formData.append('student_id', studentId);
    formData.append('comments', comments);
    
    if (file) {
        formData.append('submission_file', file);
    }

    // Referencias a elementos del modal
    const submitBtn = document.getElementById('submitBtn');
    const progressContainer = document.getElementById('progressContainer');
    const progressFill = document.getElementById('progressFill');

    // 1. Iniciar animación de subida
    submitBtn.disabled = true;
    submitBtn.textContent = 'Subiendo...';
    progressContainer.classList.add('show');
    
    let progress = 0;
    let interval;

    // Simulación del progreso de la subida (En un entorno real, esto se actualizaría con eventos de progreso del XHR)
    interval = setInterval(() => {
        progress += Math.random() * 15;
        if (progress > 100) progress = 100;
        
        progressFill.style.width = progress + '%';
        document.getElementById('progressPercent').textContent = Math.round(progress) + '%';
        
        if (progress >= 100) {
            clearInterval(interval);
            
            // 2. Ejecutar la petición al servidor
            setTimeout(async () => {
                try {
                    const response = await fetch('submit_activity.php', {
                        method: 'POST',
                        body: formData // Enviamos FormData (soporta archivo)
                    });

                    const result = await response.json();

                    if (!response.ok || !result.success) {
                        throw new Error(result.message || 'Error en el servidor al finalizar la entrega.');
                    }

                    // ÉXITO: Actualizar estado local
                    const submittedDate = new Date().toISOString().split('T')[0];
                    const isLate = result.is_late;
                    const localIndex = activities.findIndex(a => a.ActividadID === activityId);
                    
                    if(localIndex !== -1) {
                        // Actualizar el estado con la respuesta del servidor
                        activities[localIndex].status = result.status; // 'grading'
                        activities[localIndex].submittedDate = submittedDate;
                        activities[localIndex].EntregaTardia = isLate ? 1 : 0;
                        activities[localIndex].submissionComments = comments;
                        activities[localIndex].submittedFile = file ? { 
                            name: file.name, 
                            size: (file.size / 1024 / 1024).toFixed(1) + ' MB', 
                            type: file.type.split('/').pop().toUpperCase(), 
                            ruta: result.file_path || '#' 
                        } : null;
                    }

                    document.getElementById('submissionForm').style.display = 'none';
                    document.getElementById('successMessage').classList.add('show');
                    
                    // Recargar listas para reflejar el estado 'grading'
                    loadCourseHeader();
                    loadActivities();

                } catch (error) {
                    alert(`❌ Error al entregar la actividad: ${error.message}`);
                    // Detener animación y restaurar botón en caso de fallo
                    progressContainer.classList.remove('show');
                    submitBtn.disabled = false;
                    submitBtn.textContent = '📤 Entregar Actividad';
                    progressFill.style.width = '0%';
                    console.error(error);
                }
            }, 500); // Pequeña pausa para que la barra llegue al 100%
        }
    }, 200);
}
        
        function closeSubmissionModal() {
            const modal = document.getElementById('submissionModal');
            modal.classList.remove('active');
            
            // Resetear el formulario
            document.getElementById('submissionForm').reset();
            document.getElementById('selectedFile').classList.remove('show');
            document.getElementById('progressContainer').classList.remove('show');
            document.getElementById('successMessage').classList.remove('show');
            document.getElementById('submissionForm').style.display = 'block';
            document.getElementById('submitBtn').disabled = false;
            document.getElementById('submitBtn').textContent = '📤 Entregar Actividad';
            
            window.currentActivityId = null;
        }

        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) {
                 document.getElementById('selectedFile').classList.remove('show');
                 return;
            }
            
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = `(${(file.size / 1024 / 1024).toFixed(1)} MB)`;
            document.getElementById('selectedFile').classList.add('show');
        }

        function setupModalEvents() {
            document.getElementById('submissionForm').addEventListener('submit', function(e) {
                e.preventDefault();
                submitActivity();
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    closeSubmissionModal();
                    closeDetailsModal();
                    closeViewSubmissionModal();
                }
            });

            // Cerrar modales al hacer clic fuera
            document.getElementById('detailsModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeDetailsModal();
                }
            });

            document.getElementById('submissionModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    // No cerrar si se hace clic dentro del modal
                }
            });

            document.getElementById('viewSubmissionModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    closeViewSubmissionModal();
                }
            });
        }
        
        // === UTILIDADES ===
        function formatDate(dateString) {
            const date = new Date(dateString);
            const options = { 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            return date.toLocaleDateString('es-ES', options);
        }

        function deliverLate(activityId) {
            if (!confirm('Confirmar entrega tardía (se registrará como "En calificación").')) return;
            const fd = new FormData();
            fd.append('activity_id', activityId);
            fd.append('student_id', studentId);
            fd.append('comments', 'Entrega marcada como tardía desde interfaz');
            fd.append('force_no_file', '1');

            fetch('submit_activity.php', { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        alert('Entrega registrada correctamente.');
                        const idx = activities.findIndex(a => a.ActividadID === activityId);
                        if (idx !== -1) {
                            activities[idx].status = res.status || 'grading';
                            activities[idx].submittedDate = new Date().toISOString().split('T')[0];
                            activities[idx].EntregaTardia = res.is_late ? 1 : 0;
                        }
                        loadCourseHeader();
                        loadActivities();
                    } else {
                        alert('Error: ' + (res.message || 'No se pudo registrar entrega'));
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert('Error al comunicarse con el servidor.');
                });
        }
    </script>
</body>
</html>
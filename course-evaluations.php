<?php
session_start();

// Suponiendo que el ID del estudiante se almacena en la sesión
// Si no tienes un sistema de login/sesión configurado, usa un ID de prueba (ej: 1)
$studentId = $_SESSION['student_id'] ?? 1; // Usar ID 1 para demo si no hay sesión

// ============================================
// LÓGICA DE CONEXIÓN Y CONSULTA
// ============================================

// Incluir el archivo de conexión (usando los parámetros de conexion.php)
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
    // Si no hay ID de curso, redirigir o mostrar error
    header("Location: my-courses.html");
    exit();
}

// 1. OBTENER DATOS DEL CURSO
$course_stmt = $conn->prepare("
    SELECT 
        c.NombreCurso, 
        c.Icono, 
        CONCAT(p.ProfNombre, ' ', p.ProfApellido) AS Instructor,
        c.TotalLecciones
    FROM cursos c
    INNER JOIN profesorregistro p ON c.ProfID = p.ProfID
    WHERE c.CursoID = ?
");
$course_stmt->bind_param("i", $courseId);
$course_stmt->execute();
$course_result = $course_stmt->get_result();
$currentCourseData = $course_result->fetch_assoc();
$course_stmt->close();

if (!$currentCourseData) {
    // Manejo de error si el curso no existe
    echo "<script>alert('Curso no encontrado.'); window.location.href='my-courses.html';</script>";
    exit();
}

// 2. OBTENER EVALUACIONES PARA ESTE CURSO Y SU ESTADO PARA EL ESTUDIANTE
$evaluations_stmt = $conn->prepare("
    SELECT 
        e.EvaluacionID, 
        e.Titulo, 
        e.Descripcion, 
        e.Duracion, 
        e.Puntos, 
        e.FechaVencimiento, 
        e.TotalPreguntas AS QuestionsCount,
        r.RespuestaID, 
        r.Calificacion, 
        r.Estado AS SubmissionStatus, 
        r.FechaEntrega
    FROM evaluaciones e
    LEFT JOIN respuestas_evaluacion r ON e.EvaluacionID = r.EvaluacionID AND r.EstID = ?
    WHERE e.CursoID = ? AND e.Activo = TRUE
    ORDER BY e.FechaVencimiento ASC
");
$evaluations_stmt->bind_param("ii", $studentId, $courseId);
$evaluations_stmt->execute();
$evaluations_result = $evaluations_stmt->get_result();
$evaluations = [];

while ($row = $evaluations_result->fetch_assoc()) {
    // Determinar el estado para la interfaz
    $status = 'pending'; // Por defecto
    if ($row['SubmissionStatus'] === 'en_progreso') {
        $status = 'in-progress';
    } elseif ($row['SubmissionStatus'] === 'completada' || $row['SubmissionStatus'] === 'calificada') {
        $status = 'completed';
    }
    
    // Si está pendiente y vencida, actualizar a 'overdue' (aunque 'pending' es suficiente para la UI)
    $dueDate = new DateTime($row['FechaVencimiento']);
    $today = new DateTime();

    if ($status === 'pending' && $dueDate < $today) {
        // En la BD no existe un estado 'vencida' directo, se infiere desde la fecha
        // Pero para la UI, mantenemos 'pending' para que puedan iniciarla
        // o si es un requerimiento, podríamos marcarla visualmente como vencida, 
        // pero por simplicidad de la lógica de negocio la dejamos como 'pending'
    }

    $evaluations[] = [
        'id' => $row['EvaluacionID'],
        'title' => $row['Titulo'],
        'description' => $row['Descripcion'],
        'duration' => $row['Duracion'],
        'questions' => $row['QuestionsCount'],
        'points' => $row['Puntos'],
        'dueDate' => $row['FechaVencimiento'],
        'status' => $status,
        'score' => $row['Calificacion'],
        'completedDate' => $row['FechaEntrega']
        // Aquí faltarían datos como preguntas, requiresFile, etc., que se obtienen en el JS
    ];
}
$evaluations_stmt->close();

// 3. OBTENER ESTADÍSTICAS DEL CURSO PARA EL HEADER
$stats_stmt = $conn->prepare("
    SELECT 
        COUNT(e.EvaluacionID) AS TotalEvaluaciones,
        SUM(CASE WHEN r.Estado = 'completada' OR r.Estado = 'calificada' THEN 1 ELSE 0 END) AS CompletedEvaluations,
        SUM(e.Puntos) AS TotalPoints
    FROM evaluaciones e
    LEFT JOIN respuestas_evaluacion r ON e.EvaluacionID = r.EvaluacionID AND r.EstID = ?
    WHERE e.CursoID = ? AND e.Activo = TRUE
");
$stats_stmt->bind_param("ii", $studentId, $courseId);
$stats_stmt->execute();
$stats_result = $stats_stmt->get_result();
$stats = $stats_result->fetch_assoc();
$stats_stmt->close();


// 4. OBTENER LAS PREGUNTAS COMPLETAS (Para JS)
$questions_data = [];
$questions_stmt = $conn->prepare("
    SELECT 
        pe.EvaluacionID, 
        pe.PreguntaID, 
        pe.TipoPregunta, 
        pe.TextoPregunta, 
        pe.Opciones, 
        e.RequiereArchivo
    FROM preguntas_evaluacion pe
    INNER JOIN evaluaciones e ON pe.EvaluacionID = e.EvaluacionID
    WHERE e.CursoID = ? AND e.Activo = TRUE
    ORDER BY pe.EvaluacionID, pe.Orden
");
$questions_stmt->bind_param("i", $courseId);
$questions_stmt->execute();
$questions_result = $questions_stmt->get_result();

while ($q_row = $questions_result->fetch_assoc()) {
    $evalId = $q_row['EvaluacionID'];
    if (!isset($questions_data[$evalId])) {
        $questions_data[$evalId] = [
            'requiresFile' => $q_row['RequiereArchivo'],
            'questionsList' => []
        ];
    }

    $questions_data[$evalId]['questionsList'][] = [
        'id' => $q_row['PreguntaID'],
        'type' => $q_row['TipoPregunta'],
        'question' => $q_row['TextoPregunta'],
        'options' => json_decode($q_row['Opciones'], true), // Convertir JSON a array PHP
    ];
}
$questions_stmt->close();
$conn->close();

// Codificar datos PHP para ser usados en JavaScript
$currentCourseJSON = json_encode($currentCourseData);
$evaluationsJSON = json_encode($evaluations);
$statsJSON = json_encode($stats);
$questionsDataJSON = json_encode($questions_data);


?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Evaluaciones del Curso - Hoot & Learn</title>
    <style>
        /* ... (Todo el CSS original de course-evaluations.html sin cambios) ... */
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 25%, #f093fb 50%, #f5576c 75%, #4facfe 100%);
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            box-shadow: 0 8px 32px rgba(102,126,234,0.1);
        }

        .course-title {
            font-size: 2rem;
            font-weight: 800;
            margin-bottom: 0.5rem;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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

        /* === VISTAS === */
        .view {
            display: none;
        }

        .view.active {
            display: block;
        }

        /* === EVALUATIONS LIST === */
        .evaluations-section {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 8px 32px rgba(102,126,234,0.1);
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

        .evaluations-grid {
            display: grid;
            gap: 1.5rem;
        }

        .evaluation-card {
            background: rgba(255,255,255,0.6);
            border-radius: 15px;
            padding: 1.5rem;
            border: 1px solid rgba(255,255,255,0.8);
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .evaluation-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #667eea, #764ba2);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }

        .evaluation-card:hover::before {
            transform: scaleX(1);
        }

        .evaluation-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 30px rgba(102,126,234,0.15);
            background: rgba(255,255,255,0.8);
        }

        .evaluation-card.completed {
            border-color: rgba(34,197,94,0.3);
            background: rgba(34,197,94,0.05);
        }

        .evaluation-card.completed::before {
            background: linear-gradient(90deg, #22c55e, #16a34a);
            transform: scaleX(1);
        }

        .evaluation-card.in-progress {
            border-color: rgba(245,158,11,0.3);
            background: rgba(245,158,11,0.05);
        }

        .evaluation-card.in-progress::before {
            background: linear-gradient(90deg, #f59e0b, #d97706);
            transform: scaleX(1);
        }

        .evaluation-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 1rem;
        }

        .evaluation-info {
            flex: 1;
        }

        .evaluation-title {
            font-size: 1.2rem;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .evaluation-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-size: 0.9rem;
            font-weight: 600;
        }

        .status-pending {
            color: #667eea;
        }

        .status-completed {
            color: #22c55e;
        }

        .status-in-progress {
            color: #f59e0b;
        }

        .evaluation-description {
            color: #4a5568;
            margin-bottom: 1rem;
            line-height: 1.5;
        }

        .evaluation-details {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .evaluation-meta {
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

        .evaluation-actions {
            display: flex;
            gap: 0.5rem;
        }

        .action-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
            box-shadow: 0 5px 15px rgba(102,126,234,0.3);
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

        .action-btn:disabled {
            background: rgba(160,174,192,0.5);
            cursor: not-allowed;
            transform: none;
        }

        /* === EVALUATION FORM === */
        .evaluation-form-section {
            background: rgba(255,255,255,0.8);
            backdrop-filter: blur(20px);
            border-radius: 20px;
            padding: 2rem;
            border: 1px solid rgba(255,255,255,0.9);
            box-shadow: 0 8px 32px rgba(102,126,234,0.1);
        }

        .evaluation-form-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(160,174,192,0.3);
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 700;
            color: #2d3748;
        }

        .timer-container {
            background: rgba(245,158,11,0.1);
            border: 1px solid rgba(245,158,11,0.3);
            border-radius: 10px;
            padding: 1rem;
            text-align: center;
            min-width: 150px;
        }

        .timer-label {
            font-size: 0.8rem;
            font-weight: 600;
            color: #f59e0b;
            text-transform: uppercase;
            margin-bottom: 0.5rem;
        }

        .timer-display {
            font-size: 1.5rem;
            font-weight: 800;
            color: #f59e0b;
            font-family: 'Courier New', monospace;
        }

        .timer-display.warning {
            color: #ef4444;
            animation: pulse 1s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        .question-container {
            background: rgba(255,255,255,0.6);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255,255,255,0.8);
        }

        .question-header {
            display: flex;
            align-items: flex-start;
            gap: 1rem;
            margin-bottom: 1rem;
        }

        .question-number {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 0.9rem;
            flex-shrink: 0;
        }

        .question-content {
            flex: 1;
        }

        .question-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 0.5rem;
        }

        .question-type {
            font-size: 0.8rem;
            background: rgba(102,126,234,0.1);
            color: #667eea;
            padding: 0.2rem 0.5rem;
            border-radius: 6px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .question-text {
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 1rem;
        }

        .answer-options {
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .option-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.8rem;
            background: rgba(255,255,255,0.8);
            border: 1px solid rgba(160,174,192,0.2);
            border-radius: 8px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .option-item:hover {
            background: rgba(102,126,234,0.05);
            border-color: rgba(102,126,234,0.3);
        }

        .option-item.selected {
            background: rgba(102,126,234,0.1);
            border-color: #667eea;
        }

        .option-radio {
            width: 1rem;
            height: 1rem;
            border: 2px solid #667eea;
            border-radius: 50%;
            position: relative;
            flex-shrink: 0;
        }

        .option-radio.selected::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 0.5rem;
            height: 0.5rem;
            background: #667eea;
            border-radius: 50%;
        }

        .answer-textarea {
            width: 100%;
            min-height: 120px;
            padding: 1rem;
            border: 1px solid rgba(160,174,192,0.3);
            border-radius: 8px;
            background: rgba(255,255,255,0.8);
            color: #2d3748;
            font-family: inherit;
            resize: vertical;
            line-height: 1.5;
        }

        .answer-textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }

        .file-upload-section {
            background: rgba(255,255,255,0.6);
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255,255,255,0.8);
        }

        .file-upload-title {
            font-size: 1.1rem;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .file-upload-area {
            border: 2px dashed rgba(102,126,234,0.3);
            border-radius: 10px;
            padding: 2rem;
            text-align: center;
            background: rgba(102,126,234,0.05);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .file-upload-area:hover {
            border-color: rgba(102,126,234,0.5);
            background: rgba(102,126,234,0.1);
        }

        .file-upload-area.dragover {
            border-color: #667eea;
            background: rgba(102,126,234,0.15);
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

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid rgba(160,174,192,0.3);
        }

        .submit-btn {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(34,197,94,0.3);
        }

        .submit-btn:disabled {
            background: rgba(160,174,192,0.5);
            cursor: not-allowed;
            transform: none;
            box-shadow: none;
        }

        .cancel-btn {
            background: rgba(255,255,255,0.8);
            color: #4a5568;
            border: 1px solid rgba(160,174,192,0.3);
            padding: 1rem 2rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            font-size: 1.1rem;
            transition: all 0.3s ease;
        }

        .cancel-btn:hover {
            background: rgba(255,255,255,1);
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(45,55,72,0.1);
        }

        /* === SUBMISSION MODAL === */
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
            box-shadow: 0 20px 60px rgba(102,126,234,0.3);
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

        .submission-summary {
            background: rgba(102,126,234,0.05);
            border: 1px solid rgba(102,126,234,0.2);
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 0.5rem;
            font-size: 0.9rem;
        }

        .summary-label {
            color: #4a5568;
            font-weight: 600;
        }

        .summary-value {
            color: #2d3748;
            font-weight: 700;
        }

        .modal-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
        }

        .confirm-btn {
            background: linear-gradient(135deg, #22c55e 0%, #16a34a 100%);
            color: white;
            border: none;
            padding: 1rem 2rem;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }

        .confirm-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(34,197,94,0.3);
        }

        /* === SUCCESS MESSAGE === */
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

        /* === RESPONSIVE === */
        @media (max-width: 768px) {
            .evaluation-form-header {
                flex-direction: column;
                gap: 1rem;
                align-items: flex-start;
            }
            
            .timer-container {
                align-self: stretch;
            }
            
            .form-actions {
                flex-direction: column;
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
                <span id="courseIcon"><?php echo htmlspecialchars($currentCourseData['Icono'] ?? '📊'); ?></span>
                <span id="courseName"><?php echo htmlspecialchars($currentCourseData['NombreCurso'] ?? 'Curso no encontrado'); ?></span>
            </h1>
            <div class="course-stats" id="courseStats">
                </div>
        </section>

        <div class="view active" id="evaluationsListView">
            <section class="evaluations-section">
                <h2 class="section-title">
                    <span>📋</span>
                    Evaluaciones del Curso
                </h2>
                
                <div class="evaluations-grid" id="evaluationsGrid">
                    </div>
            </section>
        </div>

        <div class="view" id="evaluationFormView">
            <section class="evaluation-form-section">
                <div class="evaluation-form-header">
                    <div>
                        <h2 class="form-title" id="formTitle">Evaluación</h2>
                        <p id="formDescription" style="color: #4a5568; margin-top: 0.5rem;"></p>
                    </div>
                    <div class="timer-container" id="timerContainer">
                        <div class="timer-label">Tiempo Restante</div>
                        <div class="timer-display" id="timerDisplay">60:00</div>
                    </div>
                </div>

                <form id="evaluationForm">
                    <input type="hidden" id="formEvaluationId">
                    <div id="questionsContainer">
                        </div>

                    <div class="file-upload-section" id="fileUploadSection" style="display: none;">
                        <div class="file-upload-title">
                            <span>📎</span>
                            Archivo Adicional (Opcional)
                        </div>
                        <div class="file-upload-area" onclick="document.getElementById('fileInput').click()">
                            <div class="upload-icon">📄</div>
                            <div class="upload-text">Haz clic aquí o arrastra tu archivo PDF</div>
                            <div style="font-size: 0.9rem; color: #718096;">Formato: PDF (Max: 10MB)</div>
                        </div>
                        <input type="file" id="fileInput" class="file-input" accept=".pdf" onchange="handleFileSelect(event)">
                        <div class="selected-file" id="selectedFile">
                            <div class="file-info">
                                <span>✅</span>
                                <span id="fileName">archivo.pdf</span>
                                <span id="fileSize">(2.5 MB)</span>
                            </div>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="button" class="cancel-btn" onclick="cancelEvaluation()">
                            Cancelar
                        </button>
                        <button type="submit" class="submit-btn" id="submitBtn">
                            <span>📤</span>
                            Entregar Evaluación
                        </button>
                    </div>
                </form>
            </section>
        </div>
    </main>

    <div class="submission-modal" id="submissionModal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 class="modal-title">Confirmar Entrega</h3>
                <button class="close-btn" onclick="closeSubmissionModal()">×</button>
            </div>
            
            <div class="submission-summary" id="submissionSummary">
                </div>

            <p style="color: #4a5568; margin-bottom: 2rem;">
                ⚠️ <strong>Importante:</strong> Una vez entregada la evaluación, no podrás modificar tus respuestas.
            </p>

            <div class="modal-actions">
                <button class="cancel-btn" onclick="closeSubmissionModal()">
                    Revisar Respuestas
                </button>
                <button class="confirm-btn" onclick="confirmSubmission()">
                    Confirmar Entrega
                </button>
            </div>

            <div class="success-message" id="successMessage">
                <div class="success-icon">🎉</div>
                <div class="success-title">¡Evaluación Entregada!</div>
                <div class="success-text">Tu evaluación ha sido enviada correctamente. Recibirás los resultados pronto.</div>
                <button class="confirm-btn" onclick="returnToList()">
                    Volver a Evaluaciones
                </button>
            </div>
        </div>
    </div>

    <script>
        // === DATOS PROVENIENTES DE PHP ===
        const currentCourseData = <?php echo $currentCourseJSON; ?>;
        const evaluations = <?php echo $evaluationsJSON; ?>;
        const courseStats = <?php echo $statsJSON; ?>;
        const questionsData = <?php echo $questionsDataJSON; ?>;
        const studentId = <?php echo $studentId; ?>;
        const courseId = <?php echo $courseId; ?>;

        let currentEvaluation = null;
        let evaluationTimer = null;
        let timeRemaining = 0;
        let userAnswers = {};

        // === INICIALIZACIÓN ===
        document.addEventListener('DOMContentLoaded', function() {
            loadCourseHeader();
            loadEvaluations();
            setupModalEvents();
        });

        // === CARGAR HEADER DEL CURSO ===
        function loadCourseHeader() {
            document.getElementById('courseStats').innerHTML = `
                <div class="stat-item">
                    <span>👨‍🏫</span>
                    <span>${currentCourseData.Instructor}</span>
                </div>
                <div class="stat-item">
                    <span>📊</span>
                    <span>${courseStats.CompletedEvaluations || 0}/${courseStats.TotalEvaluaciones || 0} completadas</span>
                </div>
                <div class="stat-item">
                    <span>⏳</span>
                    <span>${(courseStats.TotalEvaluaciones || 0) - (courseStats.CompletedEvaluations || 0)} pendientes</span>
                </div>
                <div class="stat-item">
                    <span>🎯</span>
                    <span>${courseStats.TotalPoints || 0} puntos totales</span>
                </div>
            `;
        }

        // === CARGAR EVALUACIONES ===
        function loadEvaluations() {
            const evaluationsGrid = document.getElementById('evaluationsGrid');
            
            if (evaluations.length === 0) {
                evaluationsGrid.innerHTML = `
                    <div style="text-align: center; padding: 3rem; color: #4a5568;">
                        <div style="font-size: 4rem; margin-bottom: 1rem;">📋</div>
                        <h3>No hay evaluaciones disponibles</h3>
                        <p>Las evaluaciones aparecerán aquí cuando estén disponibles.</p>
                    </div>
                `;
                return;
            }

            evaluationsGrid.innerHTML = evaluations.map(evaluation => {
                const statusIcons = {
                    pending: '⏳',
                    'in-progress': '📝',
                    completed: '✅'
                };

                const statusTexts = {
                    pending: 'Pendiente',
                    'in-progress': 'En Progreso',
                    completed: 'Completada'
                };

                // Determinar si está vencida
                const dueDate = new Date(evaluation.dueDate);
                const today = new Date();
                const isOverdue = evaluation.status === 'pending' && dueDate < today;
                
                let cardClass = evaluation.status;
                if (isOverdue) cardClass = 'overdue'; // Clase visual para vencida

                return `
                    <div class="evaluation-card ${cardClass}">
                        <div class="evaluation-header">
                            <div class="evaluation-info">
                                <div class="evaluation-title">
                                    📋 ${evaluation.title}
                                </div>
                                <div class="evaluation-status status-${evaluation.status}">
                                    ${statusIcons[evaluation.status]}
                                    ${statusTexts[evaluation.status]}
                                    ${evaluation.score ? ` (${Math.round(evaluation.score)}/${evaluation.points})` : ''}
                                    ${isOverdue && evaluation.status === 'pending' ? ' (Vencida)' : ''}
                                </div>
                            </div>
                        </div>
                        
                        <div class="evaluation-description">
                            ${evaluation.description}
                        </div>
                        
                        <div class="evaluation-details">
                            <div class="evaluation-meta">
                                <div class="meta-item">
                                    <span>⏱️</span>
                                    <span>${evaluation.duration} min</span>
                                </div>
                                <div class="meta-item">
                                    <span>❓</span>
                                    <span>${evaluation.questions} preguntas</span>
                                </div>
                                <div class="meta-item">
                                    <span>🎯</span>
                                    <span>${evaluation.points} puntos</span>
                                </div>
                                <div class="meta-item">
                                    <span>📅</span>
                                    <span>Vence: ${formatDate(evaluation.dueDate)}</span>
                                </div>
                                ${evaluation.completedDate ? `
                                <div class="meta-item">
                                    <span>✅</span>
                                    <span>Completado: ${formatDate(evaluation.completedDate)}</span>
                                </div>
                                ` : ''}
                            </div>
                            
                            <div class="evaluation-actions">
                                ${evaluation.status === 'completed' ? `
                                    <button class="action-btn completed" onclick="viewResults(${evaluation.id})">
                                        Ver Resultados
                                    </button>
                                ` : evaluation.status === 'in-progress' ? `
                                    <button class="action-btn" onclick="continueEvaluation(${evaluation.id})">
                                        Continuar
                                    </button>
                                ` : `
                                    <button class="action-btn" onclick="startEvaluation(${evaluation.id})">
                                        Comenzar Evaluación
                                    </button>
                                `}
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // === ACCIONES DE EVALUACIONES ===
        function startEvaluation(evaluacionId) {
            currentEvaluation = evaluations.find(e => e.id === evaluacionId);
            if (!currentEvaluation) return;

            // **IMPORTANTE**: Aquí deberías llamar a un script PHP/API para registrar el inicio del intento
            // y, si es necesario, obtener las respuestas guardadas si es una continuación.

            // Cambiar vista
            document.getElementById('evaluationsListView').classList.remove('active');
            document.getElementById('evaluationFormView').classList.add('active');

            // Configurar formulario
            setupEvaluationForm(evaluacionId);
            
            // Iniciar timer
            startTimer(currentEvaluation.duration);
            
            // Marcar como en progreso (solo en el cliente, el backend lo haría al registrar el inicio)
            currentEvaluation.status = 'in-progress';
        }

        function continueEvaluation(evaluacionId) {
            // En un sistema real, aquí harías una petición para obtener el tiempo restante 
            // y las respuestas almacenadas en respuestas_evaluacion.Respuestas.
            
            // Por simplicidad de la demo, simplemente iniciamos la evaluación como si fuera nueva.
            startEvaluation(evaluacionId);
        }

        function viewResults(evaluationId) {
            const evaluation = evaluations.find(e => e.id === evaluationId);
            if (!evaluation) return;

            // En un sistema real, cargarías los resultados detallados desde la BD
            alert(`📊 Resultados de: ${evaluation.title}\n\nCalificación: ${Math.round(evaluation.score)}/${evaluation.points} puntos\nEstado: Completada el ${formatDate(evaluation.completedDate)}\n\n(En una implementación real, esto mostraría un reporte detallado de la evaluación.)`);
        }

        // === CONFIGURAR FORMULARIO ===
        function setupEvaluationForm(evaluationId) {
            const fullEvaluationData = questionsData[evaluationId];

            if (!fullEvaluationData) {
                alert('Error: No se encontraron preguntas para esta evaluación.');
                returnToList();
                return;
            }

            // Establecer ID de evaluación en el formulario
            document.getElementById('formEvaluationId').value = evaluationId;
            document.getElementById('formTitle').textContent = currentEvaluation.title;
            document.getElementById('formDescription').textContent = currentEvaluation.description;

            // Configurar timer
            timeRemaining = currentEvaluation.duration * 60; // convertir a segundos
            updateTimerDisplay();

            // Mostrar sección de archivo si es necesaria
            const fileUploadSection = document.getElementById('fileUploadSection');
            if (fullEvaluationData.requiresFile) {
                fileUploadSection.style.display = 'block';
            } else {
                fileUploadSection.style.display = 'none';
            }

            // Generar preguntas
            generateQuestions(fullEvaluationData.questionsList);
        }

        // === GENERAR PREGUNTAS ===
        function generateQuestions(questions) {
            const container = document.getElementById('questionsContainer');
            
            container.innerHTML = questions.map((question, index) => {
                const questionNumber = index + 1;
                
                if (question.type === 'multiple-choice') {
                    // El campo 'options' viene como array de PHP, no necesitamos parsear aquí
                    const options = Array.isArray(question.options) ? question.options : []; 
                    
                    return `
                        <div class="question-container">
                            <div class="question-header">
                                <div class="question-number">${questionNumber}</div>
                                <div class="question-content">
                                    <div class="question-title">
                                        Pregunta ${questionNumber}
                                        <span class="question-type">Opción Múltiple</span>
                                    </div>
                                </div>
                            </div>
                            <div class="question-text">${question.question}</div>
                            <div class="answer-options" data-question-id="${question.id}">
                                ${options.map((option, optionIndex) => `
                                    <div class="option-item" onclick="selectOption(${question.id}, ${optionIndex}, this)">
                                        <div class="option-radio" id="radio-${question.id}-${optionIndex}"></div>
                                        <span>${option}</span>
                                    </div>
                                `).join('')}
                            </div>
                        </div>
                    `;
                } else if (question.type === 'open-ended') {
                    return `
                        <div class="question-container">
                            <div class="question-header">
                                <div class="question-number">${questionNumber}</div>
                                <div class="question-content">
                                    <div class="question-title">
                                        Pregunta ${questionNumber}
                                        <span class="question-type">Respuesta Abierta</span>
                                    </div>
                                </div>
                            </div>
                            <div class="question-text">${question.question}</div>
                            <textarea 
                                class="answer-textarea" 
                                id="answer-${question.id}"
                                placeholder="Escribe tu respuesta aquí..."
                                oninput="saveOpenAnswer(${question.id}, this.value)"
                            ></textarea>
                        </div>
                    `;
                }
            }).join('');
        }

        // === MANEJAR RESPUESTAS ===
        function selectOption(questionId, optionIndex, clickedElement) {
            const optionsContainer = clickedElement.closest('.answer-options');
            
            // Limpiar selecciones visuales anteriores en este grupo
            optionsContainer.querySelectorAll('.option-item').forEach(item => {
                item.classList.remove('selected');
                item.querySelector('.option-radio').classList.remove('selected');
            });

            // Aplicar selección visual a la nueva opción
            clickedElement.classList.add('selected');
            clickedElement.querySelector('.option-radio').classList.add('selected');

            // Guardar respuesta
            userAnswers[questionId] = optionIndex;
        }

        function saveOpenAnswer(questionId, answer) {
            userAnswers[questionId] = answer;
        }

        // === TIMER ===
        function startTimer(durationMinutes) {
            if (evaluationTimer) clearInterval(evaluationTimer);
            timeRemaining = durationMinutes * 60; // Iniciar tiempo
            document.getElementById('timerDisplay').classList.remove('warning');
            updateTimerDisplay();
            
            evaluationTimer = setInterval(() => {
                timeRemaining--;
                updateTimerDisplay();

                if (timeRemaining <= 300 && timeRemaining > 0) { // 5 minutos restantes
                    document.getElementById('timerDisplay').classList.add('warning');
                }

                if (timeRemaining <= 0) {
                    clearInterval(evaluationTimer);
                    autoSubmitEvaluation();
                }
            }, 1000);
        }

        function updateTimerDisplay() {
            const minutes = Math.floor(timeRemaining / 60);
            const seconds = timeRemaining % 60;
            document.getElementById('timerDisplay').textContent = 
                `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
        }

        function autoSubmitEvaluation() {
            // **IMPORTANTE**: En un sistema real, aquí llamarías a la función de envío de respuestas 
            // de forma silenciosa.
            alert('⏰ El tiempo ha terminado. La evaluación se entregará automáticamente.');
            
            // Forzamos el envío para la simulación
            confirmSubmission(true); 
        }

        // === MANEJO DE ARCHIVOS ===
        function handleFileSelect(event) {
            const file = event.target.files[0];
            if (!file) {
                 document.getElementById('selectedFile').classList.remove('show');
                 return;
            }
            
            if (file.size > 10 * 1024 * 1024) {
                alert('El archivo es demasiado grande. Máximo 10MB permitido.');
                document.getElementById('fileInput').value = '';
                document.getElementById('selectedFile').classList.remove('show');
                return;
            }
            
            if (file.type !== 'application/pdf') {
                alert('Solo se permiten archivos PDF.');
                document.getElementById('fileInput').value = '';
                document.getElementById('selectedFile').classList.remove('show');
                return;
            }
            
            document.getElementById('fileName').textContent = file.name;
            document.getElementById('fileSize').textContent = `(${(file.size / 1024 / 1024).toFixed(1)} MB)`;
            document.getElementById('selectedFile').classList.add('show');
        }

        // === ENVÍO DE EVALUACIÓN (Simulación de pre-envío) ===
        function submitEvaluation() {
            // Detener timer mientras se revisa
            if (evaluationTimer) clearInterval(evaluationTimer);

            const evaluationId = document.getElementById('formEvaluationId').value;
            const fullEvaluationData = questionsData[evaluationId];
            const totalQuestions = fullEvaluationData.questionsList.length;
            const answeredQuestions = Object.keys(userAnswers).length;

            if (answeredQuestions < totalQuestions) {
                if (!confirm(`Has respondido ${answeredQuestions} de ${totalQuestions} preguntas. ¿Deseas entregar la evaluación de todas formas?`)) {
                    // Si el usuario cancela, reanudar el timer
                    startTimer(Math.ceil(timeRemaining / 60)); 
                    return;
                }
            }

            // Mostrar modal de confirmación
            showSubmissionModal();
        }

        function showSubmissionModal() {
            const modal = document.getElementById('submissionModal');
            const summary = document.getElementById('submissionSummary');

            const evaluationId = document.getElementById('formEvaluationId').value;
            const fullEvaluationData = questionsData[evaluationId];
            const totalQuestions = fullEvaluationData.questionsList.length;
            const answeredQuestions = Object.keys(userAnswers).length;
            const timeUsed = (currentEvaluation.duration * 60) - timeRemaining;
            const timeUsedMinutes = Math.floor(timeUsed / 60);
            const timeUsedSeconds = timeUsed % 60;

            summary.innerHTML = `
                <div class="summary-item">
                    <span class="summary-label">Evaluación:</span>
                    <span class="summary-value">${currentEvaluation.title}</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Preguntas respondidas:</span>
                    <span class="summary-value">${answeredQuestions}/${totalQuestions}</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Tiempo utilizado:</span>
                    <span class="summary-value">${timeUsedMinutes}:${timeUsedSeconds.toString().padStart(2, '0')}</span>
                </div>
                <div class="summary-item">
                    <span class="summary-label">Puntos posibles:</span>
                    <span class="summary-value">${currentEvaluation.points} pts</span>
                </div>
                ${document.getElementById('selectedFile').classList.contains('show') ? `
                <div class="summary-item">
                    <span class="summary-label">Archivo adjunto:</span>
                    <span class="summary-value">✅ ${document.getElementById('fileName').textContent}</span>
                </div>
                ` : ''}
            `;

            modal.classList.add('active');
            
            // Ocultar mensaje de éxito en caso de reintento
            document.getElementById('successMessage').classList.remove('show');
            document.querySelector('.submission-summary').style.display = 'block';
            document.querySelector('.modal-actions').style.display = 'flex';
            document.querySelector('.submission-modal p').style.display = 'block';
        }

        // === CONFIRMAR ENVÍO Y COMUNICARSE CON EL BACKEND ===
async function confirmSubmission(isAutoSubmission = false) {
    // Detener timer
    if (evaluationTimer) clearInterval(evaluationTimer);

    const evaluationId = document.getElementById('formEvaluationId').value;

    // Estructura de datos que se enviará en el cuerpo JSON
    const payload = {
        evaluation_id: evaluationId,
        student_id: studentId,
        answers: userAnswers, // Objeto de respuestas
        time_remaining: timeRemaining,
        // Eliminamos el manejo de archivos de este payload JSON
    };

        
    try {
    // Muestra mensaje de envío
    document.querySelector('.modal-actions').innerHTML = '<span>⏳ Enviando...</span>';

    // 1. Enviar datos al script PHP (usando la estructura JSON ya configurada)
    const response = await fetch('submit_evaluation.php', {
        method: 'POST',
        headers: { 
            'Content-Type': 'application/json' 
        },
        body: JSON.stringify(payload) // 'payload' ya contiene los datos de la evaluación
    });

    // ⚠️ CAMBIO CLAVE: Leer la respuesta como texto para capturar errores de PHP
    const responseText = await response.text();
    
    // Si el código de estado no es de éxito (ej. 500, 400), muestra el error completo
    if (!response.ok) {
        // Log del error de PHP o texto inesperado
        console.error("--- ERROR RAW RESPONSE (PHP OUTPUT) ---");
        console.error(responseText);
        console.error("---------------------------------------");
        
        // Intenta parsear el error para un mejor mensaje, si es JSON
        try {
            const result = JSON.parse(responseText);
            throw new Error(result.message || 'Error del servidor sin mensaje específico.');
        } catch {
            // Si falla al parsear (porque es HTML/texto puro), muestra el inicio del error
            const cleanText = responseText.substring(0, 200).replace(/<[^>]*>/g, '').trim();
            throw new Error(`El servidor devolvió un error HTTP ${response.status}. La respuesta no fue JSON. Contenido inicial: "${cleanText}..."`);
        }
    }
    
    // Si response.ok es true (código 200-299), asume que es JSON válido
    const result = JSON.parse(responseText);


    if (!result.success) {
        throw new Error(result.message || 'Error desconocido al enviar.');
    }

        // 2. Actualización de datos en el cliente (resto del código de éxito...)
        
        const localEvalIndex = evaluations.findIndex(e => e.id == evaluationId);
        if (localEvalIndex !== -1) {
            evaluations[localEvalIndex].status = result.status === 'calificada' ? 'completed' : 'grading';
            evaluations[localEvalIndex].completedDate = new Date().toISOString().split('T')[0];
            evaluations[localEvalIndex].score = result.calificacion;
        }

        // 3. Mostrar mensaje de éxito
        document.querySelector('.submission-summary').style.display = 'none';
        document.querySelector('.submission-modal p').style.display = 'none';
        document.getElementById('successMessage').classList.add('show');
        
        // Limpiar acciones
        document.querySelector('.modal-actions').style.display = 'none';

    } catch (error) {
        alert(`❌ Error al enviar la evaluación: ${error.message}`);
        if (!isAutoSubmission) {
            startTimer(Math.ceil(timeRemaining / 60));
        }
        document.querySelector('.modal-actions').innerHTML = `
            <button class="cancel-btn" onclick="closeSubmissionModal()">Revisar</button>
            <button class="confirm-btn" onclick="confirmSubmission()">Reintentar Envío</button>
        `;
    }
}
        
        // === FUNCIONES DE MODAL ===
        function closeSubmissionModal() {
            document.getElementById('submissionModal').classList.remove('active');
            
            // Si el modal se cierra después de un envío fallido o para revisar respuestas, 
            // el timer debe reanudarse (solo si no se está mostrando el mensaje de éxito)
            if (!document.getElementById('successMessage').classList.contains('show')) {
                startTimer(Math.ceil(timeRemaining / 60));
            }
        }

        function returnToList() {
            // Resetear formulario
            userAnswers = {};
            currentEvaluation = null;
            document.getElementById('fileInput').value = '';
            
            // Cambiar vista
            document.getElementById('evaluationFormView').classList.remove('active');
            document.getElementById('evaluationsListView').classList.add('active');
            
            // Cerrar modal y limpiar
            closeSubmissionModal();
            
            // Recargar lista y header para reflejar el nuevo estado (ej: 'grading' o 'completed')
            loadCourseHeader();
            loadEvaluations(); // Esto usa los datos 'evaluations' actualizados en confirmSubmission
            
            // Resetear modal a su estado inicial para la próxima vez
            document.querySelector('.submission-summary').style.display = 'block';
            document.querySelector('.modal-actions').style.display = 'flex';
            document.querySelector('.submission-modal p').style.display = 'block';
            document.getElementById('successMessage').classList.remove('show');
        }

        function cancelEvaluation() {
            if (confirm('¿Estás seguro de que deseas cancelar la evaluación? Se perderá el progreso no guardado.')) {
                if (evaluationTimer) {
                    clearInterval(evaluationTimer);
                }
                
                // **IMPORTANTE**: Si el estado era 'in-progress' en la BD, se debería
                // actualizar el campo 'FechaEntrega' a NULL y 'Estado' a 'en_progreso' 
                // para permitir la continuación. 
                
                // En esta simulación, simplemente regresamos a la lista.
                returnToList();
            }
        }

        // === EVENTOS ===
        function setupModalEvents() {
            document.getElementById('evaluationForm').addEventListener('submit', function(e) {
                e.preventDefault();
                submitEvaluation();
            });

            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') {
                    // Solo cerrar el modal si está activo
                    if (document.getElementById('submissionModal').classList.contains('active')) {
                        closeSubmissionModal();
                    }
                }
            });
            
            // Prevenir pérdida de foco si el modal de confirmación está abierto
            document.getElementById('submissionModal').addEventListener('click', function(e) {
                if (e.target === this) {
                    // No hacer nada si se hace clic fuera, deja que el usuario decida en el modal
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
    </script>
</body>
</html>
<?php
session_start();

// Suponiendo que el ID del estudiante ya está en la sesión
$studentId = $_SESSION['student_id'] ?? 1; // Usar ID 1 para la prueba si no hay sesión

// ============================================
// CONFIGURACIÓN DE BASE DE DATOS Y CONEXIÓN
// ============================================
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hootlearn";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error de conexión a la BD: " . $conn->connect_error]);
    exit();
}
$conn->set_charset("utf8mb4");

// 1. VERIFICAR MÉTODO POST Y DATOS ESENCIALES
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    // ... (manejo de error)
}

// ===============================================
// MODIFICACIÓN CRÍTICA: LECTURA DE JSON
// ===============================================
// Lee el cuerpo crudo de la solicitud
$jsonInput = file_get_contents('php://input');
// Decodifica el JSON a un objeto PHP
$data = json_decode($jsonInput, true); 

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Error de formato JSON: " . json_last_error_msg()]);
    exit();
}
// ===============================================

$evaluationId = filter_var($data['evaluation_id'] ?? null, FILTER_VALIDATE_INT);
$answersArray = $data['answers'] ?? null;
$timeRemaining = filter_var($data['time_remaining'] ?? null, FILTER_VALIDATE_INT);

// Volvemos a codificar las respuestas a JSON para la BD (tabla respuestas_evaluacion)
$answersJson = json_encode($answersArray);

// Asegúrate de que los datos existan después de la decodificación
if (!$evaluationId || !$answersArray) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Datos de evaluación incompletos después de decodificación JSON."]);
    exit();
}

// 2. OBTENER INFORMACIÓN DE LA EVALUACIÓN (Duración y si requiere archivo)
$evalInfo_stmt = $conn->prepare("SELECT Duracion, RequiereArchivo FROM evaluaciones WHERE EvaluacionID = ?");
$evalInfo_stmt->bind_param("i", $evaluationId);
$evalInfo_stmt->execute();
$evalInfo_result = $evalInfo_stmt->get_result();
$evaluationInfo = $evalInfo_result->fetch_assoc();
$evalInfo_stmt->close();

if (!$evaluationInfo) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Evaluación no encontrada."]);
    exit();
}

$totalDuration = $evaluationInfo['Duracion'] * 60; // Duración en segundos
$submissionDate = date('Y-m-d H:i:s');

// 3. DETERMINAR EL ESTADO DE LA EVALUACIÓN Y CALIFICACIÓN INICIAL
// Si la evaluación contiene preguntas abiertas, el estado es 'completada' (requiere calificación manual)
// Si solo tiene opción múltiple, el estado podría ser 'calificada' (se puede calificar automáticamente)

// Por simplicidad, asumiremos que si tiene muchas preguntas o es importante, 
// o si se subió un archivo, requerirá calificación manual.
$isManualGrading = true; // Valor de ejemplo, se puede determinar buscando preguntas abiertas en preguntas_evaluacion
$initialStatus = $isManualGrading ? 'completada' : 'calificada';
$initialScore = NULL; 

// 4. MANEJO DE ARCHIVOS (si aplica)
$fileRuta = NULL;
$fileName = NULL;
$fileSize = NULL;

if ($evaluationInfo['RequiereArchivo'] && isset($_FILES['evaluation_file']) && $_FILES['evaluation_file']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/evaluations/'; // Asegúrate de que esta carpeta exista y tenga permisos de escritura
    $fileTmpName = $_FILES['evaluation_file']['tmp_name'];
    $fileExtension = pathinfo($_FILES['evaluation_file']['name'], PATHINFO_EXTENSION);
    $fileName = uniqid('eval_') . '.' . $fileExtension;
    $fileSize = $_FILES['evaluation_file']['size'];
    $fileRuta = $uploadDir . $fileName;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (!move_uploaded_file($fileTmpName, $fileRuta)) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al mover el archivo subido."]);
        exit();
    }
}

// 5. INSERTAR O ACTUALIZAR RESPUESTA
// Usamos INSERT... ON DUPLICATE KEY UPDATE para manejar la primera entrega (o actualización de un intento "en_progreso")
$insert_stmt = $conn->prepare("
    INSERT INTO respuestas_evaluacion 
    (EvaluacionID, EstID, FechaInicio, FechaEntrega, Respuestas, Calificacion, Estado, IntentosUsados, ArchivoRuta, ArchivoNombre, ArchivoTamanio) 
    VALUES (?, ?, NOW(), ?, ?, ?, ?, 1, ?, ?, ?)
    ON DUPLICATE KEY UPDATE
    FechaEntrega = VALUES(FechaEntrega),
    Respuestas = VALUES(Respuestas),
    Calificacion = VALUES(Calificacion),
    Estado = VALUES(Estado),
    IntentosUsados = IntentosUsados + 1,
    ArchivoRuta = VALUES(ArchivoRuta),
    ArchivoNombre = VALUES(ArchivoNombre),
    ArchivoTamanio = VALUES(ArchivoTamanio)
");

// Nota: FechaInicio se usa como NOW() en el INSERT, y se asume que esta es la primera vez que se completa.
// En un sistema avanzado, el JS enviaría 'FechaInicio' si existía un intento previo.

$insert_stmt->bind_param(
    "iisssdsss", 
    $evaluationId, 
    $studentId, 
    $submissionDate, 
    $answersJson, 
    $initialScore, 
    $initialStatus, 
    $fileRuta, 
    $fileName,
    $fileSize
);

if ($insert_stmt->execute()) {
    
    // 6. RESPUESTA EXITOSA
    $response = [
        "success" => true,
        "message" => "Evaluación entregada correctamente.",
        "status" => $initialStatus,
        "calificacion" => $initialScore,
        "requires_manual_grading" => $isManualGrading
    ];

    if ($fileRuta) {
        $response['file_path'] = $fileRuta;
    }
    
    http_response_code(200);
    echo json_encode($response);
    
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error al guardar la respuesta en la BD: " . $insert_stmt->error]);
}

$insert_stmt->close();
$conn->close();
?>
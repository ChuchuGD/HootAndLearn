<?php
session_start();

// Configuración de la base de datos
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

// 1. Obtener datos de la solicitud
$activityId = filter_input(INPUT_POST, 'activity_id', FILTER_VALIDATE_INT);
$studentId = filter_input(INPUT_POST, 'student_id', FILTER_VALIDATE_INT);
$comments = $_POST['comments'] ?? '';

if (!$activityId || !$studentId) {
    http_response_code(400);
    echo json_encode(["success" => false, "message" => "Datos incompletos para la entrega."]);
    exit();
}

// 2. Obtener fecha de vencimiento de la actividad
$date_stmt = $conn->prepare("SELECT FechaVencimiento FROM actividades WHERE ActividadID = ?");
$date_stmt->bind_param("i", $activityId);
$date_stmt->execute();
$date_result = $date_stmt->get_result();
$activity_data = $date_result->fetch_assoc();
$date_stmt->close();

if (!$activity_data) {
    http_response_code(404);
    echo json_encode(["success" => false, "message" => "Actividad no encontrada."]);
    exit();
}

$dueDate = new DateTime($activity_data['FechaVencimiento']);
$currentDate = new DateTime();
$isLate = $currentDate > $dueDate;

// 3. Manejo de Archivos (Upload)
$fileRuta = NULL;
$fileName = NULL;
$fileSize = NULL;

if (isset($_FILES['submission_file']) && $_FILES['submission_file']['error'] === UPLOAD_ERR_OK) {
    $uploadDir = 'uploads/submissions/'; 
    $fileTmpName = $_FILES['submission_file']['tmp_name'];
    $fileExtension = pathinfo($_FILES['submission_file']['name'], PATHINFO_EXTENSION);
    $fileName = uniqid('sub_') . '.' . $fileExtension;
    $fileSize = $_FILES['submission_file']['size'];
    $fileRuta = $uploadDir . $fileName;

    if (!is_dir($uploadDir)) {
        mkdir($uploadDir, 0777, true);
    }

    if (!move_uploaded_file($fileTmpName, $fileRuta)) {
        http_response_code(500);
        echo json_encode(["success" => false, "message" => "Error al mover el archivo subido al servidor."]);
        exit();
    }
}

// 4. Insertar o Actualizar la entrega
// Usamos INSERT ON DUPLICATE KEY UPDATE por si el estudiante intenta entregar de nuevo
$insert_stmt = $conn->prepare("
    INSERT INTO entregas 
    (ActividadID, EstID, FechaEntrega, ArchivoNombre, ArchivoRuta, ArchivoTamanio, Comentarios, Estado, EntregaTardia) 
    VALUES (?, ?, NOW(), ?, ?, ?, ?, 'calificando', ?)
    ON DUPLICATE KEY UPDATE
    FechaEntrega = NOW(),
    ArchivoNombre = VALUES(ArchivoNombre),
    ArchivoRuta = VALUES(ArchivoRuta),
    ArchivoTamanio = VALUES(ArchivoTamanio),
    Comentarios = VALUES(Comentarios),
    Estado = 'calificando',
    EntregaTardia = VALUES(EntregaTardia)
");

$isLateInt = $isLate ? 1 : 0; // Convertir booleano a entero
$insert_stmt->bind_param(
    "iisssssi", 
    $activityId, 
    $studentId, 
    $fileName, 
    $fileRuta, 
    $fileSize, 
    $comments,
    $isLateInt
);

if ($insert_stmt->execute()) {
    http_response_code(200);
    echo json_encode([
        "success" => true,
        "message" => "Entrega registrada y pendiente de calificación.",
        "status" => "grading",
        "is_late" => $isLate
    ]);
} else {
    http_response_code(500);
    echo json_encode(["success" => false, "message" => "Error al guardar la entrega en la BD: " . $insert_stmt->error]);
}

$insert_stmt->close();
$conn->close();
?>
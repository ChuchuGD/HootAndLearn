<?php
session_start();
ini_set('display_errors', '1');
error_reporting(E_ALL);
header('Content-Type: application/json; charset=utf-8');

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hootlearn";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

$activityId = isset($_POST['activity_id']) ? (int)$_POST['activity_id'] : 0;
$studentId  = isset($_POST['student_id'])  ? (int)$_POST['student_id']  : 0;
$comments   = trim($_POST['comments'] ?? '');

if ($activityId <= 0 || $studentId <= 0) {
    echo json_encode(['success' => false, 'message' => 'Parámetros inválidos']);
    exit;
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Error conexión DB']);
    exit;
}
$conn->set_charset('utf8mb4');

// Obtener fecha de vencimiento y si el archivo es requerido
$actStmt = $conn->prepare("SELECT FechaVencimiento, ArchivoRequerido FROM actividades WHERE ActividadID = ? LIMIT 1");
$actStmt->bind_param("i", $activityId);
$actStmt->execute();
$actRow = $actStmt->get_result()->fetch_assoc();
$actStmt->close();

if (!$actRow) {
    echo json_encode(['success' => false, 'message' => 'Actividad no encontrada']);
    $conn->close();
    exit;
}

$fechaVenc = $actRow['FechaVencimiento']; // YYYY-MM-DD or NULL
$archivoRequerido = (int)($actRow['ArchivoRequerido'] ?? 0);

// Permitir forzar entrega sin archivo (usado por deliverLate)
$forceNoFile = isset($_POST['force_no_file']) && $_POST['force_no_file'] === '1';

// comparar fechas (considerar solo fecha)
$isLate = false;
if ($fechaVenc) {
    $today = new DateTime(date('Y-m-d'));
    $due = DateTime::createFromFormat('Y-m-d', substr($fechaVenc,0,10));
    if ($due && $today > $due) $isLate = true;
}

// Manejo de archivo (opcional)
$fileName = null;
$filePath = null;
$fileSizeStr = null;

if (!empty($_FILES['submission_file']) && $_FILES['submission_file']['error'] !== UPLOAD_ERR_NO_FILE) {
    $f = $_FILES['submission_file'];
    if ($f['error'] !== UPLOAD_ERR_OK) {
        echo json_encode(['success' => false, 'message' => 'Error al subir archivo']);
        $conn->close();
        exit;
    }
    // validar tamaño <= 10MB
    if ($f['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'message' => 'Archivo excede 10MB']);
        $conn->close();
        exit;
    }

    // validar extensiones permitidas
    $allowed = ['pdf','doc','docx','zip'];
    $ext = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, $allowed)) {
        echo json_encode(['success' => false, 'message' => 'Extensión de archivo no permitida']);
        $conn->close();
        exit;
    }

    // guardar archivo
    $uploadDir = __DIR__ . '/uploads/entregas/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);

    try {
        $safeName = time() . '-' . bin2hex(random_bytes(6)) . '-' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($f['name']));
    } catch (Exception $e) {
        $safeName = time() . '-' . bin2hex(openssl_random_pseudo_bytes(6)) . '-' . preg_replace('/[^a-zA-Z0-9_\.-]/', '_', basename($f['name']));
    }
    $dest = $uploadDir . $safeName;
    if (!move_uploaded_file($f['tmp_name'], $dest)) {
        echo json_encode(['success' => false, 'message' => 'No se pudo guardar el archivo en el servidor']);
        $conn->close();
        exit;
    }

    $fileName = $f['name'];
    $filePath = 'uploads/entregas/' . $safeName; // ruta relativa al proyecto
    $fileSizeStr = round($f['size'] / 1024 / 1024, 1) . ' MB';
} else {
    // si archivo es obligatorio y no se envió, devolver error salvo que se haya forzado
    if ($archivoRequerido && !$forceNoFile) {
        echo json_encode(['success' => false, 'message' => 'Esta actividad requiere archivo para entregar.']);
        $conn->close();
        exit;
    }
    // dejar fileName/filePath en null si no hay archivo
}

// Insertar o actualizar entrega (unique: ActividadID, EstID)
$sql = "INSERT INTO entregas (ActividadID, EstID, ArchivoNombre, ArchivoRuta, ArchivoTamanio, Comentarios, Estado, EntregaTardia)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            ArchivoNombre = VALUES(ArchivoNombre),
            ArchivoRuta = VALUES(ArchivoRuta),
            ArchivoTamanio = VALUES(ArchivoTamanio),
            Comentarios = VALUES(Comentarios),
            Estado = VALUES(Estado),
            EntregaTardia = VALUES(EntregaTardia),
            FechaEntrega = CURRENT_TIMESTAMP,
            Retroalimentacion = NULL,
            Calificacion = NULL
        ";
$estado = 'calificando';
$entregaTardiaInt = $isLate ? 1 : 0;
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo json_encode(['success' => false, 'message' => 'Error preparando consulta: ' . $conn->error]);
    $conn->close();
    exit;
}
$stmt->bind_param("iisssssi",
    $activityId,
    $studentId,
    $fileName,
    $filePath,
    $fileSizeStr,
    $comments,
    $estado,
    $entregaTardiaInt
);

if ($stmt->execute()) {
    $stmt->close();
    $conn->close();
    echo json_encode([
        'success' => true,
        'file_name' => $fileName,
        'file_path' => $filePath,
        'is_late' => $isLate,
        'status' => $estado
    ]);
    exit;
} else {
    $err = $stmt->error;
    $stmt->close();
    $conn->close();
    echo json_encode(['success' => false, 'message' => 'Error al guardar entrega: ' . $err]);
    exit;
}
?>
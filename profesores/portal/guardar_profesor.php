<?php
declare(strict_types=1);
ini_set('display_errors', '0');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Método no permitido']);
    exit;
}

// Credenciales Laragon (según tu prueba OK)
$DB_HOST = '127.0.0.1';
$DB_PORT = 3306;
$DB_USER = 'root';
$DB_PASS = '';
$DB_NAME = 'hootlearn';

$mysqli = @new mysqli($DB_HOST, $DB_USER, $DB_PASS, $DB_NAME, $DB_PORT);
if ($mysqli->connect_errno) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Error de conexión a BD',
        'code' => $mysqli->connect_errno,
        'detail' => $mysqli->connect_error
    ]);
    exit;
}
$mysqli->set_charset('utf8mb4');

// Lee JSON del body
$raw = file_get_contents('php://input');
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'JSON inválido']);
    exit;
}

$nombre     = trim($data['MstroNombre'] ?? '');
$apellidos  = trim($data['MstroAps'] ?? '');
$correo     = strtolower(trim($data['MstroCorreo'] ?? ''));
$dpto       = trim($data['MstroDpto'] ?? '');
$password   = (string)($data['MstroPassword'] ?? '');
$empleadoID = trim($data['MstroEmpleadoID'] ?? '');

if ($nombre === '' || $apellidos === '' || $correo === '' || $dpto === '' || $password === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Faltan campos obligatorios']);
    exit;
}

// Ajuste de longitudes para concordar con la tabla (VARCHAR(255))
if (mb_strlen($nombre) > 255 || mb_strlen($apellidos) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Nombre/Apellidos exceden 255 caracteres']);
    exit;
}

if (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Correo inválido']);
    exit;
}
if (mb_strlen($correo) > 255) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'El correo excede 255 caracteres definidos en la BD']);
    exit;
}

if (mb_strlen($empleadoID) > 50) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'EmpleadoID excede 50 caracteres']);
    exit;
}

$departamentos = [
    'Ciencias de la Computación','Matemáticas','Ingeniería','Física','Química','Biología',
    'Administración de Empresas','Psicología','Ciencias de la Educación','Idiomas y Literatura',
    'Artes y Humanidades','Medicina','Derecho'
];
if (!in_array($dpto, $departamentos, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Departamento inválido']);
    exit;
}

if (strlen($password) < 8) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'La contraseña debe tener al menos 8 caracteres']);
    exit;
}

// Se recomienda hashear la contraseña; se deja comentario para implementar si lo desea
// $pass_hash = password_hash($password, PASSWORD_BCRYPT);

// Verifica si el correo ya existe
$check = $mysqli->prepare('SELECT 1 FROM profesorregistro WHERE ProfCorreo = ? LIMIT 1');
if (!$check) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error preparando consulta']);
    exit;
}
$check->bind_param('s', $correo);
$check->execute();
$check->store_result();
if ($check->num_rows > 0) {
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => 'Ya existe una cuenta con este correo']);
    $check->close();
    exit;
}
$check->close();

// Inserta el registro incluyendo EmpleadoID (NULL si se envía cadena vacía)
$stmt = $mysqli->prepare('INSERT INTO profesorregistro (ProfNombre, ProfApellido, ProfCorreo, Departamento, Profpassword, EmpleadoID) VALUES (?,?,?,?,?, NULLIF(?,\'\'))');
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Error preparando consulta de inserción']);
    exit;
}
$stmt->bind_param('ssssss', $nombre, $apellidos, $correo, $dpto, $password, $empleadoID);

if (!$stmt->execute()) {
    $msg = 'No se pudo guardar el registro';
    if ($stmt->errno === 1406) {
        $msg = 'Datos demasiado largos para las columnas. Asegura que Prof* sean VARCHAR(255) y EmpleadoID <= 50 chars.';
    }
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => $msg, 'code' => $stmt->errno]);
    $stmt->close();
    exit;
}

$insertId = $stmt->insert_id;
$stmt->close();
$mysqli->close();

echo json_encode(['success' => true, 'insertId' => $insertId]);
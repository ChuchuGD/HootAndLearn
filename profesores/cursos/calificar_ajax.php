
<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success'=>false,'error'=>'Método no permitido']); exit;
}

$input = json_decode(file_get_contents('php://input'), true);
$entrega_id = isset($input['entrega_id']) ? (int)$input['entrega_id'] : 0;
$calificacion = isset($input['calificacion']) ? $input['calificacion'] : null;
$retro = isset($input['retro']) ? $input['retro'] : null;

if (!$entrega_id) {
    echo json_encode(['success'=>false,'error'=>'Entrega inválida']); exit;
}

// valida sesión de maestro (si usas maestro_id en sesión)
$maestro_id = $_SESSION['maestro_id'] ?? null;

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hootlearn";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success'=>false,'error'=>'Error conexión DB']); exit;
}
$conn->set_charset('utf8mb4');

// Opcional: comprobar que la entrega existe y pertenece a un curso del maestro
// Asume entregas.ActividadID -> actividades.CursoID -> cursos.ProfID
$checkSql = "SELECT en.ActividadID, en.{$conn->real_escape_string('EntregaID')} FROM `entregas` en WHERE en.EntregaID = ?";
$stmt = $conn->prepare($checkSql);
$stmt->bind_param("i",$entrega_id);
$stmt->execute();
$res = $stmt->get_result();
$row = $res->fetch_assoc();
$stmt->close();
if (!$row) {
    echo json_encode(['success'=>false,'error'=>'Entrega no encontrada']); $conn->close(); exit;
}

// (Opcional) verificar relación actividad -> curso -> prof (si quieres seguridad adicional)
// omitir para simplicidad si no tienes estructura o implementarla según tu esquema

// Actualizar entrega: set Calificacion, Retroalimentacion, Estado='calificado'
$updateSql = "UPDATE entregas SET Calificacion = ?, Retroalimentacion = ?, Estado = 'calificado' WHERE EntregaID = ?";
$stmt = $conn->prepare($updateSql);
if (!$stmt) {
    echo json_encode(['success'=>false,'error'=>'Error preparando consulta']); $conn->close(); exit;
}
$stmt->bind_param("dsi", $calificacion, $retro, $entrega_id); // intenta como double; si falla usar ssi
$ok = $stmt->execute();
$stmt->close();
$conn->close();

if ($ok) {
    echo json_encode(['success'=>true,'calificacion'=>$calificacion]);
} else {
    echo json_encode(['success'=>false,'error'=>'No se pudo actualizar']);
}
?>
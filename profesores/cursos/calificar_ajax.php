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
    echo json_encode(['success'=>false,'error'=>'Error preparando update entregas: '.$conn->error]);
    $conn->close();
    exit;
}
$stmt->bind_param("dsi", $calificacion, $retro, $entrega_id); // intenta como double; si falla usar ssi
$ok = $stmt->execute();
$stmt->close();

if ($ok) {
    // después de actualizar la entrega y si $ok es true, obtener alumno y curso
     $selectSql = "
         SELECT en.EstID, act.CursoID
         FROM entregas en
         JOIN actividades act ON en.ActividadID = act.ActividadID
         WHERE en.EntregaID = ?
     ";
     $stmt = $conn->prepare($selectSql);
     if (!$stmt) {
         echo json_encode(['success'=>false,'error'=>'Error preparando select alumno/curso: '.$conn->error]);
         $conn->close();
         exit;
     }
     $stmt->bind_param("i", $entrega_id);
     $stmt->execute();
     $res = $stmt->get_result();
     $row = $res->fetch_assoc();
     $stmt->close();

     if ($row) {
        $est_id = (int)$row['EstID'];
        $curso_id  = (int)$row['CursoID'];

         // Recalcula LeccionesCompletadas y Progreso (Progreso = LeccionesCompletadas / TotalLecciones)
         $updateSql = "
             UPDATE inscripciones i
             LEFT JOIN cursos c ON i.CursoID = c.CursoID
             LEFT JOIN (
                 SELECT en2.EstID, a2.CursoID, COUNT(*) AS hechas
                 FROM entregas en2
                 JOIN actividades a2 ON en2.ActividadID = a2.ActividadID
                 WHERE en2.Estado = 'calificado'
                 GROUP BY en2.EstID, a2.CursoID
             ) t ON t.EstID = i.EstID AND t.CursoID = c.CursoID
             SET
                 -- primero actualiza la cuenta de lecciones completadas
                 i.LeccionesCompletadas = COALESCE(t.hechas, 0),
                 -- ahora calcula Progreso usando la columna LeccionesCompletadas / TotalLecciones
                 -- Si quieres porcentaje (0..100) deja *100; si quieres fracción (0..1) quita *100
                 i.Progreso = CASE
                     WHEN c.TotalLecciones > 0 THEN ROUND( (i.LeccionesCompletadas / c.TotalLecciones) * 100, 2)
                     ELSE 0
                 END
             WHERE i.EstID = ? AND i.CursoID = ?
         ";

         $stmt = $conn->prepare($updateSql);
         if (!$stmt) {
             echo json_encode(['success'=>false,'error'=>'Error preparando update inscripciones: '.$conn->error]);
             $conn->close();
             exit;
         }
         $stmt->bind_param("ii", $est_id, $curso_id);
         $stmt->execute();
         $stmt->close();
     }

    // cerrar conexión después de todas las operaciones
    $conn->close();

    // responder al cliente
    echo json_encode(['success'=>true,'calificacion'=>$calificacion]);
 } else {
    $conn->close();
    echo json_encode(['success'=>false,'error'=>'No se pudo actualizar']);
 }
?>
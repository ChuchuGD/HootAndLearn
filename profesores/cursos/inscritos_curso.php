<?php
session_start();

// validar sesión / maestro
$maestro_id = null;
if (!empty($_GET['maestro_id'])) {
    $maestro_id = (int)$_GET['maestro_id'];
    $_SESSION['maestro_id'] = $maestro_id;
} else {
    $maestro_id = $_SESSION['maestro_id'] ?? null;
}
if (!$maestro_id) { header("Location: profesor-login.php"); exit; }

$curso_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$curso_id) { echo "Curso inválido."; exit; }

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hootlearn";
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) { die("Error DB"); }
$conn->set_charset("utf8mb4");

// opcional: verificar que el curso pertenece al maestro
$chk = $conn->prepare("SELECT CursoID FROM cursos WHERE CursoID = ? AND ProfID = ?");
$chk->bind_param("ii",$curso_id,$maestro_id);
$chk->execute();
$reschk = $chk->get_result();
if (!$reschk->fetch_assoc()) { $chk->close(); $conn->close(); echo "No autorizado o curso no encontrado."; exit; }
$chk->close();

// comprobar si existe la tabla de estudiantes real (estudianteregistro)
$tableExists = false;
$res = $conn->query("SHOW TABLES LIKE 'estudianteregistro'");
if ($res && $res->num_rows > 0) {
    $tableExists = true;
    $res->free();
}

// obtener inscritos (si existe la tabla de estudiantes, hacer JOIN; si no, devolver solo inscripciones)
if ($tableExists) {
    $sql = "
        SELECT i.EstID, i.LeccionesCompletadas, i.Progreso, e.EstNombre, e.EstCorreo
        FROM inscripciones i
        LEFT JOIN estudianteregistro e ON i.EstID = e.EstID
        WHERE i.CursoID = ?
        ORDER BY e.EstNombre
    ";
} else {
    // tabla de estudiantes no encontrada: traer datos mínimos desde inscripciones
    $sql = "
        SELECT i.EstID, i.LeccionesCompletadas, i.Progreso, '' AS Nombre, '' AS Apellido, '' AS Email
        FROM inscripciones i
        WHERE i.CursoID = ?
        ORDER BY i.EstID
    ";
}

$stmt = $conn->prepare($sql);
if (!$stmt) { echo "Error preparando consulta: ".$conn->error; $conn->close(); exit; }
$stmt->bind_param("i",$curso_id);
$stmt->execute();
$result = $stmt->get_result();

$inscritos = [];
while ($r = $result->fetch_assoc()) { $inscritos[] = $r; }
$stmt->close();
$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Inscritos al curso</title>
<style>
    body{font-family:Segoe UI, sans-serif;padding:1.5rem;background:#f7f7fb}
    .card{max-width:1000px;margin:0 auto;background:#fff;padding:1rem;border-radius:12px;box-shadow:0 6px 20px rgba(0,0,0,.06)}
    table{width:100%;border-collapse:collapse}
    th,td{padding:.75rem;border-bottom:1px solid #eee;text-align:left}
    th{background:#fafafa;font-weight:700}
    .back{display:inline-block;margin-bottom:1rem;padding:.5rem .9rem;background:#eee;border-radius:8px;text-decoration:none;color:#111}
</style>
</head>
<body>
<div class="card">
    <a class="back" href="cursos_profesores.php?maestro_id=<?= (int)$maestro_id ?>">← Volver a mis cursos</a>
    <h2>Inscritos del curso ID <?= (int)$curso_id ?></h2>
    <?php if (empty($inscritos)): ?>
        <p>No hay estudiantes inscritos.</p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>EstID</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th>Lecciones completadas</th>
                    <th>Progreso</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($inscritos as $s): ?>
                    <tr>
                        <td><?= (int)$s['EstID'] ?></td>
                        <td><?= htmlspecialchars(trim(($s['EstNombre'] ?? '') . ' ' . ($s['Apellido'] ?? ''))) ?></td>
                        <td><?= htmlspecialchars($s['EstCorreo'] ?? '') ?></td>
                        <td><?= (int)($s['LeccionesCompletadas'] ?? 0) ?></td>
                        <td><?= is_numeric($s['Progreso']) ? htmlspecialchars($s['Progreso']) : htmlspecialchars($s['Progreso'] ?? '0') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
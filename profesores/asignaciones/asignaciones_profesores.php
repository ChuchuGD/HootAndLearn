<?php
// filepath: [cursos_profesores.php](http://_vscodecontentref_/3)
session_start();
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Permitir maestro_id por GET o sesión
$maestro_id = null;
if (!empty($_GET['maestro_id'])) {
    $maestro_id = (int)$_GET['maestro_id'];
    $_SESSION['maestro_id'] = $maestro_id;
} else {
    $maestro_id = $_SESSION['maestro_id'] ?? null;
}

if (!$maestro_id) {
    header("Location: ../portal/profesor-login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hootlearn";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// --- Diagnóstico: comprobar cursos asociados al profesor ---
$cursos = [];
$stmt = $conn->prepare("SELECT CursoID, NombreCurso, Activo FROM cursos WHERE ProfID = ?");
if ($stmt) {
    $stmt->bind_param("i", $maestro_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $cursos[] = $r;
    }
    $stmt->close();
} else {
    die("Error preparando consulta cursos: " . $conn->error);
}

// --- Traer actividades asociadas a los cursos del profesor ---
$actividades = [];
$sql = "
    SELECT
        a.ActividadID,
        a.CursoID,
        a.Titulo,
        a.Descripcion,
        a.Tipo,
        a.Puntos,
        a.FechaVencimiento,
        a.Requisitos,
        a.ArchivoRequerido,
        a.FechaCreacion,
        a.Activo,
        c.NombreCurso
    FROM actividades a
    JOIN cursos c ON a.CursoID = c.CursoID
    WHERE c.ProfID = ?
    ORDER BY a.FechaVencimiento ASC, a.FechaCreacion DESC
";
$stmt2 = $conn->prepare($sql);
if ($stmt2) {
    $stmt2->bind_param("i", $maestro_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) {
        // normalizar tipos
        $row['ActividadID'] = (int)$row['ActividadID'];
        $row['CursoID'] = (int)$row['CursoID'];
        $row['Puntos'] = (int)$row['Puntos'];
        $row['ArchivoRequerido'] = (bool)$row['ArchivoRequerido'];
        $row['Activo'] = (bool)$row['Activo'];
        $actividades[] = $row;
    }
    $stmt2->close();
} else {
    die("Error preparando consulta actividades: " . $conn->error);
}

$conn->close();

// --- Render HTML con diagnóstico y lista de actividades ---
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Asignaciones - Diagnóstico</title>
<style>
body{font-family:Segoe UI,Roboto,Arial;margin:0;background:#f6f7fb;color:#111}
.container{max-width:1100px;margin:2rem auto;background:#fff;padding:1.5rem;border-radius:8px;box-shadow:0 6px 18px rgba(13,38,76,.08)}
h1{color:#4c1d95}
.info{background:#f3f4f6;padding:.75rem;border-radius:6px;margin-bottom:1rem}
.table{width:100%;border-collapse:collapse}
.table th,.table td{padding:.5rem .75rem;border:1px solid #e6e6e6;text-align:left;font-size:0.95rem}
.bad{color:#b91c1c;font-weight:600}
.ok{color:#065f46;font-weight:600}
.actions{margin-top:1rem}
.btn{display:inline-block;padding:.5rem .75rem;background:#7e22ce;color:#fff;border-radius:6px;text-decoration:none}
.empty{color:#6b7280;padding:1rem}
</style>
</head>
<body>
<div class="container">
    <h1>Asignaciones — diagnóstico</h1>

    <div class="info">
        <strong>Maestro ID:</strong> <?php echo (int)$maestro_id; ?> &nbsp; |
        <strong>Cursos encontrados:</strong> <?php echo count($cursos); ?> &nbsp; |
        <strong>Actividades encontradas:</strong> <?php echo count($actividades); ?>
    </div>

    <h2>Cursos del profesor</h2>
    <?php if (empty($cursos)): ?>
        <div class="empty">No se encontraron cursos asociados a este profesor. Verifica que la columna ProfID de la tabla cursos tenga el ID correcto.</div>
    <?php else: ?>
        <table class="table">
            <thead><tr><th>CursoID</th><th>NombreCurso</th><th>Activo</th></tr></thead>
            <tbody>
            <?php foreach ($cursos as $c): ?>
                <tr>
                    <td><?php echo (int)$c['CursoID']; ?></td>
                    <td><?php echo htmlspecialchars($c['NombreCurso']); ?></td>
                    <td><?php echo $c['Activo'] ? '<span class="ok">Sí</span>' : '<span class="bad">No</span>'; ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <h2 style="margin-top:1.5rem">Actividades</h2>
    <?php if (empty($actividades)): ?>
        <div class="empty">No hay actividades para los cursos de este profesor.</div>
        <div class="info">
            Posibles causas rápidas:
            <ul>
                <li>No existen filas en actividades relacionadas a los CursoID del profesor.</li>
                <li>Las filas existen pero la columna <code>Activo</code> está en 0 (si tu UI filtra por activo).</li>
                <li>El campo FechaVencimiento u otros contienen valores inesperados (revisa en la BD).</li>
            </ul>
            Revisa en tu gestor (phpMyAdmin o consola) con:
            <pre>SELECT * FROM cursos WHERE ProfID = <?php echo (int)$maestro_id; ?>;
SELECT * FROM actividades WHERE CursoID IN (<?php echo implode(',', array_map('intval', array_column($cursos,'CursoID')) ?: [0]); ?>);</pre>
        </div>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th>ActividadID</th>
                    <th>CursoID</th>
                    <th>NombreCurso</th>
                    <th>Título</th>
                    <th>Tipo</th>
                    <th>Puntos</th>
                    <th>FechaVencimiento</th>
                    <th>ArchivoReq</th>
                    <th>Activo</th>
                    <th>Acciones</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($actividades as $a): ?>
                <tr>
                    <td><?php echo $a['ActividadID']; ?></td>
                    <td><?php echo $a['CursoID']; ?></td>
                    <td><?php echo htmlspecialchars($a['NombreCurso']); ?></td>
                    <td><?php echo htmlspecialchars($a['Titulo']); ?></td>
                    <td><?php echo htmlspecialchars($a['Tipo']); ?></td>
                    <td><?php echo $a['Puntos']; ?></td>
                    <td><?php echo htmlspecialchars($a['FechaVencimiento']); ?></td>
                    <td><?php echo $a['ArchivoRequerido'] ? 'Sí' : 'No'; ?></td>
                    <td><?php echo $a['Activo'] ? 'Sí' : 'No'; ?></td>
                    <td>
                        <a class="btn" href="ver_actividad.php?id=<?php echo $a['ActividadID']; ?>&maestro_id=<?php echo $maestro_id; ?>">Ver</a>
                        <a class="btn" href="editar_actividad.php?id=<?php echo $a['ActividadID']; ?>&maestro_id=<?php echo $maestro_id; ?>">Editar</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

</div>
</body>
</html>

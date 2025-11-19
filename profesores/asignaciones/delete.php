<?php
session_start();
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Obtener maestro_id desde GET o sesión
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

$mensaje = "";
$error = "";
$actividad = null;

// Obtener ID de la actividad (acepta id, ActividadID)
$actividadId = 0;
if (!empty($_GET['id'])) $actividadId = (int)$_GET['id'];
elseif (!empty($_GET['ActividadID'])) $actividadId = (int)$_GET['ActividadID'];
elseif (!empty($_POST['id'])) $actividadId = (int)$_POST['id'];

if ($actividadId <= 0) {
    // diagnóstico simple cuando falta id
    header('Content-Type: text/html; charset=utf-8');
    echo '<h2>Falta parámetro id (ActividadID)</h2>';
    echo '<p>Pasa ?id=NN al abrir esta página o usa el listado para generar el enlace.</p>';
    echo '<p><a href="asignaciones_profesores.php?maestro_id=' . (int)$maestro_id . '">Volver a asignaciones</a></p>';
    exit();
}

// Cargar datos de la actividad y curso/propietario
$sql = "SELECT a.ActividadID, a.CursoID, a.Titulo, a.Descripcion, a.FechaVencimiento, a.ArchivoRequerido, a.Activo, c.NombreCurso, c.ProfID
        FROM actividades a
        JOIN cursos c ON a.CursoID = c.CursoID
        WHERE a.ActividadID = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if ($stmt) {
    $stmt->bind_param("i", $actividadId);
    $stmt->execute();
    $res = $stmt->get_result();
    $actividad = $res->fetch_assoc();
    $stmt->close();
} else {
    $error = "Error preparando consulta: " . $conn->error;
}

if (!$actividad) {
    $error = $error ?: "Actividad no encontrada.";
} elseif ((int)$actividad['ProfID'] !== (int)$maestro_id) {
    $error = "No tienes permiso para eliminar esta asignación.";
    $actividad = null;
}

// Procesar eliminación cuando se confirma (form POST)
if ($_SERVER["REQUEST_METHOD"] == "POST" && $actividad) {
    $confirm = $_POST['confirmar'] ?? '';
    if ($confirm === 'ELIMINAR') {
        // Eliminar solo si la actividad pertenece a un curso del profesor
        $del = $conn->prepare("DELETE FROM actividades WHERE ActividadID = ? AND CursoID IN (SELECT CursoID FROM cursos WHERE ProfID = ?)");
        if ($del) {
            $del->bind_param("ii", $actividadId, $maestro_id);
            if ($del->execute()) {
                $del->close();
                $conn->close();
                header("Location: asignaciones_profesores.php?maestro_id=" . (int)$maestro_id . "&deleted=1");
                exit();
            } else {
                $error = "Error al eliminar la asignación: " . $del->error;
            }
            $del->close();
        } else {
            $error = "Error preparando la consulta de eliminación: " . $conn->error;
        }
    } else {
        $error = "Debes escribir 'ELIMINAR' para confirmar la eliminación.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Eliminar Asignación</title>
<style>
body{font-family:Segoe UI,Roboto,Arial;margin:0;background:#f6f7fb;color:#111}
.container{max-width:800px;margin:2rem auto;background:#fff;padding:1.5rem;border-radius:8px;box-shadow:0 6px 18px rgba(13,38,76,.08)}
.btn{display:inline-block;padding:8px 12px;background:#7e22ce;color:#fff;border-radius:6px;text-decoration:none}
.btn-danger{background:#dc2626}
.alert{padding:10px;border-radius:6px;margin-bottom:12px}
.alert-error{background:#fee2e2;color:#991b1b}
</style>
</head>
<body>
<div class="container">
    <h1>Eliminar Asignación</h1>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if ($actividad): ?>
        <p><strong><?= htmlspecialchars($actividad['Titulo']) ?></strong></p>
        <p>Curso: <?= htmlspecialchars($actividad['NombreCurso']) ?> (ID <?= (int)$actividad['CursoID'] ?>)</p>
        <p>Vence: <?= htmlspecialchars($actividad['FechaVencimiento'] ?? '') ?></p>

        <div style="background:#fff3f2;border:1px solid #ffd7d0;padding:12px;border-radius:6px;margin:12px 0;">
            <strong>Advertencia:</strong> Esta acción es irreversible. Se eliminará la asignación seleccionada.
        </div>

        <form method="POST" action="">
            <label>Escribe <strong>ELIMINAR</strong> para confirmar:</label><br>
            <input type="text" name="confirmar" id="confirmar" autocomplete="off" style="padding:.5rem;width:100%;margin:.5rem 0" required>
            <input type="hidden" name="id" value="<?= (int)$actividad['ActividadID'] ?>">
            <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:8px">
                <a class="btn" href="asignaciones_profesores.php?maestro_id=<?= (int)$maestro_id ?>">Cancelar</a>
                <button type="submit" class="btn btn-danger" style="border:none">Eliminar asignación</button>
            </div>
        </form>
    <?php else: ?>
        <p>No hay asignación para eliminar.</p>
        <a class="btn" href="asignaciones_profesores.php?maestro_id=<?= (int)$maestro_id ?>">Volver</a>
    <?php endif; ?>
</div>
</body>
</html>
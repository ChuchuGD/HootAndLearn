<?php
session_start();

// Verificar sesión del profesor
$maestro_id = $_SESSION['maestro_id'] ?? null;
if (!$maestro_id) {
    header("Location: profesor-login.php");
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
$curso = null;

// Obtener ID del curso (acepta varios nombres de parámetro)
$cursoId = 0;
if (!empty($_GET['id'])) $cursoId = (int)$_GET['id'];
elseif (!empty($_GET['IDCurso'])) $cursoId = (int)$_GET['IDCurso'];
elseif (!empty($_GET['CursoID'])) $cursoId = (int)$_GET['CursoID'];

// si no vino por GET, intentar POST (por seguridad)
if (!$cursoId && !empty($_POST['id'])) $cursoId = (int)$_POST['id'];

// ***** Cambiado: evitar redirect 302 y mostrar diagnóstico si falta id *****
if ($cursoId <= 0) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<h2>Falta parámetro id (CursoID)</h2>';
    echo '<p>Se esperaba un parámetro GET/POST con el id del curso.</p>';
    echo '<h3>$_GET</h3><pre>' . htmlspecialchars(print_r($_GET, true)) . '</pre>';
    echo '<h3>$_POST</h3><pre>' . htmlspecialchars(print_r($_POST, true)) . '</pre>';
    echo '<h3>SERVER</h3><pre>HTTP_REFERER: ' . htmlspecialchars($_SERVER['HTTP_REFERER'] ?? 'N/A') . PHP_EOL .
         'REQUEST_URI: ' . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') . '</pre>';
    echo '<p>Prueba manualmente con un enlace:</p>';
    echo '<p><a href="?id=TU_CURSO_ID">Abrir con ?id=TU_CURSO_ID</a></p>';
    echo '<p>En PowerShell prueba:</p>';
    echo '<pre>Invoke-WebRequest "http://localhost/HootAndLearn/profesores/cursos/eliminar_curso.php?id=NN&maestro_id=' . (int)$maestro_id . '"</pre>';
    exit();
}

// Obtener datos del curso (columnas reales)
$stmt = $conn->prepare("SELECT CursoID, NombreCurso, Descripcion, Icono, TotalLecciones, Duracion, Precio, Activo, ProfID FROM cursos WHERE CursoID = ? LIMIT 1");
if ($stmt) {
    $stmt->bind_param("i", $cursoId);
    $stmt->execute();
    $result = $stmt->get_result();
    $curso = $result->fetch_assoc();
    $stmt->close();
} else {
    $error = "Error preparando consulta: " . $conn->error;
}

if (!$curso) {
    $error = $error ?: "Curso no encontrado.";
} elseif ((int)$curso['ProfID'] !== (int)$maestro_id) {
    $error = "No tienes permiso para eliminar este curso.";
    $curso = null;
}

// Procesar eliminación cuando se confirma
if ($_SERVER["REQUEST_METHOD"] == "POST" && $curso) {
    $confirmar = $_POST['confirmar'] ?? '';

    if ($confirmar === 'ELIMINAR') {
        // Eliminar curso (la FK ON DELETE CASCADE elimina actividades/evaluaciones asociadas)
        $stmt = $conn->prepare("DELETE FROM cursos WHERE CursoID = ? AND ProfID = ?");
        if ($stmt) {
            $stmt->bind_param("ii", $cursoId, $maestro_id);
            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                header("Location: cursos_profesores.php?maestro_id=" . (int)$maestro_id . "&deleted=1");
                exit();
            } else {
                $error = "Error al eliminar el curso: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Error preparando la consulta de eliminación: " . $conn->error;
        }
    } else {
        $error = "Debes escribir 'ELIMINAR' para confirmar la acción.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Curso - Hoot & Learn</title>
    <style>
        body{font-family:Segoe UI,Arial;padding:1rem;background:#f3f4f6}
        .container{max-width:800px;margin:2rem auto;background:#fff;padding:1.25rem;border-radius:8px}
        .btn{padding:.5rem .75rem;border-radius:6px;text-decoration:none;color:#fff;background:#7e22ce}
        .btn-danger{background:#dc2626}
    </style>
</head>
<body>
    <div class="container">
        <h2>Eliminar Curso</h2>

        <?php if (!empty($error)): ?>
            <div style="background:#fee2e2;padding:.75rem;border-radius:6px;margin-bottom:1rem;color:#991b1b;"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($curso): ?>
            <p><strong><?= htmlspecialchars($curso['NombreCurso']) ?></strong> (ID <?= (int)$curso['CursoID'] ?>)</p>
            <p><?= htmlspecialchars($curso['Descripcion'] ?? '') ?></p>
            <form method="POST" id="deleteForm">
                <label>Escribe <strong>ELIMINAR</strong> para confirmar:</label><br>
                <input type="text" name="confirmar" id="confirmar" autocomplete="off" style="padding:.5rem;width:100%;margin:.5rem 0">
                <input type="hidden" name="id" value="<?= (int)$curso['CursoID'] ?>">
                <div style="display:flex;gap:.5rem;justify-content:flex-end">
                    <a href="cursos_profesores.php?maestro_id=<?= (int)$maestro_id ?>" class="btn">Cancelar</a>
                    <button type="submit" class="btn btn-danger" style="border:none">Eliminar</button>
                </div>
            </form>
        <?php else: ?>
            <p>No hay curso para eliminar.</p>
            <a href="cursos_profesores.php?maestro_id=<?= (int)$maestro_id ?>" class="btn">Volver</a>
        <?php endif; ?>
    </div>
</body>
</html>
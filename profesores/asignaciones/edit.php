<?php
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

$mensaje = "";
$error = "";
$actividadId = 0;
$editing = false;

// detectar id (GET para abrir, POST para guardar)
if (!empty($_GET['id'])) {
    $actividadId = (int)$_GET['id'];
    $editing = $actividadId > 0;
} elseif (!empty($_POST['id'])) {
    $actividadId = (int)$_POST['id'];
    $editing = $actividadId > 0;
}

// valores por defecto del formulario
$cursoID = 0;
$titulo = '';
$descripcion = '';
$tipo = 'assignment';
$puntos = 100;
$fechaVencimiento = '';
$requisitos = '';
$archivoReq = 0;
$activo = 1;

// cargar datos existentes si es edición (GET)
if ($editing && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sql = "SELECT a.*, c.ProfID
            FROM actividades a
            JOIN cursos c ON a.CursoID = c.CursoID
            WHERE a.ActividadID = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $actividadId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $error = "Actividad no encontrada.";
            $editing = false;
            $actividadId = 0;
        } elseif ((int)$row['ProfID'] !== (int)$maestro_id) {
            $error = "No tienes permiso para editar esta actividad.";
            $editing = false;
            $actividadId = 0;
        } else {
            $cursoID = (int)$row['CursoID'];
            $titulo = $row['Titulo'] ?? '';
            $descripcion = $row['Descripcion'] ?? '';
            $tipo = $row['Tipo'] ?? 'assignment';
            $puntos = (int)($row['Puntos'] ?? 100);
            $fechaVencimiento = $row['FechaVencimiento'] ?? '';
            $requisitos = $row['Requisitos'] ?? '';
            $archivoReq = (int)$row['ArchivoRequerido'];
            $activo = isset($row['Activo']) ? (int)$row['Activo'] : 1;
        }
    } else {
        $error = "Error preparando consulta: " . $conn->error;
        $editing = false;
    }
}

// procesar POST para actualizar
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $editing) {
    $cursoID = (int)($_POST['curso_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo = trim($_POST['tipo'] ?? 'assignment');
    $puntos = (int)($_POST['puntos'] ?? 100);
    $fechaVencimiento = trim($_POST['fecha_vencimiento'] ?? '');
    $requisitos = trim($_POST['requisitos'] ?? '');
    $archivoReq = isset($_POST['archivo_requerido']) && ($_POST['archivo_requerido'] == '1' || $_POST['archivo_requerido'] === 'on') ? 1 : 0;
    $activo = isset($_POST['activo']) ? ((int)$_POST['activo'] ? 1 : 0) : 1;

    // validaciones
    if ($cursoID <= 0) {
        $error = "Debes seleccionar un curso.";
    } elseif (empty($titulo)) {
        $error = "El título es obligatorio.";
    } elseif (empty($fechaVencimiento)) {
        $error = "La fecha de vencimiento es obligatoria.";
    } else {
        // verificar que el curso pertenece al profesor
        $chk = $conn->prepare("SELECT CursoID FROM cursos WHERE CursoID = ? AND ProfID = ? LIMIT 1");
        if ($chk) {
            $chk->bind_param("ii", $cursoID, $maestro_id);
            $chk->execute();
            $rchk = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$rchk) {
                $error = "El curso seleccionado no existe o no te pertenece.";
            } else {
                // ejecutar update
                $sqlUp = "UPDATE actividades SET CursoID = ?, Titulo = ?, Descripcion = ?, Tipo = ?, Puntos = ?, FechaVencimiento = ?, Requisitos = ?, ArchivoRequerido = ?, Activo = ? WHERE ActividadID = ? AND CursoID IN (SELECT CursoID FROM cursos WHERE ProfID = ?)";
                $stmtUp = $conn->prepare($sqlUp);
                if ($stmtUp) {
                    // tipos: i,s,s,s,i,s,s,i,i,i,i
                    $stmtUp->bind_param("isssissiiii", $cursoID, $titulo, $descripcion, $tipo, $puntos, $fechaVencimiento, $requisitos, $archivoReq, $activo, $actividadId, $maestro_id);
                    if ($stmtUp->execute()) {
                        $mensaje = "Asignación actualizada correctamente.";
                        // redirigir al listado con flag
                        header("Location: asignaciones_profesores.php?maestro_id=" . (int)$maestro_id . "&updated=1");
                        exit();
                    } else {
                        $error = "Error al actualizar la asignación: " . $stmtUp->error;
                    }
                    $stmtUp->close();
                } else {
                    $error = "Error preparando actualización: " . $conn->error;
                }
            }
        } else {
            $error = "Error preparando verificación de curso: " . $conn->error;
        }
    }
}

// cargar lista de cursos del profesor para select
$cursos = [];
$stmtC = $conn->prepare("SELECT CursoID, NombreCurso FROM cursos WHERE ProfID = ? AND Activo = 1 ORDER BY NombreCurso ASC");
if ($stmtC) {
    $stmtC->bind_param("i", $maestro_id);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    while ($r = $resC->fetch_assoc()) $cursos[] = $r;
    $stmtC->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $editing ? 'Editar Asignación' : 'Editar' ?></title>
<style>
body{font-family:Segoe UI,Roboto,Arial;margin:0;background:#f6f7fb;color:#111}
.container{max-width:900px;margin:2rem auto;background:#fff;padding:1.5rem;border-radius:8px;box-shadow:0 6px 18px rgba(13,38,76,.08)}
h1{color:#4c1d95;margin-bottom:8px}
.form-group{margin-bottom:12px}
label{display:block;margin-bottom:6px;font-weight:600}
input[type=text], input[type=date], input[type=number], select, textarea{width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px}
textarea{min-height:120px}
.btn{display:inline-block;padding:8px 12px;background:#7e22ce;color:#fff;border-radius:6px;text-decoration:none}
.alert{padding:10px;border-radius:6px;margin-bottom:12px}
.alert-success{background:#d1fae5;color:#065f46}
.alert-error{background:#fee2e2;color:#991b1b}
.checkbox-inline{display:inline-flex;gap:6px;align-items:center}
</style>
</head>
<body>
<div class="container">
    <h1>✏️ Editar Asignación</h1>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <?php if (!$editing): ?>
        <div class="alert alert-error">No se puede editar: id inválido o no tienes permisos.</div>
        <a class="btn" href="asignaciones_profesores.php?maestro_id=<?= (int)$maestro_id ?>">Volver</a>
    <?php else: ?>
    <form method="POST" action="">
        <input type="hidden" name="id" value="<?= (int)$actividadId ?>">
        <div class="form-group">
            <label for="curso_id">Curso</label>
            <select id="curso_id" name="curso_id" required>
                <option value="">-- Selecciona un curso --</option>
                <?php foreach ($cursos as $c): ?>
                    <option value="<?= (int)$c['CursoID'] ?>" <?= ((int)$cursoID === (int)$c['CursoID']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['NombreCurso']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="titulo">Título</label>
            <input type="text" id="titulo" name="titulo" required value="<?= htmlspecialchars($titulo) ?>">
        </div>

        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion"><?= htmlspecialchars($descripcion) ?></textarea>
        </div>

        <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px" class="form-row">
            <div class="form-group">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo">
                    <option value="assignment" <?= $tipo === 'assignment' ? 'selected' : '' ?>>Assignment</option>
                    <option value="project" <?= $tipo === 'project' ? 'selected' : '' ?>>Project</option>
                    <option value="quiz" <?= $tipo === 'quiz' ? 'selected' : '' ?>>Quiz</option>
                </select>
            </div>

            <div class="form-group">
                <label for="puntos">Puntos</label>
                <input type="number" id="puntos" name="puntos" min="0" value="<?= (int)$puntos ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="fecha_vencimiento">Fecha de vencimiento</label>
            <input type="date" id="fecha_vencimiento" name="fecha_vencimiento" required value="<?= htmlspecialchars($fechaVencimiento) ?>">
        </div>

        <div class="form-group">
            <label for="requisitos">Requisitos / Instrucciones</label>
            <textarea id="requisitos" name="requisitos"><?= htmlspecialchars($requisitos) ?></textarea>
        </div>

        <div class="form-group">
            <label class="checkbox-inline"><input type="checkbox" name="archivo_requerido" value="1" <?= $archivoReq ? 'checked' : '' ?>> Archivo requerido</label>
        </div>

        <div class="form-group">
            <label for="activo">Activo</label>
            <select id="activo" name="activo">
                <option value="1" <?= $activo ? 'selected' : '' ?>>Sí</option>
                <option value="0" <?= !$activo ? 'selected' : '' ?>>No</option>
            </select>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end">
            <a class="btn" href="asignaciones_profesores.php?maestro_id=<?= (int)$maestro_id ?>">Cancelar</a>
            <button class="btn" type="submit">Guardar cambios</button>
        </div>
    </form>
    <?php endif; ?>
</div>
</body>
</html>
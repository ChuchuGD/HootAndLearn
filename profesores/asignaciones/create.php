<?php
session_start();
ini_set('display_errors', '1');
error_reporting(E_ALL);

// Permitir maestro_id por GET o sesión (consistente con el resto)
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

// Cargar cursos del profesor para el select
$cursos = [];
$stmtC = $conn->prepare("SELECT CursoID, NombreCurso FROM cursos WHERE ProfID = ? AND Activo = 1 ORDER BY NombreCurso ASC");
if ($stmtC) {
    $stmtC->bind_param("i", $maestro_id);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    while ($r = $resC->fetch_assoc()) {
        $cursos[] = $r;
    }
    $stmtC->close();
}

// Procesar formulario cuando se envía (crear actividad / asignación)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $cursoID = (int)($_POST['curso_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $tipo = trim($_POST['tipo'] ?? 'assignment');
    $puntos = (int)($_POST['puntos'] ?? 100);
    $fechaVenc = trim($_POST['fecha_vencimiento'] ?? '');
    $requisitos = trim($_POST['requisitos'] ?? '');
    $archivoReq = isset($_POST['archivo_requerido']) && ($_POST['archivo_requerido'] == '1' || $_POST['archivo_requerido'] === 'on') ? 1 : 0;

    // Validaciones básicas
    if ($cursoID <= 0) {
        $error = "Debes seleccionar un curso.";
    } elseif (empty($titulo)) {
        $error = "El título es obligatorio.";
    } elseif (empty($fechaVenc)) {
        $error = "La fecha de vencimiento es obligatoria.";
    } else {
        // Verificar que el curso pertenece al profesor
        $stmtCheck = $conn->prepare("SELECT CursoID FROM cursos WHERE CursoID = ? AND ProfID = ? LIMIT 1");
        if ($stmtCheck) {
            $stmtCheck->bind_param("ii", $cursoID, $maestro_id);
            $stmtCheck->execute();
            $resCheck = $stmtCheck->get_result();
            $found = $resCheck->fetch_assoc();
            $stmtCheck->close();

            if (!$found) {
                $error = "El curso seleccionado no existe o no te pertenece.";
            } else {
                // Insertar actividad
                $sql = "INSERT INTO actividades (CursoID, Titulo, Descripcion, Tipo, Puntos, FechaVencimiento, Requisitos, ArchivoRequerido) VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmtIns = $conn->prepare($sql);
                if ($stmtIns) {
                    // tipos: i,s,s,s,i,s,s,i => "isssissi"
                    $stmtIns->bind_param("isssissi", $cursoID, $titulo, $descripcion, $tipo, $puntos, $fechaVenc, $requisitos, $archivoReq);
                    if ($stmtIns->execute()) {
                        $mensaje = "Asignación creada correctamente.";
                        // limpiar valores del formulario
                        $titulo = $descripcion = $requisitos = '';
                        $puntos = 100;
                        $archivoReq = 0;
                        // Opcional: redirigir a listado con flag
                        header("Location: asignaciones_profesores.php?maestro_id=" . (int)$maestro_id . "&created=1");
                        exit();
                    } else {
                        $error = "Error al crear la asignación: " . $stmtIns->error;
                    }
                    $stmtIns->close();
                } else {
                    $error = "Error preparando la consulta: " . $conn->error;
                }
            }
        } else {
            $error = "Error preparando verificación de curso: " . $conn->error;
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Crear Asignación</title>
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
.flex{display:flex;gap:12px;align-items:center}
.checkbox-inline{display:inline-flex;gap:6px;align-items:center}
</style>
</head>
<body>
<div class="container">
    <h1>➕ Crear Asignación</h1>

    <?php if ($mensaje): ?>
        <div class="alert alert-success"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
        <div class="form-group">
            <label for="curso_id">Curso</label>
            <select id="curso_id" name="curso_id" required>
                <option value="">-- Selecciona un curso --</option>
                <?php foreach ($cursos as $c): ?>
                    <option value="<?= (int)$c['CursoID'] ?>" <?= (isset($cursoID) && (int)$cursoID === (int)$c['CursoID']) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($c['NombreCurso']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="titulo">Título</label>
            <input type="text" id="titulo" name="titulo" required value="<?= htmlspecialchars($titulo ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="descripcion">Descripción</label>
            <textarea id="descripcion" name="descripcion"><?= htmlspecialchars($descripcion ?? '') ?></textarea>
        </div>

        <div class="form-row" style="display:grid;grid-template-columns:1fr 1fr;gap:12px">
            <div class="form-group">
                <label for="tipo">Tipo</label>
                <select id="tipo" name="tipo">
                    <option value="assignment" <?= (isset($tipo) && $tipo === 'assignment') ? 'selected' : '' ?>>Assignment</option>
                    <option value="project" <?= (isset($tipo) && $tipo === 'project') ? 'selected' : '' ?>>Project</option>
                    <option value="quiz" <?= (isset($tipo) && $tipo === 'quiz') ? 'selected' : '' ?>>Quiz</option>
                </select>
            </div>

            <div class="form-group">
                <label for="puntos">Puntos</label>
                <input type="number" id="puntos" name="puntos" min="0" value="<?= htmlspecialchars($puntos ?? 100) ?>">
            </div>
        </div>

        <div class="form-group">
            <label for="fecha_vencimiento">Fecha de vencimiento</label>
            <input type="date" id="fecha_vencimiento" name="fecha_vencimiento" required value="<?= htmlspecialchars($fechaVenc ?? '') ?>">
        </div>

        <div class="form-group">
            <label for="requisitos">Requisitos / Instrucciones</label>
            <textarea id="requisitos" name="requisitos"><?= htmlspecialchars($requisitos ?? '') ?></textarea>
        </div>

        <div class="form-group">
            <label class="checkbox-inline"><input type="checkbox" name="archivo_requerido" value="1" <?= (!empty($archivoReq) && $archivoReq==1) ? 'checked' : '' ?>> Archivo requerido</label>
        </div>

        <div style="display:flex;gap:8px;justify-content:flex-end">
            <a class="btn" href="asignaciones_profesores.php?maestro_id=<?= (int)$maestro_id ?>">Cancelar</a>
            <button class="btn" type="submit">Crear</button>
        </div>
    </form>
</div>
</body>
</html>
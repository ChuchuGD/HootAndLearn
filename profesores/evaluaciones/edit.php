<?php
session_start();
ini_set('display_errors', '1');
error_reporting(E_ALL);

// maestro_id por GET o sesión
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
$editing = false;
$evaluacionId = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$editing = $evaluacionId > 0;

// valores por defecto
$cursoSeleccionado = 0;
$titulo = '';
$descripcion = '';
$duracion = 60;
$totalPreguntas = 10;
$puntos = 100;
$fechaVenc = '';
$intentos = 1;
$requiereArchivo = 0;
$activo = 1;

// cargar datos actuales si es GET y existe id
if ($editing && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $sql = "SELECT e.*, c.ProfID FROM evaluaciones e JOIN cursos c ON e.CursoID = c.CursoID WHERE e.EvaluacionID = ? LIMIT 1";
    $stmt = $conn->prepare($sql);
    if ($stmt) {
        $stmt->bind_param("i", $evaluacionId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res->fetch_assoc();
        $stmt->close();

        if (!$row) {
            $error = "Evaluación no encontrada.";
            $editing = false;
        } elseif ((int)$row['ProfID'] !== (int)$maestro_id) {
            $error = "No tienes permiso para editar esta evaluación.";
            $editing = false;
        } else {
            $cursoSeleccionado = (int)$row['CursoID'];
            $titulo = $row['Titulo'] ?? '';
            $descripcion = $row['Descripcion'] ?? '';
            $duracion = (int)($row['Duracion'] ?? 60);
            $totalPreguntas = (int)($row['TotalPreguntas'] ?? 10);
            $puntos = (int)($row['Puntos'] ?? 100);
            $fechaVenc = $row['FechaVencimiento'] ?? '';
            $intentos = (int)($row['Intentos'] ?? 1);
            $requiereArchivo = (int)($row['RequiereArchivo'] ?? 0);
            $activo = isset($row['Activo']) ? (int)$row['Activo'] : 1;
        }
    } else {
        $error = "Error preparando consulta: " . $conn->error;
        $editing = false;
    }
}

// procesar POST para guardar cambios
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $editing) {
    $cursoSeleccionado = (int)($_POST['curso_id'] ?? 0);
    $titulo = trim($_POST['titulo'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $duracion = (int)($_POST['duracion'] ?? 60);
    $totalPreguntas = (int)($_POST['total_preguntas'] ?? 0);
    $puntos = (int)($_POST['puntos'] ?? 100);
    $fechaVenc = trim($_POST['fecha_vencimiento'] ?? '');
    $intentos = (int)($_POST['intentos'] ?? 1);
    $requiereArchivo = isset($_POST['requiere_archivo']) ? 1 : 0;
    $activo = isset($_POST['activo']) ? ((int)$_POST['activo'] ? 1 : 0) : 1;

    // validaciones
    if ($cursoSeleccionado <= 0) {
        $error = "Selecciona un curso válido.";
    } elseif ($titulo === '') {
        $error = "El título es obligatorio.";
    } else {
        // verificar que el curso pertenece al profesor
        $chk = $conn->prepare("SELECT CursoID FROM cursos WHERE CursoID = ? AND ProfID = ? LIMIT 1");
        if ($chk) {
            $chk->bind_param("ii", $cursoSeleccionado, $maestro_id);
            $chk->execute();
            $rchk = $chk->get_result()->fetch_assoc();
            $chk->close();
            if (!$rchk) {
                $error = "El curso seleccionado no existe o no te pertenece.";
            } else {
                // normalizar fecha a Y-m-d (la DB espera DATE)
                if ($fechaVenc) {
                    $fechaObj = DateTime::createFromFormat('Y-m-d\TH:i', $fechaVenc) ?: DateTime::createFromFormat('Y-m-d H:i:s', $fechaVenc) ?: DateTime::createFromFormat('Y-m-d', $fechaVenc);
                    if ($fechaObj) {
                        $fechaVenc = $fechaObj->format('Y-m-d');
                    } else {
                        $fechaVenc = date('Y-m-d', strtotime('+7 days'));
                    }
                } else {
                    $fechaVenc = date('Y-m-d', strtotime('+7 days'));
                }

                // ejecutar update
                $sqlUp = "UPDATE evaluaciones SET CursoID = ?, Titulo = ?, Descripcion = ?, Duracion = ?, TotalPreguntas = ?, Puntos = ?, FechaVencimiento = ?, Intentos = ?, RequiereArchivo = ?, Activo = ? WHERE EvaluacionID = ? AND CursoID IN (SELECT CursoID FROM cursos WHERE ProfID = ?)";
                $stmtUp = $conn->prepare($sqlUp);
                if ($stmtUp) {
                    // tipos: i,s,s,i,i,i,s,i,i,i,i,i  (12 params)
                    $stmtUp->bind_param("issiiisiiiii",
                        $cursoSeleccionado,
                        $titulo,
                        $descripcion,
                        $duracion,
                        $totalPreguntas,
                        $puntos,
                        $fechaVenc,
                        $intentos,
                        $requiereArchivo,
                        $activo,
                        $evaluacionId,
                        $maestro_id
                    );
                    if ($stmtUp->execute()) {
                        // redirigir al listado
                        header("Location: evaluaciones_profesores.php?maestro_id=" . (int)$maestro_id . "&updated=1");
                        exit();
                    } else {
                        $error = "Error al actualizar: " . $stmtUp->error;
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

// cargar cursos del profesor para el select
$cursos = [];
$stmtC = $conn->prepare("SELECT CursoID, NombreCurso FROM cursos WHERE ProfID = ? ORDER BY NombreCurso ASC");
if ($stmtC) {
    $stmtC->bind_param("i", $maestro_id);
    $stmtC->execute();
    $resC = $stmtC->get_result();
    while ($r = $resC->fetch_assoc()) {
        $r['CursoID'] = (int)$r['CursoID'];
        $cursos[] = $r;
    }
    $stmtC->close();
}

$conn->close();

// helper
function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title><?= $editing ? 'Editar Evaluación' : 'Editar' ?></title>
<style>
body{font-family:Segoe UI,Arial;background:#f5f7fb;padding:20px}
.card{max-width:900px;margin:0 auto;background:#fff;padding:18px;border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,.06)}
label{display:block;margin:10px 0 6px;font-weight:600}
input[type=text], textarea, select, input[type=number], input[type=datetime-local], input[type=date]{width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px}
.row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
.actions{display:flex;gap:8px;margin-top:12px;justify-content:flex-end}
.btn{padding:8px 12px;border-radius:8px;border:none;cursor:pointer}
.btn-primary{background:#6b21a8;color:#fff}
.btn-secondary{background:#eef2ff;color:#374151}
.alert{padding:10px;border-radius:8px;margin-bottom:12px}
.success{background:#d1fae5;color:#065f46}
.error{background:#fee2e2;color:#991b1b}
</style>
</head>
<body>
  <div class="card">
    <h2>✏️ Editar evaluación</h2>

    <?php if ($mensaje): ?><div class="alert success"><?= esc($mensaje) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert error"><?= esc($error) ?></div><?php endif; ?>

    <?php if (!$editing): ?>
      <div class="alert error">No se puede editar: id inválido o no tienes permisos.</div>
      <a class="btn btn-secondary" href="evaluaciones_profesores.php?maestro_id=<?= (int)$maestro_id ?>">Volver</a>
    <?php else: ?>
    <form method="POST" action="">
      <input type="hidden" name="id" value="<?= (int)$evaluacionId ?>">

      <label for="curso_id">Curso *</label>
      <select id="curso_id" name="curso_id" required>
        <option value="">-- Selecciona --</option>
        <?php foreach ($cursos as $c): ?>
          <option value="<?= (int)$c['CursoID'] ?>" <?= $cursoSeleccionado === (int)$c['CursoID'] ? 'selected' : '' ?>><?= esc($c['NombreCurso']) ?></option>
        <?php endforeach; ?>
      </select>

      <label for="titulo">Título *</label>
      <input type="text" id="titulo" name="titulo" required value="<?= esc($titulo) ?>">

      <label for="descripcion">Descripción *</label>
      <textarea id="descripcion" name="descripcion" rows="6" required><?= esc($descripcion) ?></textarea>

      <div class="row">
        <div>
          <label for="duracion">Duración (minutos)</label>
          <input type="number" id="duracion" name="duracion" min="1" value="<?= esc($duracion) ?>">
        </div>
        <div>
          <label for="total_preguntas">Total de preguntas</label>
          <input type="number" id="total_preguntas" name="total_preguntas" min="0" value="<?= esc($totalPreguntas) ?>">
        </div>
      </div>

      <div class="row" style="margin-top:8px">
        <div>
          <label for="puntos">Puntos totales</label>
          <input type="number" id="puntos" name="puntos" step="0.1" min="0" value="<?= esc($puntos) ?>">
        </div>
        <div>
          <label for="intentos">Intentos permitidos</label>
          <input type="number" id="intentos" name="intentos" min="1" value="<?= esc($intentos) ?>">
        </div>
      </div>

      <label for="fecha_vencimiento" style="margin-top:10px">Fecha de vencimiento</label>
      <input type="datetime-local" id="fecha_vencimiento" name="fecha_vencimiento" value="<?= esc($fechaVenc ? str_replace(' ', 'T', substr($fechaVenc,0,16)) : '') ?>">

      <div style="margin-top:10px">
        <label><input type="checkbox" name="requiere_archivo" value="1" <?= $requiereArchivo ? 'checked' : '' ?>> Requiere archivo al enviar</label>
      </div>
      <div style="margin-top:6px">
        <label><input type="checkbox" name="activo" value="1" <?= $activo ? 'checked' : '' ?>> Activo</label>
      </div>

      <div class="actions">
        <a class="btn btn-secondary" href="evaluaciones_profesores.php?maestro_id=<?= (int)$maestro_id ?>">Cancelar</a>
        <button type="submit" class="btn btn-primary">Guardar cambios</button>
      </div>
    </form>
    <?php endif; ?>
  </div>
</body>
</html>
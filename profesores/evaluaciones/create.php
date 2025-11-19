<?php
session_start();

// Obtener maestro_id de GET o sesión
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

// Valores por defecto / repoblado
$cursoSeleccionado = (int)($_POST['curso_id'] ?? ($_GET['curso_id'] ?? 0));
$titulo = trim($_POST['titulo'] ?? '');
$descripcion = trim($_POST['descripcion'] ?? '');
// Duración en minutos (entero). Forzar entero para evitar insertar texto en columna INT.
$duracion = (int)($_POST['duracion'] ?? 60);
$totalPreguntas = (int)($_POST['total_preguntas'] ?? 0);
$puntos = (int)($_POST['puntos'] ?? 100);
$fechaVenc = $_POST['fecha_vencimiento'] ?? '';
$intentos = (int)($_POST['intentos'] ?? 1);
$requiereArchivo = isset($_POST['requiere_archivo']) ? 1 : 0;
$activo = isset($_POST['activo']) ? 1 : 0;
$fechaCreacion = $_POST['fechaCreacion'] ?? date('Y-m-d H:i:s');

// Procesar POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // validaciones básicas
    if (!$cursoSeleccionado) {
        $error = "Selecciona un curso válido.";
    } elseif (empty($titulo)) {
        $error = "El título es obligatorio.";
    } elseif (empty($descripcion)) {
        $error = "La descripción es obligatoria.";
    } else {
        // normalizar tipos
        $cursoSeleccionado = (int)$cursoSeleccionado;
        $duracion = (int)$duracion;
        $totalPreguntas = (int)$totalPreguntas;
        $intentos = (int)$intentos;
        $requiereArchivo = $requiereArchivo ? 1 : 0;
        $activo = $activo ? 1 : 0;
        $puntos = (int)$puntos;

        // FechaVencimiento: la columna en la BD es DATE NOT NULL.  
        // Si no envía fecha, usar por defecto +7 días. Asegurar formato Y-m-d.
        if ($fechaVenc) {
            // aceptar formatos de datetime-local o fecha simple
            $fechaObj = DateTime::createFromFormat('Y-m-d\TH:i', $fechaVenc) ?: DateTime::createFromFormat('Y-m-d H:i:s', $fechaVenc) ?: DateTime::createFromFormat('Y-m-d', $fechaVenc);
            if ($fechaObj) {
                $fechaVenc = $fechaObj->format('Y-m-d');
            } else {
                // si no se puede, asignar +7 días
                $fechaVenc = date('Y-m-d', strtotime('+7 days'));
            }
        } else {
            $fechaVenc = date('Y-m-d', strtotime('+7 days'));
        }

        // preparar insert
        $sql = "INSERT INTO evaluaciones (CursoID, Titulo, Descripcion, Duracion, TotalPreguntas, Puntos, FechaVencimiento, Intentos, RequiereArchivo, FechaCreacion, Activo)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        if ($stmt) {
            // Tipos esperados por la tabla:
            // CursoID (i), Titulo (s), Descripcion (s), Duracion (i), TotalPreguntas (i),
            // Puntos (i), FechaVencimiento (s - 'Y-m-d'), Intentos (i), RequiereArchivo (i),
            // FechaCreacion (s), Activo (i)
            $fechaVencParam = $fechaVenc; // ya en 'Y-m-d'
            $stmt->bind_param("issiiisiisi",
                $cursoSeleccionado,
                $titulo,
                $descripcion,
                $duracion,
                $totalPreguntas,
                $puntos,
                $fechaVencParam,
                $intentos,
                $requiereArchivo,
                $fechaCreacion,
                $activo
            );

            if ($stmt->execute()) {
                $mensaje = "Evaluación creada correctamente.";
                // reset valores
                $cursoSeleccionado = 0;
                $titulo = $descripcion = $duracion = '';
                $totalPreguntas = 0;
                $puntos = '';
                $fechaVenc = '';
                $intentos = 1;
                $requiereArchivo = 0;
                $activo = 1;
            } else {
                $error = "Error al guardar: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Error preparando la consulta: " . $conn->error;
        }
    }
}

$conn->close();

// helper para escapar
function esc($s){ return htmlspecialchars($s ?? '', ENT_QUOTES, 'UTF-8'); }
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Crear Evaluación - Profesor</title>
<style>
  body{font-family:Segoe UI,Arial;background:#f5f7fb;padding:20px}
  .card{max-width:820px;margin:0 auto;background:#fff;padding:18px;border-radius:10px;box-shadow:0 6px 20px rgba(0,0,0,.06)}
  label{display:block;margin:10px 0 6px;font-weight:600}
  input[type=text], textarea, select, input[type=number], input[type=datetime-local], input[type=time]{
    width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;font-size:14px;
  }
  .row{display:grid;grid-template-columns:1fr 1fr;gap:12px}
  .actions{display:flex;gap:8px;margin-top:12px}
  .btn{padding:10px 14px;border-radius:8px;border:none;cursor:pointer}
  .btn-primary{background:#6b21a8;color:#fff}
  .btn-secondary{background:#eef2ff;color:#374151}
  .alert{padding:10px;border-radius:8px;margin-bottom:12px}
  .success{background:#d1fae5;color:#065f46}
  .error{background:#fee2e2;color:#991b1b}
</style>
</head>
<body>
  <div class="card">
    <h2>➕ Crear nueva evaluación</h2>

    <?php if ($mensaje): ?>
      <div class="alert success"><?= esc($mensaje) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
      <div class="alert error"><?= esc($error) ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <label for="curso_id">Curso *</label>
      <select id="curso_id" name="curso_id" required>
        <option value="">-- Selecciona --</option>
        <?php foreach ($cursos as $c): ?>
          <option value="<?= (int)$c['CursoID'] ?>" <?= $cursoSeleccionado == $c['CursoID'] ? 'selected' : '' ?>>
            <?= esc($c['NombreCurso']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <label for="titulo">Título *</label>
      <input type="text" id="titulo" name="titulo" required value="<?= esc($titulo) ?>">

      <label for="descripcion">Descripción *</label>
      <textarea id="descripcion" name="descripcion" rows="6" required><?= esc($descripcion) ?></textarea>

      <div class="row">
        <div>
          <label for="duracion">Duración (minutos)</label>
          <input type="number" id="duracion" name="duracion" min="1" placeholder="Ej: 60" value="<?= esc($duracion) ?>">
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

      <label for="fecha_vencimiento" style="margin-top:10px">Fecha de vencimiento (opcional)</label>
      <input type="datetime-local" id="fecha_vencimiento" name="fecha_vencimiento" value="<?= esc($fechaVenc ? str_replace(' ', 'T', substr($fechaVenc,0,16)) : '') ?>">

      <div style="margin-top:10px">
        <label><input type="checkbox" name="requiere_archivo" value="1" <?= $requiereArchivo ? 'checked' : '' ?>> Requiere archivo al enviar</label>
      </div>
      <div style="margin-top:6px">
        <label><input type="checkbox" name="activo" value="1" <?= $activo ? 'checked' : '' ?>> Activo</label>
      </div>

      <input type="hidden" id="fechaCreacion" name="fechaCreacion" value="<?= esc($fechaCreacion) ?>">

      <div class="actions">
        <a class="btn btn-secondary" href="evaluaciones_profesores.php?maestro_id=<?= (int)$maestro_id ?>">← Volver</a>
        <button type="submit" class="btn btn-primary">💾 Crear evaluación</button>
      </div>
    </form>
  </div>

<script>
  // establecer fecha/hora local en fechaCreacion antes de enviar
  function setFechaCreacion(){
    const d=new Date(), pad=n=>String(n).padStart(2,'0');
    const s = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
    const f=document.getElementById('fechaCreacion');
    if(f) f.value=s;
  }
  document.addEventListener('DOMContentLoaded', ()=> {
    setFechaCreacion();
    const form=document.querySelector('form');
    if(form) form.addEventListener('submit', setFechaCreacion);
  });
</script>
</body>
</html>
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

// Cargar cursos del profesor
$courses = [];
$stmt = $conn->prepare("SELECT CursoID, NombreCurso, Activo FROM cursos WHERE ProfID = ?");
if ($stmt) {
    $stmt->bind_param("i", $maestro_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($r = $res->fetch_assoc()) {
        $r['CursoID'] = (int)$r['CursoID'];
        $r['Activo'] = (bool)$r['Activo'];
        $courses[] = $r;
    }
    $stmt->close();
} else {
    die("Error preparando consulta cursos: " . $conn->error);
}

// Cargar evaluaciones relacionadas a los cursos del profesor
$evaluaciones = [];
$sql = "
    SELECT
        e.EvaluacionID,
        e.CursoID,
        e.Titulo,
        e.Descripcion,
        e.Duracion,
        e.TotalPreguntas,
        e.Puntos,
        e.FechaVencimiento,
        e.Intentos,
        e.RequiereArchivo,
        e.FechaCreacion,
        e.Activo,
        c.NombreCurso
    FROM evaluaciones e
    JOIN cursos c ON e.CursoID = c.CursoID
    WHERE c.ProfID = ?
    ORDER BY e.FechaVencimiento ASC, e.FechaCreacion DESC
";
$stmt2 = $conn->prepare($sql);
if ($stmt2) {
    $stmt2->bind_param("i", $maestro_id);
    $stmt2->execute();
    $res2 = $stmt2->get_result();
    while ($row = $res2->fetch_assoc()) {
        $evaluaciones[] = $row;
    }
    $stmt2->close();
} else {
    die("Error preparando consulta evaluaciones: " . $conn->error);
}

// Cargar actividades (asignaciones) relacionadas a los cursos del profesor
$actividades = [];
$sqlA = "
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
$stmtA = $conn->prepare($sqlA);
if ($stmtA) {
    $stmtA->bind_param("i", $maestro_id);
    $stmtA->execute();
    $resA = $stmtA->get_result();
    while ($row = $resA->fetch_assoc()) {
        $actividades[] = $row;
    }
    $stmtA->close();
} else {
    die("Error preparando consulta actividades: " . $conn->error);
}

$conn->close();

// Pasar datos al frontend
$jsCourses = json_encode($courses, JSON_UNESCAPED_UNICODE);
$jsEvaluations = json_encode($evaluaciones, JSON_UNESCAPED_UNICODE);
$jsActividades = json_encode($actividades, JSON_UNESCAPED_UNICODE);

header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Evaluaciones y Asignaciones — Profesor</title>
  <style>
    :root{
      --bg:#f5f7fb; --card:#fff; --muted:#6b7280; --primary:#6b21a8; --accent:#7e22ce;
      --danger:#dc2626; --radius:10px;
    }
    body{margin:0;font-family:Segoe UI,Roboto,Arial;background:var(--bg);color:#111;}
    .wrap{max-width:1200px;margin:28px auto;padding:20px;}
    .topbar{display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:18px}
    .title{display:flex;gap:12px;align-items:center}
    .title h1{margin:0;color:var(--primary);font-size:1.4rem}
    .meta{color:var(--muted);font-size:.95rem}
    .actions{display:flex;gap:8px}
    .btn{background:var(--accent);color:#fff;padding:8px 12px;border-radius:8px;text-decoration:none;font-weight:600}
    .btn-outline{background:transparent;border:1px solid rgba(0,0,0,.06);color:var(--accent)}
    .btn-danger{background:var(--danger)}
    .grid{display:grid;grid-template-columns:1fr 420px;gap:18px}
    .panel{background:var(--card);border-radius:var(--radius);padding:16px;box-shadow:0 6px 18px rgba(13,38,76,0.06)}
    .list{display:grid;gap:12px}
    .card{border-radius:10px;padding:12px;background:#fff;border:1px solid rgba(0,0,0,0.04);display:flex;flex-direction:column;gap:8px}
    .card h3{margin:0;font-size:1.05rem}
    .card .small{font-size:.9rem;color:var(--muted)}
    .card .desc{color:#111;font-size:.95rem}
    .card .row{display:flex;justify-content:space-between;align-items:center;gap:8px}
    .chip{background:#f3f4f6;padding:6px 8px;border-radius:8px;font-size:.85rem;color:var(--muted)}
    .right-actions{display:flex;gap:8px}
    .assign-list{display:grid;gap:8px}
    @media(max-width:980px){ .grid{grid-template-columns:1fr; } .actions{flex-wrap:wrap} }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="topbar">
      <div class="title">
        <h1>📋 Evaluaciones</h1>
        <div class="meta">Profesor ID: <?php echo (int)$maestro_id; ?> — Cursos: <?php echo count($courses); ?></div>
      </div>
      <div class="actions">
        <a class="btn" href="create.php?maestro_id=<?php echo (int)$maestro_id; ?>">➕ Crear evaluación</a>
        <a class="btn btn-outline" href="../cursos/cursos_profesores.php?maestro_id=<?php echo (int)$maestro_id; ?>">📚 Mis cursos</a>
      </div>
    </div>

    <div class="grid">
      <div class="panel">
        <h2 style="margin-top:0">Listado de evaluaciones</h2>
        <div class="list" id="evaluations-list">
          <!-- JS render -->
        </div>
      </div>

      <aside class="panel">
        <h3 style="margin-top:0">Asignaciones recientes</h3>
        <div class="assign-list" id="assignments-list">
          <!-- JS render -->
        </div>
        <div style="margin-top:12px;text-align:right">
          <a class="btn" href="../asignaciones/create.php?maestro_id=<?php echo (int)$maestro_id; ?>">➕ Crear asignación</a>
        </div>
      </aside>
    </div>
  </div>

  <script>
    const serverCourses = <?php echo $jsCourses ?? '[]'; ?>;
    const serverEvaluations = <?php echo $jsEvaluations ?? '[]'; ?>;
    const serverActivities = <?php echo $jsActividades ?? '[]'; ?>;
    const MAESTRO_ID = <?php echo (int)$maestro_id; ?>;

    function esc(s){ if(!s) return ''; return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    function renderEvaluations(){
      const el = document.getElementById('evaluations-list');
      if(!el) return;
      if(!serverEvaluations.length){
        el.innerHTML = '<div class="card"><div class="small">No hay evaluaciones.</div></div>';
        return;
      }
      el.innerHTML = serverEvaluations.map(ev=>{
        const curso = esc(ev.NombreCurso || '');
        const titulo = esc(ev.Titulo || ev.evaluationTitle || 'Sin título');
        const desc = esc((ev.Descripcion || ev.description || '').slice(0,220));
        const due = esc(ev.FechaVencimiento || ev.dueDate || '');
        const id = encodeURIComponent(ev.EvaluacionID ?? ev.id);
        return `<div class="card">
                  <div style="display:flex;justify-content:space-between;align-items:center">
                    <div>
                      <h3>${titulo}</h3>
                      <div class="small">${curso} ${due ? '• Vence: '+due : ''}</div>
                    </div>
                    <div class="right-actions">
                      <a class="chip" href="edit.php?id=${id}&maestro_id=${MAESTRO_ID}">Editar</a>
                      <a class="chip" href="delete.php?id=${id}&maestro_id=${MAESTRO_ID}" onclick="return confirm('Eliminar evaluación? Esto es irreversible.')">Borrar</a>
                    </div>
                  </div>
                  <div class="desc">${desc}</div>
                  <div class="row">
                    <div class="small">Puntos: ${esc(ev.Puntos ?? ev.maxScore ?? '')} • Preguntas: ${esc(ev.TotalPreguntas ?? ev.totalQuestions ?? '')}</div>
                    <div class="small">${ev.RequiereArchivo ? 'Adjunto requerido' : ''}</div>
                  </div>
                </div>`;
      }).join('');
    }

    function renderAssignments(){
      const el = document.getElementById('assignments-list');
      if(!el) return;
      if(!serverActivities.length){
        el.innerHTML = '<div class="card"><div class="small">No hay asignaciones.</div></div>';
        return;
      }
      el.innerHTML = serverActivities.slice(0,8).map(a=>{
        const id = encodeURIComponent(a.ActividadID ?? a.id);
        return `<div style="padding:8px;border-radius:8px;background:#fff;border:1px solid rgba(0,0,0,0.04)">
                  <strong>${esc(a.Titulo || '')}</strong>
                  <div class="small">${esc(a.NombreCurso || '')} • Vence: ${esc(a.FechaVencimiento || '')}</div>
                  <div style="margin-top:6px;display:flex;gap:6px;justify-content:flex-end">
                    <a class="chip" href="edit.php?id=${id}&maestro_id=${MAESTRO_ID}">Editar</a>
                    <a class="chip" href="delete.php?id=${id}&maestro_id=${MAESTRO_ID}" onclick="return confirm('Eliminar asignación?')">Borrar</a>
                  </div>
                </div>`;
      }).join('');
    }

    renderEvaluations();
    renderAssignments();
  </script>
</body>
</html>
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
        $evaluacion = [
            'id' => (int)$row['EvaluacionID'],
            'cursoID' => (int)$row['CursoID'],
            'Titulo' => $row['Titulo'],
            'evaluationTitle' => $row['Titulo'],
            'Descripcion' => $row['Descripcion'],
            'description' => $row['Descripcion'],
            'duration' => (int)$row['Duracion'],
            'totalQuestions' => (int)$row['TotalPreguntas'],
            'maxScore' => (int)$row['Puntos'],
            'dueDate' => $row['FechaVencimiento'],
            'attempts' => (int)$row['Intentos'],
            'requiresFile' => (bool)$row['RequiereArchivo'],
            'fechaCreacion' => $row['FechaCreacion'],
            'activo' => (bool)$row['Activo'],
            'NombreCurso' => $row['NombreCurso'],
            'assignedStudents' => json_encode([]),
            'assignedGroups' => json_encode([])
        ];
        $evaluaciones[] = $evaluacion;
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
        $actividad = [
            'ActividadID' => (int)$row['ActividadID'],
            'id' => (int)$row['ActividadID'],
            'CursoID' => (int)$row['CursoID'],
            'cursoID' => (int)$row['CursoID'],
            'Titulo' => $row['Titulo'],
            'TituloCorto' => $row['Titulo'],
            'TituloRender' => $row['Titulo'],
            'Descripcion' => $row['Descripcion'],
            'Tipo' => $row['Tipo'],
            'Puntos' => (int)$row['Puntos'],
            'FechaVencimiento' => $row['FechaVencimiento'],
            'Requisitos' => $row['Requisitos'],
            'ArchivoRequerido' => (bool)$row['ArchivoRequerido'],
            'FechaCreacion' => $row['FechaCreacion'],
            'Activo' => (bool)$row['Activo'],
            'NombreCurso' => $row['NombreCurso'],
            'submissions' => json_encode([])
        ];
        $actividades[] = $actividad;
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
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Asignación de Evaluaciones</title>
  <link rel="stylesheet" href="./evaluaciones.css">
  <style>@view-transition { navigation: auto; }</style>
 </head>
 <body>
  <div class="container">
   <div class="header">
    <h1 id="app-title">📋 Asignación de Evaluaciones</h1>
    <p id="welcome-message">Asigna evaluaciones y asignaciones a tus cursos</p>
   </div>

   <div class="info" style="margin:1rem 0;">
     <strong>Maestro ID:</strong> <?php echo (int)$maestro_id; ?> —
     <strong>Cursos:</strong> <?php echo count($courses); ?> —
     <strong>Evaluaciones:</strong> <?php echo count($evaluaciones); ?> —
     <strong>Asignaciones:</strong> <?php echo count($actividades); ?>
   </div>

   <div style="display:flex; gap:16px; flex-wrap:wrap;">
     <div style="flex:1; min-width:320px;">
       <h2>Evaluaciones</h2>
       <div id="evaluations-container"></div>
     </div>
     <div style="flex:1; min-width:320px;">
       <h2>Asignaciones (Actividades)</h2>
       <div id="assignments-container"></div>
     </div>
   </div>
  </div>

  <!-- Datos incrustados desde PHP (deben estar antes del JS que los usa) -->
  <script>
    const serverCourses = <?php echo $jsCourses ?? '[]'; ?>;
    const serverEvaluations = <?php echo $jsEvaluations ?? '[]'; ?>;
    const serverActivities = <?php echo $jsActividades ?? '[]'; ?>;
  </script>

  <script>
    // Variables globales
    let evaluations = [];
    let assignments = [];
    let courses = [];

    function renderEvaluations() {
      const container = document.getElementById('evaluations-container');
      if (!container) return;
      if (!evaluations.length) {
        container.innerHTML = '<div class="empty" style="padding:12px">No hay evaluaciones.</div>';
        return;
      }
      container.innerHTML = '<ul style="list-style:none;padding:0;margin:0;">' + evaluations.map(ev => {
        return `<li style="background:#fff;padding:12px;border-radius:8px;margin-bottom:8px;box-shadow:0 2px 6px rgba(0,0,0,0.05);">
          <strong>${escapeHtml(ev.evaluationTitle || ev.Titulo)}</strong><br>
          <small>Curso: ${escapeHtml(ev.NombreCurso || '')} — Fecha: ${escapeHtml(ev.dueDate || ev.FechaVencimiento || '')}</small>
          <div style="margin-top:8px;">${escapeHtml(ev.description || '').slice(0,200)}</div>
          <div style="margin-top:8px;font-size:0.9rem">
            Puntos: ${ev.maxScore || ev.Puntos} — Intentos: ${ev.attempts || ev.Intentos}
            <div style="margin-top:6px;">
              <a class="btn" href="ver_evaluacion.php?id=${ev.id}&maestro_id=<?php echo $maestro_id; ?>">Ver</a>
              <a class="btn" href="editar_evaluacion.php?id=${ev.id}&maestro_id=<?php echo $maestro_id; ?>">Editar</a>
            </div>
          </div>
        </li>`;
      }).join('') + '</ul>';
    }

    function renderAssignments() {
      const container = document.getElementById('assignments-container');
      if (!container) return;
      if (!assignments.length) {
        container.innerHTML = '<div class="empty" style="padding:12px">No hay asignaciones.</div>';
        return;
      }
      container.innerHTML = '<ul style="list-style:none;padding:0;margin:0;">' + assignments.map(a => {
        return `<li style="background:#fff;padding:12px;border-radius:8px;margin-bottom:8px;box-shadow:0 2px 6px rgba(0,0,0,0.05);">
          <strong>${escapeHtml(a.Titulo || a.TituloRender || a.TituloCorto)}</strong><br>
          <small>Curso: ${escapeHtml(a.NombreCurso || '')} — Vence: ${escapeHtml(a.FechaVencimiento || '')}</small>
          <div style="margin-top:8px;">${escapeHtml(a.Descripcion || '').slice(0,200)}</div>
          <div style="margin-top:8px;font-size:0.9rem">
            Puntos: ${a.Puntos || 0} — Tipo: ${escapeHtml(a.Tipo || '')}
            <div style="margin-top:6px;">
              <a class="btn" href="ver_actividad.php?id=${a.ActividadID}&maestro_id=<?php echo $maestro_id; ?>">Ver</a>
              <a class="btn" href="editar_actividad.php?id=${a.ActividadID}&maestro_id=<?php echo $maestro_id; ?>">Editar</a>
            </div>
          </div>
        </li>`;
      }).join('') + '</ul>';
    }

    function escapeHtml(str) {
      if (!str) return '';
      return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }

    function initializeApp() {
      courses = serverCourses || [];
      evaluations = serverEvaluations || [];
      assignments = serverActivities || [];

      renderEvaluations();
      renderAssignments();
    }

    // Inicializar después de definir server variables
    initializeApp();
  </script>
</body>
</html>
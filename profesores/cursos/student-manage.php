<?php
session_start();

// Validar profesor y curso
$maestro_id = null;
if (!empty($_GET['maestro_id'])) {
    $maestro_id = (int)$_GET['maestro_id'];
    $_SESSION['maestro_id'] = $maestro_id;
} else {
    $maestro_id = $_SESSION['maestro_id'] ?? null;
}

$curso_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
if (!$maestro_id) {
    header("Location: profesor-login.php");
    exit();
}
if (!$curso_id) {
    header("Location: cursos_profesores.php?maestro_id={$maestro_id}");
    exit();
}

// Conexión DB
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hootlearn";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Helper: construir URL pública del archivo de entrega
function build_file_url($value) {
    if (empty($value)) return null;

    // Si ya es URL absoluta, devolver tal cual
    if (preg_match('#^https?://#i', $value)) {
        return $value;
    }

    // Normalizar: extraer solo el nombre/parte después de "uploads/entregas/"
    $needle = 'uploads/entregas/';
    if (stripos($value, $needle) !== false) {
        // tomar la parte después de la última ocurrencia de $needle
        $pos = strripos($value, $needle);
        $value = substr($value, $pos + strlen($needle));
    } else {
        // eliminar barras líderes si las hay
        $value = ltrim($value, '/');
    }

    $base = 'http://hootandlearn.test/uploads/entregas/';
    return $base . ltrim($value, '/');
}

// Helper: existe tabla
function table_exists($conn, $name) {
    $name = $conn->real_escape_string($name);
    $res = $conn->query("SHOW TABLES LIKE '{$name}'");
    return ($res && $res->num_rows > 0);
}

// Helper: encuentra primera columna válida entre candidatos
function find_column($conn, $table, $candidates = []) {
    $tableEsc = $conn->real_escape_string($table);
    $res = $conn->query("SHOW COLUMNS FROM `{$tableEsc}`");
    if (!$res) return null;
    $cols = [];
    while ($r = $res->fetch_assoc()) $cols[] = $r['Field'];
    foreach ($candidates as $c) {
        if (in_array($c, $cols, true)) return $c;
    }
    return null;
}

// Helper: obtener la primera columna de la tabla (fallback)
function get_first_column($conn, $table) {
    $tableEsc = $conn->real_escape_string($table);
    $res = $conn->query("SHOW COLUMNS FROM `{$tableEsc}` LIMIT 1");
    if (!$res) return null;
    $row = $res->fetch_assoc();
    return $row['Field'] ?? null;
}

// Determinar tablas/columnas (compatibilidad con tu esquema)
$activityTable = table_exists($conn, 'tareas') ? 'tareas' : (table_exists($conn, 'actividades') ? 'actividades' : null);
$submissionsTable = table_exists($conn, 'entregas') ? 'entregas' : null;
$studentsTable = null;
foreach (['estudiantes','estudianteregistro','estudiante','estudiante_registro'] as $t) {
    if (table_exists($conn, $t)) { $studentsTable = $t; break; }
}
$enrollTable = null;
foreach (['matriculas','matricula','inscripciones','curso_estudiante','curso_estudiantes'] as $t) {
    if (table_exists($conn, $t)) { $enrollTable = $t; break; }
}

if (!$activityTable) {
    $conn->close();
    die("No se encontró la tabla de tareas/actividades ('tareas' o 'actividades'). Adapta el esquema o crea la tabla correspondiente.");
}
if (!$submissionsTable) {
    $conn->close();
    die("No se encontró la tabla 'entregas'. Asegúrate de que exista.");
}
if (!$studentsTable) {
    $conn->close();
    die("No se encontró la tabla de estudiantes. Adapta el nombre de la tabla de estudiantes en el archivo.");
}

// columnas comunes (NO usar por defecto un nombre que podría no existir)
$activityIdCol = find_column($conn, $activityTable, ['TareaID','ActividadID','id','ID']);
$activityDateCol = find_column($conn, $activityTable, ['FechaEntrega','FechaLimite','FechaFin','due_date','Fecha']);
$activityTitleCol = find_column($conn, $activityTable, ['Titulo','Nombre','NombreActividad','TituloActividad','titulo']);

// Si no se detectó ID de actividad, tomar la primera columna como fallback
if (!$activityIdCol) {
    $activityIdCol = get_first_column($conn, $activityTable);
}

// columnas estudiantes
// Reemplazo: usar los nombres reales de tu tabla `estudianteregistro`
$studentIdCol = 'EstID';
$studentNameCol = 'EstNombre';
$studentLastCol = null; // no hay columna de apellido en tu tabla
$studentEmailCol = 'EstCorreo';

// columnas entregas
$submissionIdCol = find_column($conn, $submissionsTable, ['EntregaID','id','ID']) ?? get_first_column($conn, $submissionsTable);
$submissionActivityFk = find_column($conn, $submissionsTable, ['ActividadID','TareaID','actividad_id']);
$submissionStudentFk = find_column($conn, $submissionsTable, ['EstID','EstudianteID','estudiante_id']);
$submissionDateCol = find_column($conn, $submissionsTable, ['FechaEntrega','Fecha','fecha_entrega']);
$submissionFileCol = find_column($conn, $submissionsTable, ['ArchivoRuta','Archivo','ArchivoNombre','ArchivoRuta']);
$submissionCommentCol = find_column($conn, $submissionsTable, ['Comentarios','Comentario','comentarios']);
// nueva: columna para puntaje/nota y estado
$submissionGradeCol = find_column($conn, $submissionsTable, ['Calificacion','calificacion','Nota','nota','grade']) ;
$submissionStateCol = find_column($conn, $submissionsTable, ['Estado','estado']) ;

// Verificar que el curso pertenezca al profesor (mantener la comprobación original)
$stmt = $conn->prepare("SELECT * FROM `cursos` WHERE CursoID = ? AND ProfID = ?");
$stmt->bind_param("ii", $curso_id, $maestro_id);
$stmt->execute();
$curso = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$curso) {
    $conn->close();
    die("Curso no encontrado o no tienes permisos para verlo.");
}

// Construir consulta para obtener la actividad/tarea más reciente.
// Usar la columna de fecha si existe, si no usar el ID (desc)
$orderBy = $activityDateCol ? "`{$activityDateCol}` DESC" : "`{$activityIdCol}` DESC";
$sqlLatest = "SELECT * FROM `{$activityTable}` WHERE CursoID = ? ORDER BY {$orderBy} LIMIT 1";
$tareaStmt = $conn->prepare($sqlLatest);
$tareaStmt->bind_param("i", $curso_id);
$tareaStmt->execute();
$latestTask = $tareaStmt->get_result()->fetch_assoc();
$tareaStmt->close();

$students = [];
$counts = ['entregadas' => 0, 'tardias' => 0, 'no_entregadas' => 0];

if ($latestTask) {
    $activityId = $latestTask[$activityIdCol];

    if ($enrollTable) {
        // Consultar estudiantes matriculados y left join con entregas
        $enrollStudentCol = find_column($conn, $enrollTable, ['EstudianteID','EstID','estudiante_id','EstID']) ?? $studentIdCol;
        $enrollCourseCol = find_column($conn, $enrollTable, ['CursoID','curso_id','CursoID']) ?? 'CursoID';

        $sql = "
            SELECT s.`{$studentIdCol}` AS EstID,
                   " . ($studentNameCol ? "s.`{$studentNameCol}`" : "''") . " AS Nombre,
                   " . ($studentLastCol ? "s.`{$studentLastCol}`" : "''") . " AS Apellido,
                   " . ($studentEmailCol ? "s.`{$studentEmailCol}`" : "''") . " AS Email,
                   sub.`{$submissionIdCol}` AS EntregaID,
                   sub.`{$submissionFileCol}` AS Archivo,
                   sub.`{$submissionCommentCol}` AS Comentarios,
                   " . ($submissionGradeCol ? "sub.`{$submissionGradeCol}` AS Calificacion," : "NULL AS Calificacion,") . "
                   " . ($submissionDateCol ? "sub.`{$submissionDateCol}` AS FechaEntregaEntrega," : "NULL AS FechaEntregaEntrega,") . "
                   " . ($submissionStateCol ? "sub.`{$submissionStateCol}` AS Estado" : "NULL AS Estado") . "
            FROM `{$studentsTable}` s
            INNER JOIN `{$enrollTable}` m ON m.`{$enrollStudentCol}` = s.`{$studentIdCol}` AND m.`{$enrollCourseCol}` = ?
            LEFT JOIN `{$submissionsTable}` sub ON sub.`{$submissionStudentFk}` = s.`{$studentIdCol}` AND sub.`{$submissionActivityFk}` = ?
            ORDER BY " . ($studentNameCol ? "s.`{$studentNameCol}` ASC" : "s.`{$studentIdCol}` ASC") . "
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $curso_id, $activityId);
    } else {
        // Sin tabla de matricula: listar estudiantes que aparecen en entregas para esta actividad
        $sql = "
            SELECT s.`{$studentIdCol}` AS EstID,
                   " . ($studentNameCol ? "s.`{$studentNameCol}`" : "''") . " AS Nombre,
                   " . ($studentLastCol ? "s.`{$studentLastCol}`" : "''") . " AS Apellido,
                   " . ($studentEmailCol ? "s.`{$studentEmailCol}`" : "''") . " AS Email,
                   sub.`{$submissionIdCol}` AS EntregaID,
                   sub.`{$submissionFileCol}` AS Archivo,
                   sub.`{$submissionCommentCol}` AS Comentarios,
                   " . ($submissionGradeCol ? "sub.`{$submissionGradeCol}` AS Calificacion," : "NULL AS Calificacion,") . "
                   " . ($submissionDateCol ? "sub.`{$submissionDateCol}` AS FechaEntregaEntrega," : "NULL AS FechaEntregaEntrega,") . "
                   " . ($submissionStateCol ? "sub.`{$submissionStateCol}` AS Estado" : "NULL AS Estado") . "
            FROM `{$studentsTable}` s
            INNER JOIN `{$submissionsTable}` sub ON sub.`{$submissionStudentFk}` = s.`{$studentIdCol}` AND sub.`{$submissionActivityFk}` = ?
            ORDER BY " . ($studentNameCol ? "s.`{$studentNameCol}` ASC" : "s.`{$studentIdCol}` ASC") . "
        ";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $activityId);
    }

    if (!$stmt) {
        $conn->close();
        die("Error preparando la consulta de estudiantes: " . $conn->error);
    }

    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $status = 'No entregado';
        $late = false;
        if (!empty($row['EntregaID'])) {
            $deadline = $activityDateCol && isset($latestTask[$activityDateCol]) ? strtotime($latestTask[$activityDateCol]) : null;
            $deliveredAt = !empty($row['FechaEntregaEntrega']) ? strtotime($row['FechaEntregaEntrega']) : null;
            if ($deliveredAt === null) {
                $status = 'Entregada';
                $counts['entregadas']++;
            } else {
                if ($deadline !== null && $deliveredAt > $deadline) {
                    $status = 'Entregada (Tardía)';
                    $late = true;
                    $counts['tardias']++;
                } else {
                    $status = 'Entregada';
                    $counts['entregadas']++;
                }
            }
        } else {
            $counts['no_entregadas']++;
        }

        // Build file URL para la vista (maneja nombre de archivo o ruta/URL)
        $row['ArchivoUrl'] = null;
        if (!empty($row['Archivo'])) {
            $row['ArchivoUrl'] = build_file_url($row['Archivo']);
        } elseif (!empty($row['ArchivoRuta'])) {
            $row['ArchivoUrl'] = build_file_url($row['ArchivoRuta']);
        } elseif (!empty($row['ArchivoNombre'])) {
            $row['ArchivoUrl'] = build_file_url($row['ArchivoNombre']);
        }

        $row['status'] = $status;
        $row['late'] = $late;
        $students[] = $row;
    }
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Gestionar tareas - <?= htmlspecialchars($curso['NombreCurso'] ?? '') ?></title>
    <style>
        body{font-family: 'Segoe UI', Tahoma, sans-serif;background:#f3f4f6;padding:2rem}
        .card{background:#fff;border-radius:12px;padding:1.5rem;max-width:1100px;margin:0 auto;box-shadow:0 10px 30px rgba(2,6,23,0.08)}
        header{display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem}
        h1{font-size:1.4rem;color:#4b0082;display:flex;gap:.6rem;align-items:center}
        .meta{color:#6b7280}
        .stats{display:flex;gap:1rem;margin-top:.75rem;color:#374151;font-weight:600}
        .actions{display:flex;gap:.5rem}
        .btn{padding:.5rem .9rem;border-radius:8px;border:none;cursor:pointer;background:#7c3aed;color:#fff;text-decoration:none}
        .btn-secondary{background:#e5e7eb;color:#111}
        .btn-delete{background:#ef4444;color:#fff;border-radius:8px;padding:.5rem .7rem;border:none;cursor:pointer}
        .btn-delete:hover{filter:brightness(.95)}
        table{width:100%;border-collapse:collapse;margin-top:1rem}
        th,td{padding:.75rem;border-bottom:1px solid #eef2ff;text-align:left}
        th{background:#fafafa;color:#374151;font-size:.8rem;text-transform:uppercase}
        .status-ok{color:#065f46;font-weight:700}
        .status-late{color:#b91c1c;font-weight:700}
        .status-miss{color:#6b7280;font-weight:700}
        .file-link{color:#2563eb;text-decoration:none}
        .no-task{padding:1rem;background:#fffbeb;border-radius:8px;color:#92400e}
    </style>
</head>
<body>
    <div class="card">
        <header>
            <div>
                <h1>📘 <?= htmlspecialchars($curso['NombreCurso'] ?? '') ?></h1>
                <div class="meta"><?= htmlspecialchars($curso['Descripcion'] ?? '') ?></div>
                <div class="stats">
                    <div>Última tarea: <?= $latestTask ? htmlspecialchars($latestTask[$activityTitleCol] ?? '—') : '—' ?></div>
                    <?php if ($latestTask): ?>
                        <div>Entrega: <?= htmlspecialchars($latestTask[$activityDateCol] ?? '—') ?></div>
                    <?php endif; ?>
                </div>
            </div>
            
        </header>

        <?php if (!$latestTask): ?>
            <div class="no-task">
                No hay tareas para este curso. Crea una tarea para empezar a ver entregas de estudiantes.
            </div>
        <?php else: ?>
            <section>
                <h3>Vista rápida de entregas — Tarea: <?= htmlspecialchars($latestTask[$activityTitleCol] ?? '—') ?></h3>
                <p style="color:#6b7280">Resumen: <strong class="status-ok"><?= $counts['entregadas'] ?></strong> entregadas, <strong class="status-late"><?= $counts['tardias'] ?></strong> tardías, <strong class="status-miss"><?= $counts['no_entregadas'] ?></strong> sin entregar.</p>

                <table>
                    <thead>
                        <tr>
                            <th>Estudiante</th>
                            <th>Email</th>
                            <th>Estado</th>
                            <th>Calificada</th>
                            <th>Puntaje</th>
                            <th>Archivo / Comentario</th>
                            <th>Fecha de entrega</th>
                            <th style="text-align:right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($students as $s): ?>
                            <tr data-entrega="<?= (int)($s['EntregaID'] ?? 0) ?>">
                                <td><?= htmlspecialchars($s['Nombre'] ?? '') ?></td>
                                <td><?= htmlspecialchars($s['Email'] ?? '') ?></td>
                                <td>
                                    <?php if ($s['status'] === 'No entregado'): ?>
                                        <span class="status-miss">No entregado</span>
                                    <?php elseif ($s['late']): ?>
                                        <span class="status-late"><?= htmlspecialchars($s['status']) ?></span>
                                    <?php else: ?>
                                        <span class="status-ok"><?= htmlspecialchars($s['status']) ?></span>
                                    <?php endif; ?>
                                </td>
                                <td class="cell-calificada">
                                    <?php
                                        $isCalificada = !empty($s['Calificacion']) || (isset($s['Estado']) && strtolower($s['Estado']) === 'calificado');
                                     ?>
                                    <?= $isCalificada ? '<strong>Sí</strong>' : 'No' ?>
                                </td>
                                <td class="cell-puntaje"><?= $s['Calificacion'] !== null ? htmlspecialchars($s['Calificacion']) : '—' ?></td>
                                <td>
                                    <?php if (!empty($s['EntregaID'])): ?>
                                        <?php if (!empty($s['ArchivoUrl'])): ?>
                                            <div><a class="file-link" href="<?= htmlspecialchars($s['ArchivoUrl']) ?>" target="_blank" rel="noopener">Ver archivo</a></div>
                                        <?php endif; ?>
                                        <?php if (!empty($s['Comentarios'])): ?>
                                            <div style="color:#6b7280"><?= nl2br(htmlspecialchars($s['Comentarios'])) ?></div>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        —
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?= !empty($s['FechaEntregaEntrega']) ? htmlspecialchars($s['FechaEntregaEntrega']) : '—' ?>
                                </td>
                                <td style="text-align:right">
                                    <?php if (!empty($s['EntregaID'])): ?>
                                        <!-- Calificar (abre modal). Pasamos datos para prefill si existen -->
                                        <button class="btn btn-primary open-grade"
                                            data-entrega="<?= (int)$s['EntregaID'] ?>"
                                            data-nombre="<?= htmlspecialchars($s['Nombre'] ?? '') ?>"
                                            data-calificacion="<?= htmlspecialchars($s['Calificacion'] ?? '') ?>"
                                            data-retro="<?= htmlspecialchars($s['Comentarios'] ?? '') ?>">
                                            Calificar
                                        </button>
                                        <!-- Editar (abre mismo modal pero fuerza prefill) -->
                                        <button class="btn btn-secondary open-edit"
                                            data-entrega="<?= (int)$s['EntregaID'] ?>"
                                            data-nombre="<?= htmlspecialchars($s['Nombre'] ?? '') ?>"
                                            data-calificacion="<?= htmlspecialchars($s['Calificacion'] ?? '') ?>"
                                            data-retro="<?= htmlspecialchars($s['Comentarios'] ?? '') ?>">
                                            Editar
                                        </button>
                                        <!-- Borrar (elimina la entrega vía AJAX) -->
                                    <?php else: ?>
                                        <a class="btn btn-secondary" href="recordatorio.php?estudiante_id=<?= (int)$s['EstID'] ?>&actividad_id=<?= (int)$activityId ?>&maestro_id=<?= (int)$maestro_id ?>">Recordar</a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($students)): ?>
                            <tr><td colspan="6" style="text-align:center;color:#6b7280">No hay estudiantes matriculados en este curso.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </section>
        <?php endif; ?>
    </div>

    <!-- Modal simple para calificar -->
<div id="gradeModal" style="display:none;position:fixed;inset:0;z-index:1200;align-items:center;justify-content:center;">
  <div id="gradeOverlay" style="position:absolute;inset:0;background:rgba(0,0,0,0.45)"></div>
  <div style="position:relative;background:#fff;border-radius:8px;max-width:520px;width:95%;padding:1rem;z-index:1210;">
    <h3 id="gradeTitle">Calificar entrega</h3>
    <form id="gradeForm">
      <input type="hidden" name="entrega_id" id="entrega_id" value="">
      <div style="margin:0.5rem 0">
        <label>Puntaje <small>(número)</small></label><br>
        <input id="calificacion" name="calificacion" type="number" step="0.01" min="0" style="width:100%;padding:.5rem;border:1px solid #ddd;border-radius:6px" required>
      </div>
      <div style="margin:0.5rem 0">
        <label>Retroalimentación</label><br>
        <textarea id="retro" name="retro" style="width:100%;min-height:100px;padding:.5rem;border:1px solid #ddd;border-radius:6px"></textarea>
      </div>
      <div style="display:flex;gap:.5rem;justify-content:flex-end;margin-top:.75rem">
        <button type="button" id="closeGrade" class="btn btn-secondary">Cancelar</button>
        <button type="submit" class="btn">Guardar</button>
      </div>
      <div id="gradeMsg" style="margin-top:.5rem;color:#b91c1c;display:none"></div>
    </form>
  </div>
</div>

<script>
document.addEventListener('click', function(e){
  if(e.target.matches('.open-grade')){
    // usa data-attributes si vienen (prefill) o deja vacío para nueva calificación
    var id = e.target.getAttribute('data-entrega');
    var nombre = e.target.getAttribute('data-nombre') || '';
    var cal = e.target.getAttribute('data-calificacion') || '';
    var retro = e.target.getAttribute('data-retro') || '';
    document.getElementById('entrega_id').value = id;
    document.getElementById('gradeTitle').textContent = 'Calificar entrega — ' + nombre;
    document.getElementById('calificacion').value = cal;
    document.getElementById('retro').value = retro;
    document.getElementById('gradeMsg').style.display = 'none';
    document.getElementById('gradeModal').style.display = 'flex';
  }
  if(e.target.matches('.open-edit')){
    // misma acción que open-grade (se usa para editar)
    e.target.closest('td').querySelector('.open-grade')?.click();
  }
  if(e.target.matches('.btn-delete')){
    var entregaId = e.target.getAttribute('data-entrega');
    if (!entregaId) return;
    if (!confirm('¿Eliminar esta entrega? Esta acción no se puede deshacer.')) return;
    // llamada AJAX para borrar
    fetch('delete_entrega_ajax.php', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify({ entrega_id: entregaId })
    }).then(r=>r.json()).then(data=>{
      if(data.success){
        // actualizar fila: marcar como no entregado
        var tr = document.querySelector('tr[data-entrega="'+entregaId+'"]');
        if(tr){
          tr.querySelector('.cell-calificada').innerHTML = 'No';
          tr.querySelector('.cell-puntaje').textContent = '—';
          // estado
          var estadoCell = tr.querySelector('td');
          // encontrar la celda de estado (tercera td)
          var tds = tr.querySelectorAll('td');
          if(tds[2]) tds[2].innerHTML = '<span class="status-miss">No entregado</span>';
          // quitar archivo y fecha
          if(tds[5]) tds[5].innerHTML = '—';
          if(tds[6]) tds[6].innerHTML = '—';
          // remover botones Calificar/Editar/Borrar: sustituir por Recordar link
          if(tds[7]) tds[7].innerHTML = '<a class="btn btn-secondary" href=\"recordatorio.php?estudiante_id='+ (tr.getAttribute('data-estid') || '') +'&actividad_id=<?= (int)$activityId ?>&maestro_id=<?= (int)$maestro_id ?>\">Recordar</a>';
        }
      } else {
        alert(data.error || 'Error al eliminar.');
      }
    }).catch(()=>{ alert('Error de red al intentar eliminar.'); });
  }

  if(e.target.id === 'gradeOverlay' || e.target.id === 'closeGrade'){
    document.getElementById('gradeModal').style.display = 'none';
  }
});

// enviar calificación via fetch (AJAX)
document.getElementById('gradeForm').addEventListener('submit', function(ev){
  ev.preventDefault();
  var form = ev.target;
  var entrega_id = document.getElementById('entrega_id').value;
  var calificacion = document.getElementById('calificacion').value;
  var retro = document.getElementById('retro').value;

  var btn = form.querySelector('button[type="submit"]');
  btn.disabled = true;
  fetch('calificar_ajax.php', {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify({ entrega_id: entrega_id, calificacion: calificacion, retro: retro })
  }).then(r=>r.json()).then(data=>{
    btn.disabled = false;
    if(data.success){
      // actualizar fila en DOM
      var tr = document.querySelector('tr[data-entrega="'+entrega_id+'"]');
      if(tr){
        var cellCalificada = tr.querySelector('.cell-calificada');
        var cellPuntaje = tr.querySelector('.cell-puntaje');
        if(cellCalificada) cellCalificada.innerHTML = '<strong>Sí</strong>';
        if(cellPuntaje) cellPuntaje.textContent = data.calificacion;
      }
      document.getElementById('gradeModal').style.display = 'none';
    } else {
      var msg = document.getElementById('gradeMsg');
      msg.style.display = 'block';
      msg.textContent = data.error || 'Error al guardar.';
    }
  }).catch(err=>{
    btn.disabled = false;
    var msg = document.getElementById('gradeMsg');
    msg.style.display = 'block';
    msg.textContent = 'Error de red.';
  });
});
</script>
</body>
</html>
<?php
// forzar logout en pruebas
session_start();
unset($_SESSION['student_id']);
unset($_SESSION['student_name']);
?>
<?php
session_start();
require_once __DIR__ . '/conexion.php';

// API: manejar inscripción vía JSON POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // aceptar JSON o form
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    if (!isset($input['action']) || $input['action'] !== 'enroll') {
        header('Content-Type: application/json; charset=utf-8', true, 400);
        echo json_encode(['success' => false, 'message' => 'Acción inválida']);
        exit;
    }

    $courseId = isset($input['courseId']) ? (int)$input['courseId'] : 0;
    $studentId = isset($_SESSION['student_id']) ? (int)$_SESSION['student_id'] : 0;

    if ($courseId <= 0) {
        header('Content-Type: application/json; charset=utf-8', true, 400);
        echo json_encode(['success' => false, 'message' => 'Curso inválido']);
        exit;
    }

    // Si no hay sesión: crear/reusar cuenta "guest" por sesión (no global)
    if ($studentId <= 0) {
        // usar session_id() para crear un guest único por navegador/sesión
        if (session_id() === '') session_start();
        $guestEmail = 'guest_' . session_id() . '@hootlearn.local';
        $gstmt = $conn->prepare("SELECT IDEst FROM EstudianteRegistro WHERE EstCorreo = ? LIMIT 1");
        if ($gstmt) {
            $gstmt->bind_param("s", $guestEmail);
            $gstmt->execute();
            $gres = $gstmt->get_result();
            if ($grow = $gres->fetch_assoc()) {
                $studentId = (int)$grow['IDEst'];
            }
            $gstmt->close();
        }
        if ($studentId <= 0) {
            // crear cuenta guest mínima con email único por sesión
            $insertGuest = $conn->prepare("INSERT INTO EstudianteRegistro (EstNombre, EstCorreo, EstPassword) VALUES (?, ?, ?)");
            $guestName = 'Invitado';
            $guestPass = ''; // en producción usar hash
            if ($insertGuest) {
                $insertGuest->bind_param("sss", $guestName, $guestEmail, $guestPass);
                $insertGuest->execute();
                $studentId = (int)$conn->insert_id;
                $insertGuest->close();
            }
        }
    }
    // setear sesión para que "mis-cursos.php" muestre los cursos inmediatamente
    $_SESSION['student_id'] = $studentId;
    // opcional: cargar y guardar nombre del estudiante
    $sn = $conn->prepare("SELECT EstNombre FROM EstudianteRegistro WHERE IDEst = ? LIMIT 1");
    if ($sn) {
        $sn->bind_param("i", $studentId);
        $sn->execute();
        $sres = $sn->get_result();
        if ($srow = $sres->fetch_assoc()) $_SESSION['student_name'] = $srow['EstNombre'] ?? 'Invitado';
        $sn->close();
    }

    // Intentar insertar inscripción (maneja duplicados)
    $conn->begin_transaction();
    try {
        $ins = $conn->prepare("INSERT INTO inscripciones (IDEst, curso_id, fecha_inscripcion, created_at) VALUES (?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)");
        $ins->bind_param("ii", $studentId, $courseId);
        $insResult = $ins->execute();
        if ($ins === false) {
            throw new Exception($conn->error);
        }
        $ins->close();

        if ($insResult === false) {
            // si duplicate entry -> ya inscrito
            if ($conn->errno === 1062) {
                $already = true;
            } else {
                throw new Exception($conn->error);
            }
        } else {
            $already = false;
        }

        // Asegurar fila en progreso_estudiante (inserta o actualiza total_lecciones)
        $q = $conn->prepare("SELECT Lecciones FROM cursos WHERE IDCurso = ?");
        $q->bind_param("i", $courseId);
        $q->execute();
        $res = $q->get_result();
        $totalLessons = 0;
        if ($r = $res->fetch_assoc()) $totalLessons = (int)$r['Lecciones'];
        $q->close();

        $p = $conn->prepare("
            INSERT INTO progreso_estudiante (estudiante_id, curso_id, lecciones_completas, total_lecciones)
            VALUES (?, ?, 0, ?)
            ON DUPLICATE KEY UPDATE total_lecciones = VALUES(total_lecciones)
        ");
        $p->bind_param("iii", $studentId, $courseId, $totalLessons);
        $p->execute();
        $p->close();

        $conn->commit();

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['success' => true, 'already_enrolled' => $already, 'message' => $already ? 'Ya inscrito' : 'Inscripción exitosa', 'studentId' => $studentId]);
        exit;

    } catch (Exception $e) {
        $conn->rollback();
        header('Content-Type: application/json; charset=utf-8', true, 500);
        echo json_encode(['success' => false, 'message' => 'Error en el servidor', 'error' => $e->getMessage()]);
        exit;
    }
}

// Si no es POST: cargar lista de cursos desde DB para mostrar en la UI
$courses = [];
$stmt = $conn->prepare("SELECT IDCurso AS id, Titulo AS title, Instructor AS instructor, Icono AS icon, Lecciones AS lessons, descripcion FROM cursos ORDER BY created_at DESC");
if ($stmt) {
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $courses[] = $row;
    }
    $stmt->close();
}

// Obtener cursos en los que ya está inscrito el usuario real o el guest (para deshabilitar botón)
$enrolledCourseIds = [];
$checkStudentId = isset($_SESSION['student_id']) ? (int)$_SESSION['student_id'] : 0;
if ($checkStudentId <= 0) {
    // intentar usar guest si existe
    $guestEmail = 'guest@hootlearn.local';
    $gstmt = $conn->prepare("SELECT IDEst FROM EstudianteRegistro WHERE EstCorreo = ? LIMIT 1");
    if ($gstmt) {
        $gstmt->bind_param("s", $guestEmail);
        $gstmt->execute();
        $gres = $gstmt->get_result();
        if ($grow = $gres->fetch_assoc()) $checkStudentId = (int)$grow['IDEst'];
        $gstmt->close();
    }
}
if ($checkStudentId > 0) {
    $eStmt = $conn->prepare("SELECT curso_id FROM inscripciones WHERE IDEst = ?");
    if ($eStmt) {
        $eStmt->bind_param("i", $checkStudentId);
        $eStmt->execute();
        $eRes = $eStmt->get_result();
        while ($er = $eRes->fetch_assoc()) {
            $enrolledCourseIds[] = (int)$er['curso_id'];
        }
        $eStmt->close();
    }
}

$studentName = isset($_SESSION['student_name']) ? $_SESSION['student_name'] : null;
$studentId = isset($_SESSION['student_id']) ? (int)$_SESSION['student_id'] : 0;

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8"/>
    <meta name="viewport" content="width=device-width,initial-scale=1"/>
    <title>Inscribirse a Cursos - Hoot & Learn</title>
    <style>
        /* Mantener estilos existentes, simplificados */
        body{font-family:Inter,Segoe UI,Roboto,sans-serif;background:#f7fafc;color:#2d3748;margin:0;padding:0}
        .header{padding:1.5rem 2rem;background:rgba(255,255,255,0.2);backdrop-filter:blur(10px);border-bottom:1px solid rgba(0,0,0,0.03)}
        .header .logo{font-weight:800}
        .main{max-width:1200px;margin:2rem auto;padding:0 1rem}
        .courses-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(300px,1fr));gap:1.5rem}
        .card{background:white;border-radius:12px;padding:1.5rem;box-shadow:0 6px 18px rgba(15,23,42,0.06);border:1px solid rgba(0,0,0,0.03)}
        .card .title{font-weight:700;margin-bottom:0.5rem}
        .card .meta{color:#4a5568;font-size:0.9rem;margin-bottom:1rem}
        .enroll{display:inline-block;padding:0.8rem 1rem;border-radius:10px;background:linear-gradient(135deg,#5a67d8,#667eea);color:#fff;border:none;cursor:pointer}
        .enroll[disabled]{opacity:0.6;cursor:default}
        .login-cta{background:#fff;border:1px solid #e2e8f0;padding:0.6rem 0.9rem;border-radius:8px;color:#2d3748;text-decoration:none}
    </style>
</head>
<body>
    <header class="header">
        <div class="logo">Hoot & Learn</div>
    </header>

    <main class="main">
        <h1>Inscribirse a Cursos</h1>
        <p>Explora los cursos disponibles e inscríbete. <?php if ($studentName) echo "Sesión: " . htmlspecialchars($studentName); ?></p>

        <div class="courses-grid" id="coursesGrid"></div>
    </main>

    <script>
        const courses = <?php echo json_encode($courses, JSON_UNESCAPED_UNICODE); ?>;
        // permitimos inscripción aun sin sesión => siempre mostramos botón
        const enrolledCourseIds = <?php echo json_encode($enrolledCourseIds ?: [], JSON_UNESCAPED_UNICODE); ?>;

        function render() {
            const grid = document.getElementById('coursesGrid');
            if (!courses.length) {
                grid.innerHTML = '<div class="card">No hay cursos disponibles.</div>';
                return;
            }

            grid.innerHTML = courses.map(c => {
                const isEnrolled = enrolledCourseIds.includes(c.id);
                return `
                <div class="card" id="course-${c.id}">
                    <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:0.5rem">
                        <div style="display:flex;gap:0.75rem;align-items:center">
                            <div style="font-size:1.8rem">${escape(c.icon || '📚')}</div>
                            <div>
                                <div class="title">${escape(c.title)}</div>
                                <div class="meta">${escape(c.instructor || '')}</div>
                            </div>
                        </div>
                        <div style="text-align:right">
                            <div style="font-weight:700;color:#5a67d8">${c.lessons} lecciones</div>
                        </div>
                    </div>
                    <div style="color:#4a5568;margin-bottom:1rem">${escape(c.descripcion || '')}</div>
                    <div style="display:flex;gap:0.75rem;align-items:center">
                        ${isEnrolled ? `<button class="enroll" disabled>Ya inscrito</button>` : `<button class="enroll" onclick="enrollCourse(${c.id}, this)">Inscribirse</button>`}
                        <a href="course-details.php?courseId=${c.id}" style="color:#5a67d8;text-decoration:none">Ver detalles</a>
                    </div>
                </div>
                `;
            }).join('');
        }

        async function enrollCourse(courseId, btn) {
            btn.disabled = true;
            btn.textContent = 'Inscribiendo...';

            try {
                const resp = await fetch('enroll-courses.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'enroll', courseId })
                });
                const data = await resp.json();
                if (resp.ok && data.success) {
                    btn.textContent = data.already_enrolled ? 'Ya inscrito' : 'Inscrito';
                    btn.disabled = true;
                    enrolledCourseIds.push(courseId);
                    // redirigir para ver el curso en "Mis Cursos"
                    window.location.href = 'mis-cursos.php';
                } else {
                    throw new Error(data.message || 'Error');
                }
            } catch (err) {
                console.error(err);
                alert('Error al inscribirse: ' + (err.message || 'comprueba la consola'));
                btn.disabled = false;
                btn.textContent = 'Inscribirse';
            }
        }

        function escape(s) {
            return String(s || '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'})[c]);
        }

        render();
    </script>
</body>
</html>

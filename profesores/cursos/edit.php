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
$editing = false;
$cursoId = 0;

// Detectar id (GET para abrir editar, POST para guardar)
if (!empty($_GET['id'])) {
    $cursoId = (int)$_GET['id'];
    $editing = $cursoId > 0;
} elseif (!empty($_POST['id'])) {
    $cursoId = (int)$_POST['id'];
    $editing = $cursoId > 0;
}

// Si estamos en modo edición y es GET: cargar datos para rellenar formulario
if ($editing && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $stmt = $conn->prepare("SELECT CursoID, NombreCurso, Descripcion, Icono, TotalLecciones, Duracion, Precio, FechaCreacion, ProfID FROM cursos WHERE CursoID = ? LIMIT 1");
    if ($stmt) {
        $stmt->bind_param("i", $cursoId);
        $stmt->execute();
        $res = $stmt->get_result();
        $cursoRow = $res->fetch_assoc();
        $stmt->close();

        if (!$cursoRow) {
            $error = "Curso no encontrado.";
            $editing = false;
            $cursoId = 0;
        } elseif ((int)$cursoRow['ProfID'] !== (int)$maestro_id) {
            $error = "No tienes permiso para editar este curso.";
            $editing = false;
            $cursoId = 0;
        } else {
            // poblar variables para el formulario
            $nombreCurso = $cursoRow['NombreCurso'];
            $descripcion = $cursoRow['Descripcion'];
            $duracion = $cursoRow['Duracion'];
            $icono = $cursoRow['Icono'] ?? '📚';
            $lecciones = (int)$cursoRow['TotalLecciones'];
            $precio = $cursoRow['Precio'];
            $fechaCreacion = $cursoRow['FechaCreacion'];
        }
    } else {
        $error = "Error preparando consulta: " . $conn->error;
        $editing = false;
    }
}

// Procesar formulario cuando se envía (crear o actualizar)
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombreCurso = trim($_POST['nombre_curso'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $duracion = trim($_POST['duracion'] ?? '');
    $icono = trim($_POST['icono'] ?? '📚');
    $instructor = trim($_POST['instructor'] ?? '');
    $lecciones = (int)($_POST['lecciones'] ?? 0);
    $precio = trim($_POST['precio'] ?? '');
    $fechaCreacion = $_POST['fechaCreacion'] ?? date('Y-m-d H:i:s');

    // asegurar tipos
    $maestro_id = (int)$maestro_id;
    $lecciones = (int)$lecciones;
    $precio = is_numeric($precio) ? (float)$precio : null;

    // Validaciones mínimas
    if (empty($nombreCurso)) {
        $error = "El nombre del curso es obligatorio.";
    } elseif (empty($descripcion)) {
        $error = "La descripción es obligatoria.";
    } elseif (empty($duracion)) {
        $error = "La duración es obligatoria.";
    } else {
        if ($cursoId > 0) {
            // UPDATE existente (solo si pertenece al profesor)
            $stmt = $conn->prepare("UPDATE cursos SET NombreCurso = ?, Descripcion = ?, Icono = ?, TotalLecciones = ?, Duracion = ?, Precio = ?, FechaCreacion = ? WHERE CursoID = ? AND ProfID = ?");
            if ($stmt) {
                // tipos: s,s,s,i,s,d,s,i,i
                $stmt->bind_param("sssisdssi", $nombreCurso, $descripcion, $icono, $lecciones, $duracion, $precio, $fechaCreacion, $cursoId, $maestro_id);
                if ($stmt->execute()) {
                    if ($stmt->affected_rows >= 0) {
                        $mensaje = "Curso actualizado correctamente.";
                    } else {
                        $error = "No se realizaron cambios.";
                    }
                } else {
                    $error = "Error al actualizar el curso: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Error preparando la consulta de actualización: " . $conn->error;
            }
        } else {
            // INSERT nuevo
            $stmt = $conn->prepare("INSERT INTO cursos (NombreCurso, Descripcion, ProfID, Icono, TotalLecciones, Duracion, Precio, FechaCreacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
            if ($stmt) {
                $stmt->bind_param("ssisisds", $nombreCurso, $descripcion, $maestro_id, $icono, $lecciones, $duracion, $precio, $fechaCreacion);
                if ($stmt->execute()) {
                    $mensaje = "Curso creado exitosamente.";
                    // limpiar campos
                    $nombreCurso = $descripcion = $duracion = $instructor = "";
                    $lecciones = 0;
                    $icono = "📚";
                    $fechaCreacion = date('Y-m-d H:i:s');
                } else {
                    $error = "Error al crear el curso: " . $stmt->error;
                }
                $stmt->close();
            } else {
                $error = "Error preparando la consulta: " . $conn->error;
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $cursoId > 0 ? 'Editar Curso' : 'Crear Curso' ?> - Hoot & Learn</title>
    <style>
        * { margin:0; padding:0; box-sizing:border-box; }
        body { font-family:'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); min-height:100vh; padding:2rem;}
        .container{max-width:800px;margin:0 auto;background:white;border-radius:20px;padding:2rem;box-shadow:0 20px 60px rgba(0,0,0,0.3)}
        header{text-align:center;margin-bottom:2rem;padding-bottom:1rem;border-bottom:2px solid #ede9fe}
        h1{color:#6b21a8;font-size:2rem;margin-bottom:.5rem}
        .subtitle{color:#6b7280;font-size:1rem}
        .alert{padding:1rem;border-radius:10px;margin-bottom:1.5rem;font-weight:500}
        .alert-success{background:#d1fae5;color:#065f46;border:1px solid #34d399}
        .alert-error{background:#fee2e2;color:#991b1b;border:1px solid #f87171}
        .form-group{margin-bottom:1.5rem}
        label{display:block;color:#374151;font-weight:600;margin-bottom:.5rem;font-size:.95rem}
        input[type="text"],input[type="number"],textarea,select{width:100%;padding:.75rem;border:2px solid #e5e7eb;border-radius:8px;font-size:1rem;transition:all .3s}
        input:focus,textarea:focus,select:focus{outline:none;border-color:#7e22ce;box-shadow:0 0 0 3px rgba(126,34,206,.1)}
        textarea{min-height:120px;resize:vertical}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:1rem}
        .icon-selector{display:grid;grid-template-columns:repeat(6,1fr);gap:.5rem;margin-top:.5rem}
        .icon-option{padding:.75rem;border:2px solid #e5e7eb;border-radius:8px;text-align:center;cursor:pointer;font-size:1.5rem;transition:all .3s}
        .icon-option:hover{border-color:#7e22ce;background:#f5f3ff}
        .icon-option.selected{border-color:#7e22ce;background:#ede9fe}
        .button-group{display:flex;gap:1rem;margin-top:2rem}
        .btn{padding:.875rem 1.5rem;border:none;border-radius:10px;font-size:1rem;font-weight:600;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;gap:.5rem;text-decoration:none}
        .btn-primary{background:linear-gradient(135deg,#7e22ce 0%,#5b21b6 100%);color:#fff;flex:1}
        .btn-secondary{background:#f3f4f6;color:#374151;flex:1}
        .helper-text{font-size:.85rem;color:#6b7280;margin-top:.25rem}
        @media (max-width:768px){body{padding:1rem}.container{padding:1.5rem}.form-row{grid-template-columns:1fr}.icon-selector{grid-template-columns:repeat(4,1fr)}.button-group{flex-direction:column}}
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1><?= $cursoId > 0 ? '✏️ Editar Curso' : '✨ Crear Nuevo Curso' ?></h1>
            <p class="subtitle"><?= $cursoId > 0 ? 'Modifica los datos del curso' : 'Completa la información para crear un curso' ?></p>
        </header>

        <?php if (!empty($mensaje)): ?>
            <div class="alert alert-success">✅ <?= htmlspecialchars($mensaje) ?></div>
        <?php endif; ?>

        <?php if (!empty($error)): ?>
            <div class="alert alert-error">❌ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <input type="hidden" id="fechaCreacion" name="fechaCreacion" value="<?= htmlspecialchars($fechaCreacion ?? '') ?>">
            <?php if ($cursoId > 0): ?>
                <input type="hidden" name="id" value="<?= (int)$cursoId ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="nombre_curso">Nombre del Curso *</label>
                <input type="text" id="nombre_curso" name="nombre_curso" required placeholder="Ej: Introducción a JavaScript" value="<?= htmlspecialchars($nombreCurso ?? '') ?>">
            </div>

            <div class="form-group">
                <label for="descripcion">Descripción *</label>
                <textarea id="descripcion" name="descripcion" required placeholder="Describe de qué trata el curso..."><?= htmlspecialchars($descripcion ?? '') ?></textarea>
                <div class="helper-text">Proporciona una descripción detallada del curso</div>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="instructor">Nombre del Instructor *</label>
                    <input type="text" id="instructor" name="instructor" required placeholder="Tu nombre" value="<?= htmlspecialchars($instructor ?? '') ?>">
                </div>

                <div class="form-group">
                    <label for="duracion">Duración *</label>
                    <input type="text" id="duracion" name="duracion" required placeholder="Ej: 8 semanas, 40 horas" value="<?= htmlspecialchars($duracion ?? '') ?>">
                </div>
            </div>

            <div class="form-group">
                <label for="lecciones">Número de Lecciones</label>
                <input type="number" id="lecciones" name="lecciones" min="0" placeholder="Ej: 12" value="<?= htmlspecialchars($lecciones ?? 0) ?>">
                <div class="helper-text">Cantidad total de lecciones del curso</div>
            </div>

            <div class="form-group">
                <label>Ícono del Curso</label>
                <input type="hidden" id="icono" name="icono" value="<?= htmlspecialchars($icono ?? '📚') ?>">
                <div class="icon-selector">
                    <?php
                    $icons = ['📚','💻','🎨','🔬','📊','🎯','🚀','🎓','📝','🌐','🎬','🎵'];
                    foreach ($icons as $ic) {
                        $sel = ($icono ?? '📚') === $ic ? 'selected' : '';
                        echo "<div class=\"icon-option $sel\" onclick=\"selectIcon(this,'$ic')\">$ic</div>";
                    }
                    ?>
                </div>
            </div>

            <div class="button-group">
                <a href="cursos_profesores.php?maestro_id=<?= (int)$maestro_id ?>" class="btn btn-secondary">← Cancelar</a>
                <button type="submit" class="btn btn-primary"><?= $cursoId > 0 ? '💾 Guardar Cambios' : '💾 Crear Curso' ?></button>
            </div>
        </form>
    </div>

    <script>
        function setFechaCreacion() {
            const d = new Date();
            const pad = n => String(n).padStart(2,'0');
            const s = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
            const input = document.getElementById('fechaCreacion');
            if (input && !input.value) input.value = s;
        }
        document.addEventListener('DOMContentLoaded', () => {
            setFechaCreacion();
            const form = document.querySelector('form');
            if (form) form.addEventListener('submit', setFechaCreacion);
        });
        function selectIcon(element, icon) {
            document.querySelectorAll('.icon-option').forEach(opt => opt.classList.remove('selected'));
            element.classList.add('selected');
            document.getElementById('icono').value = icon;
        }
    </script>
</body>
</html>
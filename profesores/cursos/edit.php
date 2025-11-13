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

// Procesar formulario cuando se envía
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $nombreCurso = trim($_POST['nombre_curso'] ?? '');
    $descripcion = trim($_POST['descripcion'] ?? '');
    $duracion = trim($_POST['duracion'] ?? '');
    $icono = trim($_POST['icono'] ?? '📚');
    $instructor = trim($_POST['instructor'] ?? '');
    $lecciones = (int)($_POST['lecciones'] ?? 0);
    $precio = trim($_POST['precio'] ?? ''); 
    $fechaCreacion = $_POST['fechaCreacion'] ?? date('Y-m-d H:i:s');

    // asegurarse de tipos para bind_param
    $maestro_id = (int)$maestro_id;
    $lecciones = (int)$lecciones;
    // convertir precio a número si corresponde (si tu columna es DECIMAL/DOUBLE)
    $precio = is_numeric($precio) ? (float)$precio : $precio;
    
    // Validaciones
    if (empty($nombreCurso)) {
        $error = "El nombre del curso es obligatorio.";
    } elseif (empty($descripcion)) {
        $error = "La descripción es obligatoria.";
    } elseif (empty($duracion)) {
        $error = "La duración es obligatoria.";
    } else {
        // Insertar curso en la base de datos
        $stmt = $conn->prepare("INSERT INTO cursos (NombreCurso, Descripcion, ProfID, Icono, TotalLecciones, Duracion, Precio, FechaCreacion) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt) {
            // tipos: s(nombre), s(descripción), i(profID), s(icono), i(lecciones), s(duración), d(precio), s(fechaCreacion)
            $stmt->bind_param("ssisisds", $nombreCurso, $descripcion, $maestro_id, $icono, $lecciones, $duracion, $precio, $fechaCreacion);
            
            if ($stmt->execute()) {
                $mensaje = "Curso creado exitosamente.";
                // Limpiar campos después de crear
                $nombreCurso = $descripcion = $duracion = $instructor = "";
                $lecciones = 0;
                $icono = "📚";
            } else {
                $error = "Error al crear el curso: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Error preparando la consulta: " . $conn->error;
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
    <title>Crear Curso - Hoot & Learn</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 2px solid #ede9fe;
        }
        
        h1 {
            color: #6b21a8;
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }
        
        .subtitle {
            color: #6b7280;
            font-size: 1rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-weight: 500;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #34d399;
        }
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #f87171;
        }
        
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        label {
            display: block;
            color: #374151;
            font-weight: 600;
            margin-bottom: 0.5rem;
            font-size: 0.95rem;
        }
        
        input[type="text"],
        input[type="number"],
        textarea,
        select {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: inherit;
        }
        
        input:focus,
        textarea:focus,
        select:focus {
            outline: none;
            border-color: #7e22ce;
            box-shadow: 0 0 0 3px rgba(126, 34, 206, 0.1);
        }
        
        textarea {
            min-height: 120px;
            resize: vertical;
        }
        
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        
        .icon-selector {
            display: grid;
            grid-template-columns: repeat(6, 1fr);
            gap: 0.5rem;
            margin-top: 0.5rem;
        }
        
        .icon-option {
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            text-align: center;
            cursor: pointer;
            font-size: 1.5rem;
            transition: all 0.3s ease;
        }
        
        .icon-option:hover {
            border-color: #7e22ce;
            background-color: #f5f3ff;
        }
        
        .icon-option.selected {
            border-color: #7e22ce;
            background-color: #ede9fe;
        }
        
        .button-group {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
        }
        
        .btn {
            padding: 0.875rem 1.5rem;
            border: none;
            border-radius: 10px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #7e22ce 0%, #5b21b6 100%);
            color: white;
            flex: 1;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(126, 34, 206, 0.3);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            flex: 1;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .helper-text {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 0.25rem;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .container {
                padding: 1.5rem;
            }
            
            .form-row {
                grid-template-columns: 1fr;
            }
            
            .icon-selector {
                grid-template-columns: repeat(4, 1fr);
            }
            
            .button-group {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>✨ Crear Nuevo Curso</h1>
            <p class="subtitle">Completa la información para crear un curso</p>
        </header>
        
        <?php if ($mensaje): ?>
            <div class="alert alert-success">
                ✅ <?= htmlspecialchars($mensaje) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <!-- input oculto para la fecha/hora del cliente -->
            <input type="hidden" id="fechaCreacion" name="fechaCreacion" value="<?= htmlspecialchars($fechaCreacion ?? '') ?>">

            <div class="form-group">
                <label for="nombre_curso">Nombre del Curso *</label>
                <input 
                    type="text" 
                    id="nombre_curso" 
                    name="nombre_curso" 
                    required
                    placeholder="Ej: Introducción a JavaScript"
                    value="<?= htmlspecialchars($nombreCurso ?? '') ?>"
                >
            </div>
            
            <div class="form-group">
                <label for="descripcion">Descripción *</label>
                <textarea 
                    id="descripcion" 
                    name="descripcion" 
                    required
                    placeholder="Describe de qué trata el curso, qué aprenderán los estudiantes..."
                ><?= htmlspecialchars($descripcion ?? '') ?></textarea>
                <div class="helper-text">Proporciona una descripción detallada del curso</div>
            </div>
            
            <div class="form-row">
                <div class="form-group">
                    <label for="instructor">Nombre del Instructor *</label>
                    <input 
                        type="text" 
                        id="instructor" 
                        name="instructor" 
                        required
                        placeholder="Tu nombre"
                        value="<?= htmlspecialchars($instructor ?? '') ?>"
                    >
                </div>
                
                <div class="form-group">
                    <label for="duracion">Duración *</label>
                    <input 
                        type="text" 
                        id="duracion" 
                        name="duracion" 
                        required
                        placeholder="Ej: 8 semanas, 40 horas"
                        value="<?= htmlspecialchars($duracion ?? '') ?>"
                    >
                </div>
            </div>
            
            <div class="form-group">
                <label for="lecciones">Número de Lecciones</label>
                <input 
                    type="number" 
                    id="lecciones" 
                    name="lecciones" 
                    min="0"
                    placeholder="Ej: 12"
                    value="<?= htmlspecialchars($lecciones ?? 0) ?>"
                >
                <div class="helper-text">Cantidad total de lecciones del curso</div>
            </div>
            
            <div class="form-group">
                <label>Ícono del Curso</label>
                <input type="hidden" id="icono" name="icono" value="<?= htmlspecialchars($icono ?? '📚') ?>">
                <div class="icon-selector">
                    <div class="icon-option selected" onclick="selectIcon(this, '📚')">📚</div>
                    <div class="icon-option" onclick="selectIcon(this, '💻')">💻</div>
                    <div class="icon-option" onclick="selectIcon(this, '🎨')">🎨</div>
                    <div class="icon-option" onclick="selectIcon(this, '🔬')">🔬</div>
                    <div class="icon-option" onclick="selectIcon(this, '📊')">📊</div>
                    <div class="icon-option" onclick="selectIcon(this, '🎯')">🎯</div>
                    <div class="icon-option" onclick="selectIcon(this, '🚀')">🚀</div>
                    <div class="icon-option" onclick="selectIcon(this, '🎓')">🎓</div>
                    <div class="icon-option" onclick="selectIcon(this, '📝')">📝</div>
                    <div class="icon-option" onclick="selectIcon(this, '🌐')">🌐</div>
                    <div class="icon-option" onclick="selectIcon(this, '🎬')">🎬</div>
                    <div class="icon-option" onclick="selectIcon(this, '🎵')">🎵</div>
                </div>
            </div>
            
            <div class="button-group">
                <a href="cursos_profesores.php" class="btn btn-secondary">
                    ← Cancelar
                </a>
                <button type="submit" class="btn btn-primary">
                    💾 Crear Curso
                </button>
            </div>
        </form>
    </div>
    
    <script>
        // Establece fecha/hora local del cliente en formato YYYY-MM-DD HH:MM:SS
        function setFechaCreacion() {
            const d = new Date();
            const pad = n => String(n).padStart(2, '0');
            const s = `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())} ${pad(d.getHours())}:${pad(d.getMinutes())}:${pad(d.getSeconds())}`;
            const input = document.getElementById('fechaCreacion');
            if (input) input.value = s;
        }

        // Poner valor al cargar y justo antes de enviar el formulario
        document.addEventListener('DOMContentLoaded', () => {
            setFechaCreacion();
            const form = document.querySelector('form');
            if (form) form.addEventListener('submit', setFechaCreacion);
        });
        
        function selectIcon(element, icon) {
            // Remover selección anterior
            document.querySelectorAll('.icon-option').forEach(opt => {
                opt.classList.remove('selected');
            });
            
            // Seleccionar nuevo ícono
            element.classList.add('selected');
            document.getElementById('icono').value = icon;
        }
    </script>
</body>
</html>
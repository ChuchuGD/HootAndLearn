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
$curso = null;

// Obtener ID del curso
$cursoId = $_GET['id'] ?? null;

if (!$cursoId) {
    header("Location: cursos_profesores.php");
    exit();
}

// Obtener datos del curso para mostrar
$stmt = $conn->prepare("SELECT * FROM cursos WHERE IDCurso = ? AND ProfID = ?");
if ($stmt) {
    $stmt->bind_param("ii", $cursoId, $maestro_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $curso = $result->fetch_assoc();
    $stmt->close();
}

if (!$curso) {
    $error = "Curso no encontrado o no tienes permisos para eliminarlo.";
}

// Procesar eliminación cuando se confirma
if ($_SERVER["REQUEST_METHOD"] == "POST" && $curso) {
    $confirmar = $_POST['confirmar'] ?? '';
    
    if ($confirmar === 'ELIMINAR') {
        // Eliminar curso de la base de datos
        $stmt = $conn->prepare("DELETE FROM cursos WHERE IDCurso = ? AND ProfID = ?");
        
        if ($stmt) {
            $stmt->bind_param("ii", $cursoId, $maestro_id);
            
            if ($stmt->execute()) {
                $stmt->close();
                $conn->close();
                // Redirigir con mensaje de éxito
                header("Location: cursos_profesores.php?deleted=1");
                exit();
            } else {
                $error = "Error al eliminar el curso: " . $stmt->error;
            }
            $stmt->close();
        } else {
            $error = "Error preparando la consulta: " . $conn->error;
        }
    } else {
        $error = "Debes escribir 'ELIMINAR' para confirmar la acción.";
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Eliminar Curso - Hoot & Learn</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            min-height: 100vh;
            padding: 2rem;
        }
        
        .container {
            max-width: 600px;
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
            border-bottom: 2px solid #fee2e2;
        }
        
        .warning-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        h1 {
            color: #dc2626;
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
        
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #f87171;
        }
        
        .course-info {
            background: #fef3f2;
            border: 2px solid #fca5a5;
            border-radius: 15px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .course-title {
            font-size: 1.3rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .course-details {
            color: #6b7280;
            font-size: 0.95rem;
            line-height: 1.6;
        }
        
        .warning-box {
            background: #fef2f2;
            border: 2px solid #dc2626;
            border-radius: 10px;
            padding: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .warning-box h3 {
            color: #dc2626;
            font-size: 1.1rem;
            margin-bottom: 0.75rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .warning-box ul {
            margin-left: 1.5rem;
            color: #4b5563;
        }
        
        .warning-box li {
            margin-bottom: 0.5rem;
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
        
        input[type="text"] {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 1rem;
            transition: all 0.3s ease;
            font-family: 'Courier New', monospace;
            font-weight: 600;
        }
        
        input:focus {
            outline: none;
            border-color: #dc2626;
            box-shadow: 0 0 0 3px rgba(220, 38, 38, 0.1);
        }
        
        .helper-text {
            font-size: 0.85rem;
            color: #6b7280;
            margin-top: 0.25rem;
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
            flex: 1;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, #dc2626 0%, #991b1b 100%);
            color: white;
        }
        
        .btn-danger:hover:not(:disabled) {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(220, 38, 38, 0.3);
        }
        
        .btn-danger:disabled {
            background: #d1d5db;
            cursor: not-allowed;
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .container {
                padding: 1.5rem;
            }
            
            .button-group {
                flex-direction: column-reverse;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <div class="warning-icon">⚠️</div>
            <h1>Eliminar Curso</h1>
            <p class="subtitle">Esta acción es permanente y no se puede deshacer</p>
        </header>
        
        <?php if ($error): ?>
            <div class="alert alert-error">
                ❌ <?= htmlspecialchars($error) ?>
            </div>
        <?php endif; ?>
        
        <?php if ($curso): ?>
            <div class="course-info">
                <div class="course-title">
                    <span><?= htmlspecialchars($curso['Icono'] ?? '📚') ?></span>
                    <span><?= htmlspecialchars($curso['Titulo']) ?></span>
                </div>
                <div class="course-details">
                    <strong>ID:</strong> <?= htmlspecialchars($curso['IDCurso']) ?><br>
                    <strong>Instructor:</strong> <?= htmlspecialchars($curso['Instructor'] ?? 'No especificado') ?><br>
                    <strong>Duración:</strong> <?= htmlspecialchars($curso['Duracion'] ?? 'No especificada') ?><br>
                    <strong>Lecciones:</strong> <?= htmlspecialchars($curso['Lecciones'] ?? 0) ?>
                </div>
            </div>
            
            <div class="warning-box">
                <h3>⚠️ Advertencia</h3>
                <p style="margin-bottom: 1rem;">Al eliminar este curso:</p>
                <ul>
                    <li>Se perderán todos los datos del curso</li>
                    <li>Los estudiantes inscritos perderán el acceso</li>
                    <li>Se eliminarán todas las actividades y evaluaciones asociadas</li>
                    <li>Esta acción NO se puede deshacer</li>
                </ul>
            </div>
            
            <form method="POST" action="" id="deleteForm">
                <div class="form-group">
                    <label for="confirmar">
                        Para confirmar, escribe <strong style="color: #dc2626;">ELIMINAR</strong> en el campo:
                    </label>
                    <input 
                        type="text" 
                        id="confirmar" 
                        name="confirmar" 
                        placeholder="Escribe ELIMINAR"
                        autocomplete="off"
                        oninput="checkConfirmation()"
                    >
                    <div class="helper-text">Debes escribir exactamente "ELIMINAR" (en mayúsculas)</div>
                </div>
                
                <div class="button-group">
                    <a href="cursos_profesores.php" class="btn btn-secondary">
                        ← Cancelar
                    </a>
                    <button type="submit" class="btn btn-danger" id="deleteBtn" disabled>
                        🗑️ Eliminar Curso
                    </button>
                </div>
            </form>
        <?php else: ?>
            <div style="text-align: center; padding: 2rem;">
                <p style="color: #6b7280; margin-bottom: 1rem;">No se pudo cargar el curso</p>
                <a href="cursos_profesores.php" class="btn btn-secondary">
                    ← Volver a Mis Cursos
                </a>
            </div>
        <?php endif; ?>
    </div>
    
    <script>
        function checkConfirmation() {
            const input = document.getElementById('confirmar');
            const deleteBtn = document.getElementById('deleteBtn');
            
            if (input.value === 'ELIMINAR') {
                deleteBtn.disabled = false;
            } else {
                deleteBtn.disabled = true;
            }
        }
        
        // Confirmar antes de enviar
        document.getElementById('deleteForm')?.addEventListener('submit', function(e) {
            if (!confirm('¿Estás absolutamente seguro de que deseas eliminar este curso? Esta acción NO se puede deshacer.')) {
                e.preventDefault();
            }
        });
    </script>
</body>
</html>
<?php
session_start();

// Permitir maestro_id por GET o sesión
$maestro_id = null;
if (!empty($_GET['maestro_id'])) {
    $maestro_id = (int)$_GET['maestro_id'];
    $_SESSION['maestro_id'] = $maestro_id;
} else {
    $maestro_id = $_SESSION['maestro_id'] ?? null;
}

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

// Verificar columnas de la tabla
$expectedCol = 'ProfID';
$colExists = false;
$cols = [];

$res = $conn->query("SHOW COLUMNS FROM `cursos`");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $cols[] = $row['Field'];
        if ($row['Field'] === $expectedCol) {
            $colExists = true;
        }
    }
    $res->free();
}

if (!$colExists) {
    die("La columna esperada '{$expectedCol}' no existe en la tabla cursos.");
}

// Consultar cursos del profesor
$sql = "SELECT * FROM `cursos` WHERE `{$expectedCol}` = ?";
$stmt = $conn->prepare($sql);
if ($stmt === false) {
    die("Error preparando la consulta: " . $conn->error);
}
$stmt->bind_param("i", $maestro_id);

if (!$stmt->execute()) {
    $stmt->close();
    $conn->close();
    die("Error ejecutando la consulta: " . $stmt->error);
}

$result = $stmt->get_result();

$cursos = [];
while ($row = $result->fetch_assoc()) {
    $cursos[] = $row;
}

$stmt->close();
$conn->close();

// Verificar si hay mensaje de éxito por eliminación
$showDeleteSuccess = isset($_GET['deleted']) && $_GET['deleted'] == '1';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Cursos - Hoot & Learn</title>
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
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            padding: 2rem;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        header {
            text-align: center;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 2px solid #ede9fe;
        }
        
        h1 {
            color: #6b21a8;
            font-size: 2.5rem;
            margin-bottom: 0.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
        }
        
        .subtitle {
            color: #6b7280;
            font-size: 1.1rem;
        }
        
        .alert {
            padding: 1rem;
            border-radius: 10px;
            margin-bottom: 1.5rem;
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #34d399;
        }
        
        .action-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .stats {
            display: flex;
            gap: 1.5rem;
        }
        
        .stat-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            color: #6b7280;
            font-weight: 600;
        }
        
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
            padding: 0.75rem 1.5rem;
            border-radius: 10px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
            font-size: 1rem;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #7e22ce 0%, #5b21b6 100%);
            color: white;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(126, 34, 206, 0.3);
        }
        
        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #e5e7eb;
        }
        
        .btn-secondary:hover {
            background: #e5e7eb;
        }
        
        .btn-edit {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .btn-edit:hover {
            box-shadow: 0 5px 15px rgba(59, 130, 246, 0.3);
        }
        
        .btn-delete {
            background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%);
            color: white;
            padding: 0.5rem 1rem;
            font-size: 0.9rem;
        }
        
        .btn-delete:hover {
            box-shadow: 0 5px 15px rgba(239, 68, 68, 0.3);
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        
        th, td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
        }
        
        th {
            background-color: #f9fafb;
            color: #374151;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.85rem;
            letter-spacing: 0.05em;
        }
        
        tr:hover {
            background-color: #f9fafb;
        }
        
        .course-icon {
            font-size: 1.5rem;
            margin-right: 0.5rem;
        }
        
        .course-name {
            display: flex;
            align-items: center;
            font-weight: 600;
            color: #1f2937;
        }
        
        .course-description {
            max-width: 400px;
            color: #6b7280;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }
        
        .actions-cell {
            display: flex;
            gap: 0.5rem;
            justify-content: flex-end;
        }
        
        .no-cursos {
            text-align: center;
            padding: 4rem 2rem;
            color: #6b7280;
        }
        
        .no-cursos-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }
        
        .no-cursos h3 {
            font-size: 1.5rem;
            color: #374151;
            margin-bottom: 0.5rem;
        }
        
        .no-cursos p {
            margin-bottom: 2rem;
        }
        
        @media (max-width: 768px) {
            body {
                padding: 1rem;
            }
            
            .container {
                padding: 1rem;
            }
            
            h1 {
                font-size: 1.8rem;
                flex-direction: column;
                gap: 0.5rem;
            }
            
            .action-bar {
                flex-direction: column;
                align-items: stretch;
            }
            
            .stats {
                justify-content: space-around;
            }
            
            table {
                font-size: 0.85rem;
            }
            
            th, td {
                padding: 0.75rem 0.5rem;
            }
            
            .course-description {
                max-width: 200px;
            }
            
            .actions-cell {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <header>
            <h1>
                <span>📚</span>
                Mis Cursos
            </h1>
            <p class="subtitle">Gestiona y administra todos tus cursos</p>
        </header>
        
        <?php if ($showDeleteSuccess): ?>
            <div class="alert alert-success">
                <span>✅</span>
                <span>El curso ha sido eliminado exitosamente</span>
            </div>
        <?php endif; ?>
        
        <div class="action-bar">
            <div class="stats">
                <div class="stat-item">
                    <span>📊</span>
                    <span>Total de cursos: <?= count($cursos) ?></span>
                </div>
            </div>
            <a href="create.php" class="btn btn-primary">
                <span>➕</span>
                Crear Nuevo Curso
            </a>
        </div>

        <?php if (!empty($cursos)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Curso</th>
                        <th>Descripción</th>
                        <th>Duración</th>
                        <th>Lecciones</th>
                        <th style="text-align: right;">Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cursos as $curso): ?>
<tr>
    <td><?= (int)$curso['CursoID'] ?></td>
    <td><?= htmlspecialchars($curso['NombreCurso']) ?></td>
    <td><?= htmlspecialchars($curso['Duracion'] ?? '') ?></td>
    <td>
        <a class="btn" href="student-manage.php?id=<?= (int)$curso['CursoID'] ?>&maestro_id=<?= (int)$maestro_id ?>">Gestionar</a>
        <a class="btn" href="edit.php?id=<?= (int)$curso['CursoID'] ?>&maestro_id=<?= (int)$maestro_id ?>">Editar</a>
        <a class="btn btn-danger" href="eliminar_curso.php?id=<?= (int)$curso['CursoID'] ?>&maestro_id=<?= (int)$maestro_id ?>">Eliminar</a>
    </td>
</tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-cursos">
                <div class="no-cursos-icon">📚</div>
                <h3>Aún no has creado ningún curso</h3>
                <p>¡Comienza creando tu primer curso y comparte tu conocimiento!</p>
                <a href="create.php" class="btn btn-primary">
                    <span>➕</span>
                    Crear Mi Primer Curso
                </a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
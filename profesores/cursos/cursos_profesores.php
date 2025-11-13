<?php
// filepath: [cursos_profesores.php](http://_vscodecontentref_/3)
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
    header("Location: ../portal/profesor-login.php");
    exit();
}

$servername = "localhost";
$username = "root";
$password = "";
$dbname = "hootlearn";

// Crear la conexión
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}
$conn->set_charset("utf8mb4");

// Verificar que la columna que relaciona al profesor exista en la tabla Cursos
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
} else {
    die("No se pudo leer la estructura de la tabla cursos: " . $conn->error);
}

if (!$colExists) {
    die("La columna esperada '{$expectedCol}' no existe en la tabla cursos. Columnas disponibles: " . implode(', ', $cols));
}

// Preparar y ejecutar la consulta de cursos para el profesor
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

// Renderizar HTML directamente (sin include)
header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mis Cursos - Hoot & Learn</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 0;
            background-color: #f3f4f6;
        }
        header {
            background-color: #6b21a8;
            color: white;
            padding: 1rem;
            text-align: center;
            font-size: 1.8rem;
            font-weight: bold;
        }
        .container {
            max-width: 900px;
            margin: 2rem auto;
            background-color: white;
            padding: 2rem;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        h2 {
            color: #4c1d95;
            text-align: center;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }
        th, td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
            text-align: center;
        }
        th {
            background-color: #ede9fe;
            color: #4c1d95;
        }
        tr:hover {
            background-color: #f5f3ff;
        }
        .btn {
            display: inline-block;
            background-color: #7e22ce;
            color: white;
            padding: 8px 16px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .btn:hover {
            background-color: #5b21b6;
        }
        .no-cursos {
            text-align: center;
            color: #6b7280;
            padding: 1rem;
        }
    </style>
</head>
<body>
    <header>Mis Cursos - Hoot & Learn</header>

    <div class="container">
        <h2>Lista de Cursos Creados</h2>

        <?php if (!empty($cursos)): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Nombre del Curso</th>
                        <th>Descripción</th>
                        <th>Duración</th>
                        <th>Acciones</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($cursos as $curso): ?>
                        <tr>
                            <td><?= htmlspecialchars($curso['CursoID'] ?? '') ?></td>
                            <td><?= htmlspecialchars($curso['NombreCurso'] ?? '') ?></td>
                            <td style="max-width:300px; word-wrap:break-word;"><?= htmlspecialchars($curso['Descripcion'] ?? '') ?></td>
                            <td><?= htmlspecialchars($curso['Duracion'] ?? '') ?></td>
                            <td>
                                <a class="btn" href="editar_curso.php?id=<?= urlencode($curso['CursoID'] ?? '') ?>">Editar</a>
                                <a class="btn" href="eliminar_curso.php?id=<?= urlencode($curso['CursoID'] ?? '') ?>">Eliminar</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php else: ?>
            <div class="no-cursos">Aún no has creado ningún curso.</div>
        <?php endif; ?>

        <div style="text-align:center; margin-top:2rem;">
            <a href="create.php" class="btn">+ Crear Nuevo Curso</a>
        </div>
    </div>
</body>
</html>

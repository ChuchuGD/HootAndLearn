<?php
include 'conexion.php';
session_start();

$profesorID = $_SESSION['profesor_id'] ?? null;

if (!$profesorID) {
    echo json_encode(["error" => "No se ha iniciado sesión."]);
    exit;
}

$accion = $_GET['accion'] ?? '';

if ($accion === 'cursos') {
    $sql = "SELECT CursoID, NombreCurso FROM cursos WHERE ProfID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $profesorID);
    $stmt->execute();
    $result = $stmt->get_result();
    $cursos = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($cursos);
}

elseif ($accion === 'grupos' && isset($_GET['cursoID'])) {
    $cursoID = $_GET['cursoID'];
    $sql = "SELECT GrupoID, NombreGrupo FROM grupos WHERE CursoID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cursoID);
    $stmt->execute();
    $result = $stmt->get_result();
    $grupos = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($grupos);
}

elseif ($accion === 'alumnos' && isset($_GET['cursoID'])) {
    $cursoID = $_GET['cursoID'];
    $sql = "SELECT e.EstID, CONCAT(e.EstNombre, ' ', e.EstApellido) AS NombreCompleto
            FROM estudianteregistro e
            INNER JOIN inscripciones i ON e.EstID = i.EstID
            WHERE i.CursoID = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $cursoID);
    $stmt->execute();
    $result = $stmt->get_result();
    $alumnos = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($alumnos);
}

$conn->close();
?>

<?php
include 'conexion.php';
session_start();

$profesor_id = $_SESSION['profesor_id'] ?? null;

if (!$profesor_id) {
    echo json_encode(['status' => 'error', 'message' => 'Sesión no válida']);
    exit();
}

// --- Recibir datos enviados desde el formulario ---
$nombre = $_POST['nombre'] ?? '';
$descripcion = $_POST['descripcion'] ?? '';
$fecha = $_POST['fecha'] ?? '';
$tipo = $_POST['tipo'] ?? ''; // examen, tarea, etc.
$grupo_id = $_POST['grupo_id'] ?? null;

// Validar datos mínimos
if (empty($nombre) || empty($grupo_id)) {
    echo json_encode(['status' => 'error', 'message' => 'Faltan datos obligatorios']);
    exit();
}

// --- Insertar en la tabla de evaluaciones ---
$sql = "INSERT INTO evaluaciones (NombreEval, Descripcion, FechaEntrega, TipoEval, ProfID, GrupoID)
        VALUES (?, ?, ?, ?, ?, ?)";
$stmt = $conn->prepare($sql);
$stmt->bind_param("ssssii", $nombre, $descripcion, $fecha, $tipo, $profesor_id, $grupo_id);

if ($stmt->execute()) {
    // Obtener ID de la evaluación recién creada
    $evaluacion_id = $stmt->insert_id;

    // --- Asociar automáticamente a todos los alumnos del grupo ---
    $query = "SELECT AlumnoID FROM inscripciones WHERE GrupoID = ?";
    $ins = $conn->prepare($query);
    $ins->bind_param("i", $grupo_id);
    $ins->execute();
    $result = $ins->get_result();

    while ($row = $result->fetch_assoc()) {
        $insertAlumno = $conn->prepare("INSERT INTO asignaciones (AlumnoID, EvalID, Estado) VALUES (?, ?, 'Pendiente')");
        $insertAlumno->bind_param("ii", $row['AlumnoID'], $evaluacion_id);
        $insertAlumno->execute();
        $insertAlumno->close();
    }

    echo json_encode(['status' => 'success', 'message' => 'Evaluación asignada correctamente']);
} else {
    echo json_encode(['status' => 'error', 'message' => 'Error al guardar la evaluación']);
}

$stmt->close();
$conn->close();
?>

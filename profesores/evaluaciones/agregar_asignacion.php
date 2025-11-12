<?php
include 'conexion.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cursoID = $_POST['cursoID'] ?? null;
    $titulo = $_POST['titulo'] ?? null;
    $descripcion = $_POST['descripcion'] ?? null;
    $duracion = $_POST['duracion'] ?? 60;
    $totalPreguntas = $_POST['totalPreguntas'] ?? 10;
    $puntos = $_POST['puntos'] ?? 100;
    $fechaVencimiento = $_POST['fechaVencimiento'] ?? null;
    $intentos = $_POST['intentos'] ?? 1;
    $requiereArchivo = isset($_POST['requiereArchivo']) ? 1 : 0;

    if (!$cursoID || !$titulo || !$fechaVencimiento) {
        echo json_encode(["status" => "error", "message" => "Faltan campos obligatorios"]);
        exit;
    }

    $sql = "INSERT INTO evaluaciones 
            (CursoID, Titulo, Descripcion, Duracion, TotalPreguntas, Puntos, FechaVencimiento, Intentos, RequiereArchivo)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param(
        "issiiisii",
        $cursoID,
        $titulo,
        $descripcion,
        $duracion,
        $totalPreguntas,
        $puntos,
        $fechaVencimiento,
        $intentos,
        $requiereArchivo
    );

    if ($stmt->execute()) {
        echo json_encode(["status" => "success", "message" => "Evaluación creada correctamente"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Error al crear evaluación: " . $stmt->error]);
    }

    $stmt->close();
    $conn->close();
}
?>

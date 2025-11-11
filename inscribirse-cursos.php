<?php
header('Content-Type: application/json');

// === CONEXIÓN A LA BASE DE DATOS ===
$servername = "127.0.0.1";
$username = "root";
$password = "2435";
$database = "Hoot&Learn";
$port = 3307;

$conn = new mysqli($servername, $username, $password, $database, $port);

if ($conn->connect_error) {
    die(json_encode(["error" => "Error de conexión: " . $conn->connect_error]));
}

// === CONSULTAR CURSOS ===
$sql = "SELECT id_curso AS id, NombreCurso AS title, Categoria AS category, 
                Descripcion AS description, Precio AS price, Duracion AS duration, 
                Icono AS icon FROM CursosActuales";
$result = $conn->query($sql);

$courses = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $courses[] = $row;
    }
}

echo json_encode($courses, JSON_UNESCAPED_UNICODE);
$conn->close();
?>

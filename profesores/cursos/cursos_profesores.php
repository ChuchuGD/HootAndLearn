<?php
$servername = "127.0.0.1";
$username = "root";
$password = "2435";
$database = "Hoot&Learn";
$port = 3306;

$conn = new mysqli($servername, $username, $password, $database, $port);

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

session_start();
$maestro_id = $_SESSION['maestro_id'] ?? null;

if (!$maestro_id) {
    header("Location: login.php");
    exit();
}

$sql = "SELECT * FROM Cursos WHERE IDMstro = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $maestro_id);
$stmt->execute();
$result = $stmt->get_result();

$cursos = [];
while ($row = $result->fetch_assoc()) {
    $cursos[] = $row;
}

$stmt->close();
$conn->close();

include 'mis_cursos.html';
?>

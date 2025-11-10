<?php
// Conexión a la BD
$servername = "127.0.0.1";
$username = "root";
$password = "2435";
$database = "hootandlearn"; // cambia al nombre real de tu BD

$conn = new mysqli($servername, $username, $password, $database, 3307); // puerto 3307 si usas XAMPP alternativo

if ($conn->connect_error) {
    die("Error de conexión: " . $conn->connect_error);
}

// Supongamos que el ID del alumno viene por GET
$idAlumno = $_GET['IDEst'] ?? null;

if (!$idAlumno) {
    echo json_encode(["error" => "No se proporcionó ID del alumno"]);
    exit;
}

// Consulta: unir las tablas necesarias
$sql = "
SELECT 
    ca.IDCurso,
    c.NombreCurso,
    CONCAT(m.MstroNombre, ' ', m.MstroAps) AS NombreMaestro,
    ca.TareasCargadas
FROM CursosActuales ca
INNER JOIN Cursos c ON ca.IDCurso = c.IDCurso
INNER JOIN MaestroRegistro m ON ca.MstroNombre = m.MstroNombre AND ca.MstroAps = m.MstroAps
WHERE ca.IDEst = '$idAlumno'
";

$result = $conn->query($sql);

$cursos = [];

if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $cursos[] = $row;
    }
}

$conn->close();

header('Content-Type: application/json');
echo json_encode($cursos);
?>

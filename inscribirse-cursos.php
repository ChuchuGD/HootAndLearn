<?php
$conexion = new mysqli("127.0.0.1", "root", "2435", "Hoot&Learn", 3307);

if ($conexion->connect_error) {
    die("Error de conexión: " . $conexion->connect_error);
}

$sql = "SELECT NombreCurso FROM CursosActuales";
$resultado = $conexion->query($sql);

if ($resultado->num_rows > 0) {
    while ($fila = $resultado->fetch_assoc()) {
        echo $fila['NombreCurso'] . "<br>";
    }
} else {
    echo "No hay cursos registrados.";
}

$conexion->close();
?>

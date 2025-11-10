<?php
// Configuración de la base de datos
$servername = "localhost"; // O la dirección IP de tu servidor de base de datos
$username = "root"; // Tu nombre de usuario de MySQL
$password = "2435"; // Tu contraseña de MySQL
$dbname = "Hoot&Learn"; // El nombre de la base de datos a la que quieres conectarte

// --- 1. Crear la conexión ---
$conn = new mysqli($servername, $username, $password, $dbname);

// --- 2. Verificar la conexión ---
if ($conn->connect_error) {
    // Si hay un error, el script termina y muestra el mensaje de error.
    die("Conexión fallida: " . $conn->connect_error);
}

// Si la ejecución llega aquí, la conexión fue exitosa.
                  
// --- 3. (Opcional) Ejecutar una consulta y trabajar con los datos ---

/*
// Ejemplo de una consulta SELECT
$sql = "SELECT id, nombre, email FROM usuarios";
$result = $conn->query($sql);

if ($result->num_rows > 0) {
    // Mostrar datos de cada fila
    while($row = $result->fetch_assoc()) {
        echo "id: " . $row["id"]. " - Nombre: " . $row["nombre"]. " - Email: " . $row["email"]. "<br>";
    }
} else {
    echo "0 resultados";
}
*/

// --- 4. Cerrar la conexión (IMPORTANTE) ---
// $conn->close();

?>
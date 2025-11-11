<?php
session_start();

// Destruir todas las variables de sesión
$_SESSION = array();

// Destruir la sesión
session_destroy();

// Redirigir al portal de estudiantes
header("Location: student-portal.php");
exit();
?>

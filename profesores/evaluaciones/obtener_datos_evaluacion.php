// ... (Tus bloques if/elseif existentes)

elseif ($accion === 'alumnosPorGrupos' && isset($_GET['groupIDs'])) {
    $groupIDs_str = $_GET['groupIDs'];
    $groupIDs_arr = array_map('intval', explode(',', $groupIDs_str)); // Convertir a array de enteros

    // Crea placeholders para la consulta (ej: ?, ?, ?)
    $placeholders = implode(',', array_fill(0, count($groupIDs_arr), '?'));
    $tipos = str_repeat('i', count($groupIDs_arr));

    $sql = "SELECT e.EstID, CONCAT(e.EstNombre, ' ', e.EstApellido) AS NombreCompleto
            FROM estudianteregistro e
            INNER JOIN grupomembros gm ON e.EstID = gm.EstID
            WHERE gm.GrupoID IN ($placeholders)";
            
    $stmt = $conn->prepare($sql);
    
    // Necesitamos pasar el array como referencias (truco de PHP)
    $bind_params = array_merge([$tipos], $groupIDs_arr);
    call_user_func_array([$stmt, 'bind_param'], refValues($bind_params));
    
    $stmt->execute();
    $result = $stmt->get_result();
    $alumnos = $result->fetch_all(MYSQLI_ASSOC);
    echo json_encode($alumnos);
}

// ... (Tu función refValues si la tienes, y el cierre de conexión)

// Pequeña función auxiliar necesaria si usas call_user_func_array
function refValues($arr){
    if (strnatcmp(phpversion(),'5.3') >= 0) // PHP 5.3+
    {
        $refs = array();
        foreach($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}

$conn->close();
?>
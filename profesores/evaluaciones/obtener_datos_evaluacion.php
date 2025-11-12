<?php
include 'conexion.php';

// Función auxiliar necesaria para mysqli::bind_param con un número variable de argumentos
function refValues($arr) {
    if (strnatcmp(phpversion(), '5.3') >= 0) {
        $refs = array();
        foreach ($arr as $key => $value)
            $refs[$key] = &$arr[$key];
        return $refs;
    }
    return $arr;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 1. Obtención y validación de datos de la evaluación
    $cursoID = $_POST['cursoID'] ?? null;
    $titulo = $_POST['titulo'] ?? null;
    $descripcion = $_POST['descripcion'] ?? null;
    $duracion = $_POST['duracion'] ?? 60;
    $totalPreguntas = $_POST['totalPreguntas'] ?? 10;
    $puntos = $_POST['puntos'] ?? 100;
    $fechaVencimiento = $_POST['fechaVencimiento'] ?? null;
    $intentos = $_POST['intentos'] ?? 1;
    $requiereArchivo = isset($_POST['requiereArchivo']) ? 1 : 0;
    
    // Recibir los arrays JSON de estudiantes y grupos seleccionados del frontend
    // Estos se envían como cadenas JSON desde el JavaScript
    $estudiantesJSON = $_POST['assignedStudents'] ?? '[]';
    $gruposJSON = $_POST['assignedGroups'] ?? '[]';

    $estudiantesAsignar = json_decode($estudiantesJSON, true);
    $gruposAsignar = json_decode($gruposJSON, true);
    
    // Validación Mínima (Asegurar campos de evaluación y asignación)
    if (!$cursoID || !$titulo || !$fechaVencimiento) {
        echo json_encode(["status" => "error", "message" => "Faltan campos obligatorios de la evaluación (CursoID, Título, FechaVencimiento)"]);
        exit;
    }
    
    if (empty($estudiantesAsignar) && empty($gruposAsignar)) {
        echo json_encode(["status" => "error", "message" => "Debes seleccionar al menos un estudiante o grupo para asignar la evaluación."]);
        exit;
    }

    // Usaremos **transacciones** para asegurar que se guarda la evaluación Y sus asignaciones
    $conn->begin_transaction();
    $evaluacionID = null;
    $estudiantesAAsignar = []; // Lista final de EstIDs únicos a los que se asignará la evaluación

    try {
        // A. Insertar la evaluación principal en la tabla 'evaluaciones'
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

        if (!$stmt->execute()) {
            throw new Exception("Error al crear evaluación principal: " . $stmt->error);
        }
        $evaluacionID = $conn->insert_id;
        $stmt->close();

        // B. Asignar Estudiantes Individuales
        // El frontend ya envió una lista de EstIDs para asignación individual.
        $estudiantesAAsignar = $estudiantesAsignar;

        // C. Asignar Estudiantes de Grupos
        if (!empty($gruposAsignar)) {
            // Convertir el array de GroupID a una lista para la consulta (ej: 1,2,3)
            $placeholders = implode(',', array_fill(0, count($gruposAsignar), '?'));
            $tipos = str_repeat('i', count($gruposAsignar)); // Todos son enteros (i)

            // Obtener EstID de los estudiantes en los grupos seleccionados
            $sqlGrupos = "SELECT e.EstID 
                         FROM estudianteregistro e
                         INNER JOIN grupomembros gm ON e.EstID = gm.EstID
                         WHERE gm.GrupoID IN ($placeholders)";

            $stmtGrupos = $conn->prepare($sqlGrupos);
            
            // Usar la función auxiliar para bind_param con un número variable de parámetros
            $bind_params = array_merge([$tipos], $gruposAsignar);
            call_user_func_array([$stmtGrupos, 'bind_param'], refValues($bind_params));
            
            $stmtGrupos->execute();
            $resultGrupos = $stmtGrupos->get_result();
            
            while ($row = $resultGrupos->fetch_assoc()) {
                // Agregar estudiantes del grupo a la lista si NO están ya
                if (!in_array($row['EstID'], $estudiantesAAsignar)) {
                    $estudiantesAAsignar[] = $row['EstID'];
                }
            }
            $stmtGrupos->close();
        }

        // D. Insertar Asignaciones Individuales Únicas
        if (!empty($estudiantesAAsignar)) {
            $estudiantesAAsignar = array_unique($estudiantesAAsignar); // Eliminar posibles duplicados
            
            // Preparar la consulta de inserción de asignaciones
            $sqlAsignacion = "INSERT INTO evaluacion_asignacion_estudiante (EvaluacionID, EstID) VALUES (?, ?)";
            $stmtAsignacion = $conn->prepare($sqlAsignacion);

            foreach ($estudiantesAAsignar as $estID) {
                // El EstID debe ser un entero
                $estID_int = (int)$estID;
                
                $stmtAsignacion->bind_param("ii", $evaluacionID, $estID_int);
                
                if (!$stmtAsignacion->execute()) {
                    // Si falla una asignación (por ejemplo, clave duplicada si ya se asignó), lo registramos pero no abortamos el COMMIT, 
                    // a menos que sea un error crítico de DB.
                    // Si la tabla tiene la restricción UNIQUE(EvaluacionID, EstID), esto podría fallar si se intenta reasignar.
                    error_log("Fallo al asignar EvaluacionID {$evaluacionID} a EstID {$estID_int}: " . $stmtAsignacion->error);
                }
            }
            $stmtAsignacion->close();
        }

        // Si todo va bien, confirmamos los cambios en la DB
        $conn->commit();
        echo json_encode([
            "status" => "success", 
            "message" => "Evaluación creada y asignada a " . count($estudiantesAAsignar) . " estudiantes exitosamente.", 
            // Esto es útil para que el frontend actualice su lista de asignaciones
            "evaluacionID" => $evaluacionID,
            "assignedStudents" => json_encode($estudiantesAAsignar),
            "assignedGroups" => $gruposJSON // Guardamos la selección de grupos también
        ]);

    } catch (Exception $e) {
        // Si algo falla, revertimos todos los cambios
        $conn->rollback();
        echo json_encode(["status" => "error", "message" => "Error de transacción: " . $e->getMessage()]);
    }

    $conn->close();
}
// Si el método no es POST, no hacemos nada
?>